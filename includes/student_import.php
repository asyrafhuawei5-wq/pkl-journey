<?php
// includes/student_import.php
// Helper import data murid dari file Excel untuk panel Admin.
// Tidak membuat halaman baru; seluruh aksi diproses di dashboard/admin.php.

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pklStudentImportAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($pklStudentImportAutoload)) {
    require_once $pklStudentImportAutoload;
}

if (!defined('PKL_STUDENT_IMPORT_MAX_BYTES')) {
    define('PKL_STUDENT_IMPORT_MAX_BYTES', 5 * 1024 * 1024);
}
if (!defined('PKL_STUDENT_IMPORT_MAX_ROWS')) {
    define('PKL_STUDENT_IMPORT_MAX_ROWS', 1000);
}

/**
 * Cek ketersediaan library Excel (PhpSpreadsheet).
 */
function pkl_student_import_library_ready(): bool
{
    return class_exists(Spreadsheet::class) && class_exists(IOFactory::class);
}

/**
 * Pastikan kolom profil murid tersedia tanpa mengubah data lama.
 * Kolom hanya ditambahkan jika belum ada.
 *
 * @return array<int,string> Daftar kolom users yang tersedia.
 */
function pkl_student_import_schema(PDO $pdo): array
{
    static $available = null;
    if (is_array($available)) {
        return $available;
    }

    $available = [];
    try {
        $available = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return $available;
    }

    $definitions = [
        'nama'                 => "VARCHAR(100) NULL AFTER `username`",
        'kelas'                => "VARCHAR(50) NULL AFTER `nama`",
        'jurusan'              => "VARCHAR(100) NULL AFTER `kelas`",
        'must_change_password' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `jurusan`",
        'jalur_pkl'            => "VARCHAR(10) NULL AFTER `must_change_password`",
    ];

    foreach ($definitions as $column => $definition) {
        if (in_array($column, $available, true)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$column}` {$definition}");
            $available[] = $column;
        } catch (Throwable $e) {
            // Jika ALTER tidak diizinkan, fitur import akan menolak dengan pesan yang jelas.
        }
    }

    return $available;
}

/**
 * Kirim file template Excel ke browser (khusus Admin).
 */
function pkl_student_import_send_template(): void
{
    if (!pkl_student_import_library_ready()) {
        throw new RuntimeException('Library PhpSpreadsheet belum tersedia. Jalankan composer install terlebih dahulu.');
    }

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('PKL Journey')
        ->setTitle('Template Import Data Murid')
        ->setDescription('Template resmi import data murid PKL Journey');

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Murid');

    $headers = ['NIS', 'Nama', 'Kelas', 'Jurusan', 'Jalur PKL', 'Nama Perusahaan'];
    foreach ($headers as $index => $header) {
        $sheet->setCellValue([$index + 1, 1], $header);
    }

    $sheet->getStyle('A1:F1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2744']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F2744']]],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(24);

    // NIS disimpan sebagai teks agar angka nol di depan tidak hilang.
    $sheet->getStyle('A2:A' . (PKL_STUDENT_IMPORT_MAX_ROWS + 1))
        ->getNumberFormat()
        ->setFormatCode('@');

    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(32);
    $sheet->getColumnDimension('E')->setWidth(14);
    $sheet->getColumnDimension('F')->setWidth(36);
    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:F1');

    // Sheet petunjuk terpisah, tidak ikut dibaca saat import.
    $guide = $spreadsheet->createSheet();
    $guide->setTitle('Petunjuk');
    $guide->setCellValue('A1', 'Petunjuk Import Data Murid & Penempatan PKL');
    $guide->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $guide->setCellValue('A3', '1. Isi data pada sheet "Data Murid" mulai baris 2.');
    $guide->setCellValue('A4', '2. Kolom wajib: NIS, Nama, Kelas, Jurusan, Jalur PKL, Nama Perusahaan. Jangan mengubah urutan header.');
    $guide->setCellValue('A5', '3. NIS harus unik, tidak boleh sama dengan NIS yang sudah terdaftar.');
    $guide->setCellValue('A6', '4. Format NIS berupa teks/angka tanpa spasi, 3-30 karakter.');
    $guide->setCellValue('A7', '5. Password awal akun murid otomatis menggunakan NIS masing-masing. Murid wajib mengganti password saat login pertama kali.');
    $guide->setCellValue('A8', '6. Jalur PKL diisi: Sekolah atau Mandiri (tidak case-sensitive).');
    $guide->setCellValue('A9', '7. Nama Perusahaan harus sudah terdaftar di Master Perusahaan sebelum import dikonfirmasi.');
    $guide->setCellValue('A10', '8. Satu perusahaan boleh menampung banyak siswa dari jalur berbeda.');
    $guide->setCellValue('A12', 'Contoh:');
    $guide->getStyle('A12')->getFont()->setBold(true);
    $guide->fromArray(['NIS', 'Nama', 'Kelas', 'Jurusan', 'Jalur PKL', 'Nama Perusahaan'], null, 'A13');
    $guide->getStyle('A13:F13')->getFont()->setBold(true);
    $guide->setCellValueExplicit('A14', '0012345678', DataType::TYPE_STRING);
    $guide->setCellValue('B14', 'Budi Santoso');
    $guide->setCellValue('C14', 'XII TKJ 1');
    $guide->setCellValue('D14', 'Teknik Komputer dan Jaringan');
    $guide->setCellValue('E14', 'Sekolah');
    $guide->setCellValue('F14', 'PT Telkom Indonesia (Witel Bekasi)');
    $guide->setCellValueExplicit('A15', '0012345679', DataType::TYPE_STRING);
    $guide->setCellValue('B15', 'Siti Rahayu');
    $guide->setCellValue('C15', 'XII TKJ 2');
    $guide->setCellValue('D15', 'Teknik Komputer dan Jaringan');
    $guide->setCellValue('E15', 'Mandiri');
    $guide->setCellValue('F15', 'PT Astra Honda Motor');
    $guide->getColumnDimension('A')->setWidth(20);
    $guide->getColumnDimension('B')->setWidth(30);
    $guide->getColumnDimension('C')->setWidth(18);
    $guide->getColumnDimension('D')->setWidth(32);
    $guide->getColumnDimension('E')->setWidth(14);
    $guide->getColumnDimension('F')->setWidth(36);

    $spreadsheet->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_import_murid.xlsx"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
}

/**
 * Ambil nilai sel sebagai string yang aman untuk validasi.
 */
function pkl_student_import_cell_value($cell): string
{
    $value = $cell->getValue();

    if ($value instanceof RichText) {
        $value = $value->getPlainText();
    }

    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value)) {
        return (string)$value;
    }
    if (is_float($value)) {
        if (is_finite($value) && floor($value) === $value && abs($value) < 1.0e15) {
            return number_format($value, 0, '', '');
        }
        return rtrim(rtrim(sprintf('%.15F', $value), '0'), '.');
    }

    return trim((string)$value);
}

/**
 * Normalisasi teks sel/header.
 */
function pkl_student_import_normalize_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $normalized = preg_replace('/\s+/u', ' ', $value);
    return $normalized === null ? $value : trim($normalized);
}

/**
 * Normalisasi header agar cocok dengan variasi penulisan umum.
 */
function pkl_student_import_normalize_header(string $value): string
{
    $value = strtolower(pkl_student_import_normalize_text($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    $value = $value === null ? '' : trim($value);
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

/**
 * Email otomatis unik untuk akun murid. NIS tetap menjadi username login.
 */
function pkl_student_import_email(string $nis): string
{
    $local = strtolower($nis);
    $local = preg_replace('/[^a-z0-9._-]/', '', $local);
    $local = trim((string)$local, '._-');
    if ($local === '') {
        $local = 'murid';
    }
    if (strlen($local) > 60) {
        $local = substr($local, 0, 60);
    }

    return $local . '@siswa.pkljourney.id';
}

/**
 * Validasi NIS.
 */
function pkl_student_import_validate_nis(string $nis): ?string
{
    if ($nis === '') {
        return 'NIS wajib diisi.';
    }
    if (mb_strlen($nis) < 3 || mb_strlen($nis) > 30) {
        return 'NIS harus terdiri dari 3-30 karakter.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $nis)) {
        return 'Format NIS tidak valid (hanya huruf, angka, titik, garis bawah, atau tanda hubung).';
    }
    if (!preg_match('/[A-Za-z0-9]/', $nis)) {
        return 'NIS harus mengandung huruf atau angka.';
    }
    return null;
}

/**
 * Validasi kolom teks wajib.
 */
function pkl_student_import_validate_text(string $value, string $label, int $maxLength, bool $allowPunctuation = true): ?string
{
    if ($value === '') {
        return $label . ' wajib diisi.';
    }
    if (mb_strlen($value) > $maxLength) {
        return $label . ' maksimal ' . $maxLength . ' karakter.';
    }
    if (preg_match('/[\x00-\x1F\x7F]/u', $value)) {
        return $label . ' mengandung karakter yang tidak valid.';
    }
    if (preg_match('/^[=+@]/', $value)) {
        return $label . ' tidak boleh diawali karakter =, +, atau @.';
    }
    if (!preg_match('/\p{L}/u', $value)) {
        return $label . ' harus mengandung huruf.';
    }
    if ($allowPunctuation && !preg_match('/^[\p{L}\p{N} .,\/&()\'’+-]+$/u', $value)) {
        return 'Format ' . $label . ' tidak valid.';
    }
    return null;
}

/**
 * Ambil akun yang sudah ada untuk daftar NIS (username) dan email otomatis.
 *
 * @return array{0: array<string,bool>, 1: array<string,bool>}
 */
function pkl_student_import_existing_accounts(PDO $pdo, array $nisList): array
{
    $usernames = [];
    $emails = [];

    $nisList = array_values(array_unique(array_filter($nisList, static fn($value) => $value !== '')));
    if (empty($nisList)) {
        return [$usernames, $emails];
    }

    foreach (array_chunk($nisList, 300) as $chunk) {
        $generatedEmails = array_map('pkl_student_import_email', $chunk);
        $values = array_merge($chunk, $generatedEmails);
        $placeholders = implode(',', array_fill(0, count($values), '?'));

        $stmt = $pdo->prepare("
            SELECT username, email
            FROM users
            WHERE username IN ($placeholders) OR email IN ($placeholders)
        ");
        $stmt->execute(array_merge($values, $values));

        foreach ($stmt->fetchAll() as $account) {
            $usernames[strtolower((string)$account['username'])] = true;
            $emails[strtolower((string)$account['email'])] = true;
        }
    }

    return [$usernames, $emails];
}

/**
 * Baca dan validasi file Excel. Tidak menulis ke database.
 *
 * @return array{rows: array<int,array<string,mixed>>, total: int, valid: int, invalid: int}
 */
function pkl_student_import_parse_file(string $path, PDO $pdo): array
{
    if (!pkl_student_import_library_ready()) {
        throw new RuntimeException('Library PhpSpreadsheet belum tersedia. Jalankan composer install terlebih dahulu.');
    }

    try {
        $reader = IOFactory::createReaderForFile($path);
        if (!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx)
            && !($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xls)) {
            throw new RuntimeException('Format file harus .xlsx atau .xls.');
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
    } catch (Throwable $e) {
        throw new RuntimeException('File Excel tidak dapat dibaca. Pastikan file berformat .xlsx/.xls dan tidak rusak.', 0, $e);
    }

    try {
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = (int)$sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $aliases = [
            'nis'        => ['nis', 'nis siswa', 'nisn', 'nomor induk siswa'],
            'nama'       => ['nama', 'nama siswa', 'nama murid', 'nama lengkap'],
            'kelas'      => ['kelas', 'tingkat', 'rombel', 'kelas rombel'],
            'jurusan'    => ['jurusan', 'kompetensi keahlian', 'program keahlian', 'jurusan kompetensi keahlian', 'jurusan program keahlian'],
            'jalur_pkl'  => ['jalur pkl', 'jalur', 'jalur pkL', 'jalur pkL murid', 'tipe pkl', 'tipe'],
            'perusahaan' => ['nama perusahaan', 'perusahaan', 'tempat pkl', 'tempat praktek', 'instansi', 'nama instansi', 'lokasi pkl'],
        ];

        $headerMap = [];
        $headerRow = 0;
        $scanUntil = min(10, $highestRow);
        $maxHeaderColumn = min($highestColumn, 20);

        for ($row = 1; $row <= $scanUntil; $row++) {
            $found = [];
            for ($column = 1; $column <= $maxHeaderColumn; $column++) {
                $header = pkl_student_import_normalize_header(
                    pkl_student_import_cell_value($sheet->getCell([$column, $row]))
                );
                if ($header === '') {
                    continue;
                }
                foreach ($aliases as $key => $aliasList) {
                    if (in_array($header, $aliasList, true)) {
                        $found[$key] = $column;
                    }
                }
            }
            // Require the original 4 core columns; jalur_pkl and perusahaan are optional for detection
            // but will be validated per-row.
            if (count(array_intersect_key($found, array_flip(['nis','nama','kelas','jurusan']))) === 4) {
                $headerMap = $found;
                $headerRow = $row;
                break;
            }
        }

        if ($headerRow === 0) {
            throw new RuntimeException('Header tidak ditemukan. File wajib memiliki kolom: NIS, Nama, Kelas, dan Jurusan.');
        }

        if ($highestRow > $headerRow + 5000) {
            throw new RuntimeException('Rentang baris file terlalu besar. Maksimal ' . PKL_STUDENT_IMPORT_MAX_ROWS . ' data per import.');
        }

        $dataRows = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $values = [
                'nis'        => pkl_student_import_normalize_text(pkl_student_import_cell_value($sheet->getCell([$headerMap['nis'], $row]))),
                'nama'       => pkl_student_import_normalize_text(pkl_student_import_cell_value($sheet->getCell([$headerMap['nama'], $row]))),
                'kelas'      => pkl_student_import_normalize_text(pkl_student_import_cell_value($sheet->getCell([$headerMap['kelas'], $row]))),
                'jurusan'    => pkl_student_import_normalize_text(pkl_student_import_cell_value($sheet->getCell([$headerMap['jurusan'], $row]))),
                'jalur_pkl'  => isset($headerMap['jalur_pkl'])
                    ? pkl_student_import_normalize_text(pkl_student_import_cell_value($sheet->getCell([$headerMap['jalur_pkl'], $row])))
                    : '',
                'perusahaan' => isset($headerMap['perusahaan'])
                    ? pkl_student_import_normalize_text(pkl_student_import_cell_value($sheet->getCell([$headerMap['perusahaan'], $row])))
                    : '',
            ];

            if ($values['nis'] === '' && $values['nama'] === '' && $values['kelas'] === '' && $values['jurusan'] === '') {
                continue;
            }

            $dataRows[] = ['row_number' => $row, 'values' => $values];
            if (count($dataRows) > PKL_STUDENT_IMPORT_MAX_ROWS) {
                throw new RuntimeException('Jumlah baris melebihi batas maksimal ' . PKL_STUDENT_IMPORT_MAX_ROWS . ' data per import.');
            }
        }

        if (empty($dataRows)) {
            throw new RuntimeException('File Excel tidak berisi data murid. Isi data mulai baris 2.');
        }

        [$existingUsernames, $existingEmails] = pkl_student_import_existing_accounts(
            $pdo,
            array_column(array_column($dataRows, 'values'), 'nis')
        );

        $seenNis = [];
        $seenEmails = [];
        $rows = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($dataRows as $dataRow) {
            $rowNumber = (int)$dataRow['row_number'];
            $values = $dataRow['values'];
            $errors = [];

            $nisError = pkl_student_import_validate_nis($values['nis']);
            if ($nisError !== null) {
                $errors[] = $nisError;
            }

            $nameError = pkl_student_import_validate_text($values['nama'], 'Nama', 100);
            if ($nameError !== null) {
                $errors[] = $nameError;
            }

            $classError = pkl_student_import_validate_text($values['kelas'], 'Kelas', 50);
            if ($classError !== null) {
                $errors[] = $classError;
            }

            $majorError = pkl_student_import_validate_text($values['jurusan'], 'Jurusan', 100);
            if ($majorError !== null) {
                $errors[] = $majorError;
            }

            // Validasi Jalur PKL (wajib, hanya Sekolah/Mandiri)
            $jalurNorm = strtolower(trim($values['jalur_pkl']));
            if ($jalurNorm === '') {
                $errors[] = 'Jalur PKL wajib diisi (Sekolah atau Mandiri).';
                $jalurNorm = '';
            } elseif (!in_array($jalurNorm, ['sekolah', 'mandiri'], true)) {
                $errors[] = 'Jalur PKL tidak valid. Isi dengan "Sekolah" atau "Mandiri".';
                $jalurNorm = '';
            } else {
                $jalurNorm = ucfirst($jalurNorm); // normalize to "Sekolah" / "Mandiri"
            }

            // Validasi Nama Perusahaan (wajib, max 255 karakter)
            $perusahaanError = pkl_student_import_validate_text($values['perusahaan'], 'Nama Perusahaan', 255);
            if ($perusahaanError !== null) {
                $errors[] = $perusahaanError;
            }

            $nisKey = strtolower($values['nis']);
            if ($values['nis'] !== '') {
                if (isset($seenNis[$nisKey])) {
                    $errors[] = 'NIS duplikat dalam file (sama dengan baris ' . $seenNis[$nisKey] . ').';
                } else {
                    $seenNis[$nisKey] = $rowNumber;
                }
            }

            if ($values['nis'] !== '' && (isset($existingUsernames[$nisKey]) || isset($existingEmails[$nisKey]))) {
                $errors[] = 'NIS sudah terdaftar di database.';
            }

            $email = $values['nis'] !== '' ? pkl_student_import_email($values['nis']) : '';
            $emailKey = strtolower($email);
            if ($email !== '') {
                if (isset($seenEmails[$emailKey])) {
                    $errors[] = 'Email otomatis (' . $email . ') duplikat dalam file (sama dengan baris ' . $seenEmails[$emailKey] . ').';
                } else {
                    $seenEmails[$emailKey] = $rowNumber;
                }
                if (isset($existingEmails[$emailKey]) || isset($existingUsernames[$emailKey])) {
                    $errors[] = 'Email otomatis (' . $email . ') sudah digunakan akun lain.';
                }
            }

            $status = empty($errors) ? 'valid' : 'invalid';
            if ($status === 'valid') {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $rows[] = [
                'row_number'  => $rowNumber,
                'nis'         => $values['nis'],
                'nama'        => $values['nama'],
                'kelas'       => $values['kelas'],
                'jurusan'     => $values['jurusan'],
                'jalur_pkl'   => $jalurNorm,
                'perusahaan'  => trim($values['perusahaan']),
                'email'       => $email,
                'status'      => $status,
                'reason'      => empty($errors) ? '-' : implode(' ', $errors),
            ];
        }

        return [
            'rows'    => $rows,
            'total'   => count($rows),
            'valid'   => $validCount,
            'invalid' => $invalidCount,
        ];
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
}

/**
 * Buat password acak unik per murid (bukan password default bersama).
 */
function pkl_student_import_generate_password(int $length = 12): string
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $maxIndex = strlen($characters) - 1;

    do {
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $maxIndex)];
        }
    } while (!preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password));

    return $password;
}

/**
 * Ambil peta nama-perusahaan → id dari tabel companies untuk school_id tertentu.
 * Kunci: nama perusahaan lowercase-trimmed.
 *
 * @return array<string,int>  map[strtolower(nama)] = company_id
 */
function pkl_student_import_resolve_companies(PDO $pdo, int $schoolId): array
{
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM `companies` WHERE school_id = :sid");
        $stmt->execute([':sid' => $schoolId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[strtolower(trim((string)$row['name']))] = (int)$row['id'];
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Simpan data valid dalam satu transaksi database.
 *
 * @param array<string,mixed> $preview
 * @param array<int,string>   $availableColumns
 * @return array{imported:int, rows:array<int,array<string,mixed>>, imported_at:string}
 */
function pkl_student_import_commit(PDO $pdo, array $preview, int $schoolId, array $availableColumns, array $companies = []): array
{
    foreach (['nama', 'kelas', 'jurusan'] as $requiredColumn) {
        if (!in_array($requiredColumn, $availableColumns, true)) {
            throw new RuntimeException('Kolom profil murid (`' . $requiredColumn . '`) belum tersedia. Import dibatalkan.');
        }
    }

    // Kolom jalur_pkl bersifat opsional untuk kompatibilitas mundur.
    $hasJalurPkl = in_array('jalur_pkl', $availableColumns, true);

    $validRows = [];
    foreach (($preview['rows'] ?? []) as $row) {
        if (($row['status'] ?? '') === 'valid') {
            $validRows[] = $row;
        }
    }

    if (empty($validRows)) {
        throw new RuntimeException('Tidak ada data valid yang dapat diimport.');
    }

    if ($schoolId <= 0) {
        $schoolId = 1;
    }

    if ($pdo->inTransaction()) {
        throw new RuntimeException('Transaksi database lain sedang berjalan. Coba lagi.');
    }

    // Siapkan password awal (menggunakan NIS) + hash sebelum transaksi agar lock database singkat.
    $preparedRows = [];
    foreach ($validRows as $row) {
        $plainPassword = (string)$row['nis'];
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Gagal membuat hash password untuk NIS ' . $row['nis'] . '.');
        }
        $preparedRows[] = [
            'row'      => $row,
            'password' => $plainPassword,
            'hash'     => $passwordHash,
        ];
    }

    $pdo->beginTransaction();

    try {
        // Cek ulang tepat sebelum insert untuk mencegah import ganda / data berubah.
        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = :username1
               OR email = :username2
               OR email = :email1
               OR username = :email2
            LIMIT 1
            FOR UPDATE
        ");

        $conflicts = [];
        foreach ($validRows as $row) {
            $email = pkl_student_import_email((string)$row['nis']);
            $check->execute([
                ':username1' => (string)$row['nis'],
                ':username2' => (string)$row['nis'],
                ':email1'    => $email,
                ':email2'    => $email,
            ]);
            if ($check->fetch()) {
                $conflicts[] = (string)$row['nis'];
            }
        }

        if (!empty($conflicts)) {
            $display = array_slice($conflicts, 0, 10);
            $suffix = count($conflicts) > 10 ? ', ...' : '';
            throw new RuntimeException(
                'Import dibatalkan karena NIS berikut sudah terdaftar saat konfirmasi: '
                . implode(', ', $display) . $suffix . '. Tidak ada data yang disimpan.'
            );
        }

        $jalurPklCol = $hasJalurPkl ? ', `jalur_pkl`' : '';
        $jalurPklVal = $hasJalurPkl ? ', :jalur_pkl' : '';
        $insert = $pdo->prepare("
            INSERT INTO users (`username`, `email`, `password`, `role`, `school_id`, `nama`, `kelas`, `jurusan`, `must_change_password`{$jalurPklCol}, `created_at`)
            VALUES (:username, :email, :password, 'murid', :school_id, :nama, :kelas, :jurusan, 1{$jalurPklVal}, NOW())
        ");

        // Prepare internship insert (company_id may be NULL if column not resolved)
        $insertInternship = $pdo->prepare("
            INSERT INTO internships (student_id, school_id, company_id, created_at)
            VALUES (:student_id, :school_id, :company_id, NOW())
        ");

        $resultRows = [];
        foreach ($preparedRows as $prepared) {
            $row = $prepared['row'];

            $params = [
                ':username'  => (string)$row['nis'],
                ':email'     => pkl_student_import_email((string)$row['nis']),
                ':password'  => $prepared['hash'],
                ':school_id' => $schoolId,
                ':nama'      => (string)$row['nama'],
                ':kelas'     => (string)$row['kelas'],
                ':jurusan'   => (string)$row['jurusan'],
            ];
            if ($hasJalurPkl) {
                $params[':jalur_pkl'] = (string)($row['jalur_pkl'] ?? '');
            }
            $insert->execute($params);

            $newUserId = (int)$pdo->lastInsertId();

            // Buat record internship (penempatan PKL) untuk siswa baru.
            $companyKey = strtolower(trim((string)($row['perusahaan'] ?? '')));
            $companyId  = $companies[$companyKey] ?? null;
            $insertInternship->execute([
                ':student_id' => $newUserId,
                ':school_id'  => $schoolId,
                ':company_id' => $companyId,
            ]);

            $resultRows[] = [
                'id'         => $newUserId,
                'nis'        => (string)$row['nis'],
                'nama'       => (string)$row['nama'],
                'kelas'      => (string)$row['kelas'],
                'jurusan'    => (string)$row['jurusan'],
                'jalur_pkl'  => (string)($row['jalur_pkl'] ?? ''),
                'perusahaan' => (string)($row['perusahaan'] ?? ''),
                'company_id' => $companyId,
                'password'   => $prepared['password'],
            ];
        }

        $pdo->commit();

        return [
            'imported'    => count($resultRows),
            'rows'        => $resultRows,
            'imported_at' => date('Y-m-d H:i:s'),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
