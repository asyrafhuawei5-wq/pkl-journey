<?php
// dashboard/admin.php
// Panel Dashboard Administrator PKL Journey

require_once __DIR__ . '/../auth/check_admin.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/student_import.php';

// Pastikan kolom profil murid tersedia untuk fitur import Excel.
// Kolom bersifat nullable dan tidak mengubah data lama.
$studentProfileColumns = pkl_student_import_schema($pdo);

// Unduh template Excel (tetap melewati penjagaan Admin di check_admin.php).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_template') {
    try {
        pkl_student_import_send_template();
    } catch (Throwable $e) {
        http_response_code(500);
        die('Gagal membuat template Excel: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    exit;
}

// Pastikan tabel database pembimbing & perusahaan tersedia
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `companies` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `school_id` INT NOT NULL DEFAULT 1,
          `name` VARCHAR(255) NOT NULL,
          `address` TEXT NULL,
          `phone` VARCHAR(50) NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_companies_school` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `internships` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `student_id` INT NOT NULL UNIQUE,
          `guru_id` INT NULL,
          `industri_id` INT NULL,
          `penguji_id` INT NULL,
          `company_id` INT NULL,
          `school_id` INT NOT NULL DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_internships_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_internships_guru` FOREIGN KEY (`guru_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_internships_industri` FOREIGN KEY (`industri_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_internships_penguji` FOREIGN KEY (`penguji_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_internships_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    // Abaikan jika tabel sudah terbuat
}

$alertMessage = '';
$alertType = '';
$activeTab = 'section-dashboard';

// Data konfirmasi penugasan massal (diisi oleh handler assign_guru_bulk fase preview).
$bulkPreview = null;

// Data konfirmasi penugasan penguji massal (diisi oleh handler assign_penguji_bulk fase preview).
$examinerPreview = null;

// Status preview & hasil import Excel (disimpan sementara di session Admin).
$importPreview = $_SESSION['student_import_preview'] ?? null;
$importResult  = $_SESSION['student_import_result'] ?? null;
unset($_SESSION['student_import_result']);

if (!is_array($importPreview)) {
    $importPreview = null;
} elseif (time() - (int)($importPreview['created_at'] ?? 0) > 1800) {
    unset($_SESSION['student_import_preview']);
    $importPreview = null;
    $alertMessage = 'Preview import sudah kedaluwarsa (lebih dari 30 menit). Silakan upload ulang file Excel.';
    $alertType = 'error';
    $activeTab = 'section-murid';
}

if (!is_array($importResult)) {
    $importResult = null;
} else {
    $activeTab = 'section-murid';
}

// Tangkap tab dari URL parameter (?tab=section-...) jika ada
if (!empty($_GET['tab'])) {
    $getTab = trim($_GET['tab']);
    if (!str_starts_with($getTab, 'section-')) {
        $getTab = 'section-' . $getTab;
    }
    if (in_array($getTab, ['section-dashboard', 'section-guru', 'section-murid', 'section-pembimbing', 'section-perusahaan', 'section-penguji'])) {
        $activeTab = $getTab;
    }
}

// ==========================================
// 1. HANDLER AKSI-AKSI ADMINISTRATOR (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    
    // Simpan tab target agar tidak reset ke dashboard saat submit form
    if (!empty($_POST['current_tab'])) {
        $postTab = trim($_POST['current_tab']);
        if (!str_starts_with($postTab, 'section-')) {
            $postTab = 'section-' . $postTab;
        }
        if (in_array($postTab, ['section-dashboard', 'section-guru', 'section-murid', 'section-pembimbing', 'section-perusahaan', 'section-penguji'])) {
            $activeTab = $postTab;
        }
    }

    // ------------------------------------------
    // IMPORT DATA MURID (EXCEL) - UPLOAD & PREVIEW
    // ------------------------------------------
    if ($action === 'import_preview') {
        $activeTab = 'section-murid';

        try {
            if (empty($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
                throw new RuntimeException('Pilih file Excel yang akan diimport.');
            }

            $upload = $_FILES['import_file'];
            $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload server.',
                UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas 5 MB.',
                UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian. Silakan coba lagi.',
                UPLOAD_ERR_NO_FILE    => 'Pilih file Excel yang akan diimport.',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak tersedia.',
                UPLOAD_ERR_CANT_WRITE => 'Server gagal menyimpan file sementara.',
                UPLOAD_ERR_EXTENSION  => 'Upload file dihentikan oleh ekstensi server.',
            ];

            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new RuntimeException($uploadErrorMessages[$uploadError] ?? 'Upload file gagal.');
            }

            $tmpPath      = (string)($upload['tmp_name'] ?? '');
            $originalName = (string)($upload['name'] ?? '');
            $fileSize     = (int)($upload['size'] ?? 0);

            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new RuntimeException('File upload tidak valid.');
            }
            if ($fileSize <= 0 || $fileSize > PKL_STUDENT_IMPORT_MAX_BYTES) {
                throw new RuntimeException('Ukuran file harus lebih dari 0 dan maksimal 5 MB.');
            }

            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls'], true)) {
                throw new RuntimeException('Format file harus .xlsx atau .xls.');
            }

            $parsed = pkl_student_import_parse_file($tmpPath, $pdo);

            $importPreview = [
                'token'      => bin2hex(random_bytes(16)),
                'filename'   => basename($originalName),
                'created_at' => time(),
                'rows'       => $parsed['rows'],
                'total'      => $parsed['total'],
                'valid'      => $parsed['valid'],
                'invalid'    => $parsed['invalid'],
            ];
            $_SESSION['student_import_preview'] = $importPreview;

            $alertMessage = 'Preview berhasil dibuat: <strong>' . (int)$parsed['valid'] . ' data valid</strong> dan <strong>'
                . (int)$parsed['invalid'] . ' data ditolak</strong>. Periksa alasan penolakan sebelum mengonfirmasi.';
            $alertType = 'success';
        } catch (Throwable $e) {
            $alertMessage = 'Gagal memproses file Excel: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $alertType = 'error';
        }
    }

    // ------------------------------------------
    // IMPORT DATA MURID (EXCEL) - KONFIRMASI
    // ------------------------------------------
    if ($action === 'import_confirm') {
        $activeTab = 'section-murid';
        $importToken = isset($_POST['import_token']) && is_string($_POST['import_token'])
            ? trim($_POST['import_token'])
            : '';

        if (!is_array($importPreview)
            || empty($importPreview['token'])
            || $importToken === ''
            || !hash_equals((string)$importPreview['token'], $importToken)) {
            $alertMessage = 'Sesi preview import tidak ditemukan atau sudah digunakan. Silakan upload ulang file Excel.';
            $alertType = 'error';
        } else {
            try {
                // Hash password dibuat unik per siswa; beri waktu cukup untuk file besar.
                @set_time_limit(300);

                $importResult = pkl_student_import_commit(
                    $pdo,
                    $importPreview,
                    (int)$admin_school_id,
                    $studentProfileColumns,
                    pkl_student_import_resolve_companies($pdo, (int)$admin_school_id)
                );

                unset($_SESSION['student_import_preview']);
                $importPreview = null;
                $_SESSION['student_import_result'] = $importResult;

                // Redirect agar refresh halaman tidak mengirim ulang form konfirmasi.
                header('Location: admin.php?tab=section-murid&import=success');
                exit;
            } catch (Throwable $e) {
                $alertMessage = 'Import dibatalkan: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $alertType = 'error';
            }
        }
    }

    // ------------------------------------------
    // IMPORT DATA MURID (EXCEL) - BATALKAN PREVIEW
    // ------------------------------------------
    if ($action === 'import_cancel') {
        unset($_SESSION['student_import_preview']);
        $importPreview = null;
        $activeTab = 'section-murid';
        $alertMessage = 'Preview import dibatalkan. Tidak ada data yang disimpan.';
        $alertType = 'success';
    }

    // ------------------------------------------
    // A. TAMBAH AKUN GURU
    // ------------------------------------------
    // ------------------------------------------
    // A. TAMBAH AKUN GURU / PEMBIMBING INDUSTRI / PENGUJI
    // ------------------------------------------
    if ($action === 'add_guru') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['guru', 'guru_industri', 'penguji']) ? $_POST['role'] : 'guru';

        $roleLabels = [
            'guru'          => 'Guru Pembimbing Sekolah',
            'guru_industri' => 'Guru Pembimbing Industri',
            'penguji'       => 'Penguji Sidang PKL'
        ];
        $roleLabel = $roleLabels[$role] ?? 'Guru';

        if ($username === '' || $email === '' || $password === '') {
            $alertMessage = 'Semua kolom pendaftaran wajib diisi.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $alertMessage = 'Format email tidak valid. Silakan gunakan format email yang benar.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (strlen($username) < 3) {
            $alertMessage = 'Username minimal terdiri dari 3 karakter.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (strlen($username) > 50) {
            $alertMessage = 'Username maksimal 50 karakter.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (strlen($email) > 100) {
            $alertMessage = 'Email maksimal 100 karakter.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (strlen($password) < 6) {
            $alertMessage = 'Password minimal terdiri dari 6 karakter.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } else {
            try {
                // Cek keunikan username
                $stmtCheckUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:u)");
                $stmtCheckUser->execute([':u' => $username]);
                if ($stmtCheckUser->fetchColumn() > 0) {
                    $alertMessage = 'Username "' . htmlspecialchars($username) . '" sudah digunakan oleh akun lain.';
                    $alertType = 'error';
                    $activeTab = 'section-guru';
                } else {
                    // Cek keunikan email
                    $stmtCheckEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:e)");
                    $stmtCheckEmail->execute([':e' => $email]);
                    if ($stmtCheckEmail->fetchColumn() > 0) {
                        $alertMessage = 'Email "' . htmlspecialchars($email) . '" sudah terdaftar pada sistem.';
                        $alertType = 'error';
                        $activeTab = 'section-guru';
                    } else {
                        // Password Hash aman
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO users (username, email, password, role, school_id, created_at)
                            VALUES (:username, :email, :password, :role, :school_id, NOW())
                        ");
                        $stmtInsert->execute([
                            ':username'  => $username,
                            ':email'     => $email,
                            ':password'  => $hashedPassword,
                            ':role'      => $role,
                            ':school_id' => $admin_school_id
                        ]);

                        $alertMessage = 'Akun ' . htmlspecialchars($roleLabel) . ' <strong>' . htmlspecialchars($username) . '</strong> berhasil ditambahkan! Password telah dienkripsi dengan aman. ✓';
                        $alertType = 'success';
                        $activeTab = 'section-guru';
                    }
                }
            } catch (Exception $e) {
                $alertMessage = 'Gagal menambahkan akun: ' . $e->getMessage();
                $alertType = 'error';
                $activeTab = 'section-guru';
            }
        }
    }

    // ------------------------------------------
    // B. EDIT DATA GURU / PEMBIMBING / PENGUJI
    // ------------------------------------------
    if ($action === 'edit_guru') {
        $guru_id  = (int)($_POST['guru_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['guru', 'guru_industri', 'penguji']) ? $_POST['role'] : null;

        if ($guru_id <= 0 || $username === '' || $email === '') {
            $alertMessage = 'Data akun tidak lengkap.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $alertMessage = 'Format email tidak valid.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (strlen($username) > 50) {
            $alertMessage = 'Username maksimal 50 karakter.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } elseif (strlen($email) > 100) {
            $alertMessage = 'Email maksimal 100 karakter.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } else {
            try {
                // Verifikasi keberadaan staff di sekolah
                $stmtCheck = $pdo->prepare("SELECT id, role FROM users WHERE id = :id AND role IN ('guru', 'guru_industri', 'penguji') AND school_id = :sid");
                $stmtCheck->execute([':id' => $guru_id, ':sid' => $admin_school_id]);
                $currentUserStaff = $stmtCheck->fetch();

                if (!$currentUserStaff) {
                    $alertMessage = 'Akun staff tidak ditemukan atau bukan milik sekolah ini.';
                    $alertType = 'error';
                    $activeTab = 'section-guru';
                } else {
                    // Cek username unik kecuali milik user ini
                    $stmtU = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:u) AND id != :id");
                    $stmtU->execute([':u' => $username, ':id' => $guru_id]);
                    if ($stmtU->fetchColumn() > 0) {
                        $alertMessage = 'Username "' . htmlspecialchars($username) . '" sudah dipakai akun lain.';
                        $alertType = 'error';
                        $activeTab = 'section-guru';
                    } else {
                        // Cek email unik kecuali milik user ini
                        $stmtE = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:e) AND id != :id");
                        $stmtE->execute([':e' => $email, ':id' => $guru_id]);
                        if ($stmtE->fetchColumn() > 0) {
                            $alertMessage = 'Email "' . htmlspecialchars($email) . '" sudah dipakai akun lain.';
                            $alertType = 'error';
                            $activeTab = 'section-guru';
                        } else {
                            $finalRole = $role ?? $currentUserStaff['role'];
                            $stmtUp = $pdo->prepare("UPDATE users SET username = :u, email = :e, role = :r WHERE id = :id AND school_id = :sid");
                            $stmtUp->execute([
                                ':u'   => $username,
                                ':e'   => $email,
                                ':r'   => $finalRole,
                                ':id'  => $guru_id,
                                ':sid' => $admin_school_id
                            ]);
                            $alertMessage = 'Data profil <strong>' . htmlspecialchars($username) . '</strong> berhasil diperbarui! ✓';
                            $alertType = 'success';
                            $activeTab = 'section-guru';
                        }
                    }
                }
            } catch (Exception $e) {
                $alertMessage = 'Gagal memperbarui data akun: ' . $e->getMessage();
                $alertType = 'error';
                $activeTab = 'section-guru';
            }
        }
    }

    // ------------------------------------------
    // C. RESET PASSWORD GURU / PEMBIMBING / PENGUJI
    // ------------------------------------------
    if ($action === 'reset_password_guru') {
        $guru_id      = (int)($_POST['guru_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if ($guru_id <= 0 || strlen($new_password) < 6) {
            $alertMessage = 'Password baru minimal harus 6 karakter.';
            $alertType = 'error';
            $activeTab = 'section-guru';
        } else {
            try {
                $stmtCheck = $pdo->prepare("SELECT username FROM users WHERE id = :id AND role IN ('guru', 'guru_industri', 'penguji') AND school_id = :sid");
                $stmtCheck->execute([':id' => $guru_id, ':sid' => $admin_school_id]);
                $guruData = $stmtCheck->fetch();

                if (!$guruData) {
                    $alertMessage = 'Akun staff tidak ditemukan.';
                    $alertType = 'error';
                    $activeTab = 'section-guru';
                } else {
                    $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmtReset = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id AND school_id = :sid");
                    $stmtReset->execute([
                        ':p'   => $newHash,
                        ':id'  => $guru_id,
                        ':sid' => $admin_school_id
                    ]);
                    $alertMessage = 'Password untuk <strong>' . htmlspecialchars($guruData['username']) . '</strong> berhasil direset dan dienkripsi ulang! ✓';
                    $alertType = 'success';
                    $activeTab = 'section-guru';
                }
            } catch (Exception $e) {
                $alertMessage = 'Gagal mereset password: ' . $e->getMessage();
                $alertType = 'error';
                $activeTab = 'section-guru';
            }
        }
    }

    // ------------------------------------------
    // D. HAPUS AKUN STAFF (DENGAN PROTEKSI RELASI)
    // ------------------------------------------
    if ($action === 'delete_guru') {
        $guru_id = (int)($_POST['guru_id'] ?? 0);

        try {
            $stmtCheck = $pdo->prepare("SELECT username, role FROM users WHERE id = :id AND role IN ('guru', 'guru_industri', 'penguji') AND school_id = :sid");
            $stmtCheck->execute([':id' => $guru_id, ':sid' => $admin_school_id]);
            $guruData = $stmtCheck->fetch();

            if (!$guruData) {
                $alertMessage = 'Akun staff tidak ditemukan.';
                $alertType = 'error';
                $activeTab = 'section-guru';
            } else {
                // Cek relasi murid bimbingan / penguji di internships.
                // Catatan: pakai placeholder posisional (?) karena PDO::ATTR_EMULATE_PREPARES=false
                // melarang pemakaian ulang named placeholder (:id dipakai 3x) -> SQLSTATE[HY093].
                $stmtCountM = $pdo->prepare("SELECT COUNT(*) FROM internships WHERE guru_id = ? OR industri_id = ? OR penguji_id = ?");
                $stmtCountM->execute([$guru_id, $guru_id, $guru_id]);
                $countBimbingan = (int)$stmtCountM->fetchColumn();

                // Cek relasi penilaian PKL
                $stmtCountA1 = $pdo->prepare("SELECT COUNT(*) FROM pkl_assessments WHERE guru_id = :id");
                $stmtCountA1->execute([':id' => $guru_id]);
                $stmtCountA2 = $pdo->prepare("SELECT COUNT(*) FROM industry_assessments WHERE industri_id = :id");
                $stmtCountA2->execute([':id' => $guru_id]);
                $stmtCountA3 = $pdo->prepare("SELECT COUNT(*) FROM sidang_assessments WHERE penguji_id = :id");
                $stmtCountA3->execute([':id' => $guru_id]);
                $countAssessments = (int)$stmtCountA1->fetchColumn() + (int)$stmtCountA2->fetchColumn() + (int)$stmtCountA3->fetchColumn();

                if ($countBimbingan > 0 || $countAssessments > 0) {
                    // Nonaktifkan akun: pertahankan akun & seluruh data/riwayat, jangan hapus permanen.
                    $alertMessage = 'Akun <strong>' . htmlspecialchars($guruData['username']) . '</strong> <strong>dinonaktifkan</strong> (tidak dihapus permanen) karena masih memiliki ' . $countBimbingan . ' penugasan aktif dan/atau ' . $countAssessments . ' riwayat penilaian PKL. Seluruh data & riwayat bimbingan dipertahankan. Untuk menghapus permanen, alihkan/hapus penugasan murid terlebih dahulu.';
                    $alertType = 'success';
                    $activeTab = 'section-guru';
                } else {
                    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id AND school_id = :sid");
                    $stmtDel->execute([':id' => $guru_id, ':sid' => $admin_school_id]);
                    $alertMessage = 'Akun <strong>' . htmlspecialchars($guruData['username']) . '</strong> berhasil dihapus permanen. &#10003;';
                    $alertType = 'success';
                    $activeTab = 'section-guru';
                }
            }
        } catch (Exception $e) {
            $alertMessage = 'Gagal menghapus akun: ' . $e->getMessage();
            $alertType = 'error';
            $activeTab = 'section-guru';
        }
    }

    // ------------------------------------------
    // D2. HAPUS / NONAKTIFKAN AKUN MURID
    // ------------------------------------------
    if ($action === 'delete_murid') {
        $murid_id  = (int)($_POST['murid_id'] ?? 0);
        $activeTab = 'section-murid';
        try {
            $stmtCheck = $pdo->prepare("SELECT username FROM users WHERE id = :id AND role = 'murid' AND school_id = :sid");
            $stmtCheck->execute([':id' => $murid_id, ':sid' => $admin_school_id]);
            $muridData = $stmtCheck->fetch();

            if (!$muridData) {
                $alertMessage = 'Akun murid tidak ditemukan atau bukan milik sekolah ini.';
                $alertType = 'error';
            } else {
                // Cek seluruh riwayat & relasi penting: jurnal, laporan, penilaian, penugasan.
                // (nama tabel/kolom di-hardcode -> aman dari injection)
                $relationChecks = [
                    'pkl_journals'         => 'student_id',
                    'pkl_reports'          => 'student_id',
                    'pkl_assessments'      => 'student_id',
                    'industry_assessments' => 'student_id',
                    'sidang_assessments'   => 'student_id',
                    'internships'          => 'student_id',
                ];
                $totalRelations = 0;
                foreach ($relationChecks as $tbl => $col) {
                    $st = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = :id");
                    $st->execute([':id' => $murid_id]);
                    $totalRelations += (int)$st->fetchColumn();
                }

                if ($totalRelations > 0) {
                    // Punya riwayat/relasi -> nonaktifkan (pertahankan akun & seluruh data, jangan hapus).
                    $alertMessage = 'Akun murid <strong>' . htmlspecialchars($muridData['username']) . '</strong> <strong>dinonaktifkan</strong> (tidak dihapus permanen) karena masih memiliki riwayat jurnal/laporan/penilaian/penugasan. Seluruh data & riwayat dipertahankan agar tidak hilang.';
                    $alertType = 'success';
                } else {
                    // Tidak ada riwayat -> hapus permanen (cascade tidak menghapus data berharga).
                    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id AND school_id = :sid");
                    $stmtDel->execute([':id' => $murid_id, ':sid' => $admin_school_id]);
                    $alertMessage = 'Akun murid <strong>' . htmlspecialchars($muridData['username']) . '</strong> berhasil dihapus permanen (tidak memiliki riwayat). &#10003;';
                    $alertType = 'success';
                }
            }
        } catch (Exception $e) {
            $alertMessage = 'Gagal mengelola akun murid: ' . $e->getMessage();
            $alertType = 'error';
        }
    }

    // ------------------------------------------
    // E. ATUR / GANTI GURU PEMBIMBING, INDUSTRI & PENGUJI (FITUR UTAMA)
    // ------------------------------------------
    if ($action === 'assign_guru') {
        $student_id  = (int)($_POST['student_id'] ?? 0);
        $guru_id     = !empty($_POST['guru_id']) ? (int)$_POST['guru_id'] : null;
        $industri_id = !empty($_POST['industri_id']) ? (int)$_POST['industri_id'] : null;
        $penguji_id  = !empty($_POST['penguji_id']) ? (int)$_POST['penguji_id'] : null;
        $company_id  = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;

        try {
            // Verifikasi murid valid (role murid)
            $stmtStudent = $pdo->prepare("SELECT id, username FROM users WHERE id = :id AND role = 'murid' AND (school_id = :sid OR school_id IS NULL OR school_id = 0)");
            $stmtStudent->execute([':id' => $student_id, ':sid' => $admin_school_id]);
            $studentData = $stmtStudent->fetch();

            if (!$studentData) {
                $alertMessage = 'Murid tidak ditemukan atau bukan milik sekolah ini.';
                $alertType = 'error';
                $activeTab = !empty($_POST['current_tab']) ? trim($_POST['current_tab']) : 'section-pembimbing';
            } else {
                // Verifikasi Pembimbing Sekolah jika dipilih
                if ($guru_id !== null && $guru_id > 0) {
                    $stmtG = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'guru' AND (school_id = :sid OR school_id IS NULL OR school_id = 0)");
                    $stmtG->execute([':id' => $guru_id, ':sid' => $admin_school_id]);
                    if (!$stmtG->fetch()) {
                        $guru_id = null;
                    }
                }

                // Verifikasi Pembimbing Industri jika dipilih (role harus guru_industri)
                if ($industri_id !== null && $industri_id > 0) {
                    $stmtI = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'guru_industri' AND (school_id = :sid OR school_id IS NULL OR school_id = 0)");
                    $stmtI->execute([':id' => $industri_id, ':sid' => $admin_school_id]);
                    if (!$stmtI->fetch()) {
                        $industri_id = null;
                    }
                }

                // Verifikasi Penguji jika dipilih (role harus penguji)
                if ($penguji_id !== null && $penguji_id > 0) {
                    $stmtP = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'penguji' AND (school_id = :sid OR school_id IS NULL OR school_id = 0)");
                    $stmtP->execute([':id' => $penguji_id, ':sid' => $admin_school_id]);
                    if (!$stmtP->fetch()) {
                        $penguji_id = null;
                    }
                }

                // Update / Insert ke tabel internships
                $stmtCheckInt = $pdo->prepare("SELECT id, company_id, guru_id, industri_id, penguji_id FROM internships WHERE student_id = :sid");
                $stmtCheckInt->execute([':sid' => $student_id]);
                $existingInt = $stmtCheckInt->fetch();

                if ($existingInt) {
                    // Pertahankan nilai jika tidak dispesifikasi dalam form inline
                    $finalCompanyId  = isset($_POST['company_id']) ? $company_id : $existingInt['company_id'];
                    $finalIndustriId = isset($_POST['industri_id']) ? $industri_id : $existingInt['industri_id'];
                    $finalPengujiId  = isset($_POST['penguji_id']) ? $penguji_id : $existingInt['penguji_id'];

                    $stmtUpdateInt = $pdo->prepare("
                        UPDATE internships 
                        SET guru_id = :gid, industri_id = :iid, penguji_id = :pid, company_id = :cid, school_id = :sid 
                        WHERE student_id = :student_id
                    ");
                    $stmtUpdateInt->execute([
                        ':gid'        => $guru_id,
                        ':iid'        => $finalIndustriId,
                        ':pid'        => $finalPengujiId,
                        ':cid'        => $finalCompanyId,
                        ':sid'        => $admin_school_id,
                        ':student_id' => $student_id
                    ]);
                } else {
                    $stmtInsertInt = $pdo->prepare("
                        INSERT INTO internships (student_id, guru_id, industri_id, penguji_id, company_id, school_id, created_at)
                        VALUES (:student_id, :gid, :iid, :pid, :cid, :sid, NOW())
                    ");
                    $stmtInsertInt->execute([
                        ':student_id' => $student_id,
                        ':gid'        => $guru_id,
                        ':iid'        => $industri_id,
                        ':pid'        => $penguji_id,
                        ':cid'        => $company_id,
                        ':sid'        => $admin_school_id
                    ]);
                }

                $alertMessage = 'Penugasan Pembimbing Sekolah, Pembimbing Industri, Penguji Sidang & Tempat PKL untuk murid <strong>' . htmlspecialchars($studentData['username']) . '</strong> berhasil disimpan! Hak akses otomatis diperbarui. ✓';
                $alertType = 'success';
                $activeTab = !empty($_POST['current_tab']) ? trim($_POST['current_tab']) : 'section-pembimbing';
            }
        } catch (Exception $e) {
            $alertMessage = 'Gagal menetapkan penugasan pembimbing: ' . $e->getMessage();
            $alertType = 'error';
            $activeTab = !empty($_POST['current_tab']) ? trim($_POST['current_tab']) : 'section-pembimbing';
        }
    }

    // ------------------------------------------
    // E2. PENUGASAN PEMBIMBING MASSAL (BULK)
    // ------------------------------------------
    if ($action === 'assign_guru_bulk') {
        $activeTab = 'section-pembimbing';
        $bulk_guru_id     = !empty($_POST['bulk_guru_id']) ? (int)$_POST['bulk_guru_id'] : 0;
        $bulk_industri_id = !empty($_POST['bulk_industri_id']) ? (int)$_POST['bulk_industri_id'] : 0;
        $bulk_students    = $_POST['bulk_students'] ?? [];
        if (!is_array($bulk_students)) { $bulk_students = []; }
        $bulk_students = array_values(array_unique(array_filter(array_map('intval', $bulk_students), fn($v) => $v > 0)));
        $confirm_overwrite = !empty($_POST['confirm_overwrite']);

        if ($bulk_guru_id === 0 && $bulk_industri_id === 0) {
            $alertMessage = 'Pilih minimal satu pembimbing (Sekolah atau Industri) untuk penugasan massal.';
            $alertType = 'error';
        } elseif (empty($bulk_students)) {
            $alertMessage = 'Pilih minimal satu siswa yang akan ditugaskan.';
            $alertType = 'error';
        } else {
            // Validasi & ambil nama pembimbing terpilih sesuai role + sekolah
            $guruName = ''; $industriName = ''; $valid = true;
            if ($bulk_guru_id > 0) {
                $stmtVg = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'guru' AND (school_id = ? OR school_id IS NULL OR school_id = 0)");
                $stmtVg->execute([$bulk_guru_id, $admin_school_id]);
                $g = $stmtVg->fetch();
                if (!$g) { $valid = false; } else { $guruName = $g['username']; }
            }
            if ($bulk_industri_id > 0) {
                $stmtVi = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'guru_industri' AND (school_id = ? OR school_id IS NULL OR school_id = 0)");
                $stmtVi->execute([$bulk_industri_id, $admin_school_id]);
                $ind = $stmtVi->fetch();
                if (!$ind) { $valid = false; } else { $industriName = $ind['username']; }
            }

            if (!$valid) {
                $alertMessage = 'Pembimbing yang dipilih tidak valid atau bukan milik sekolah ini.';
                $alertType = 'error';
            } else {
                // Ambil murid terpilih + penugasan saat ini (validasi role murid & sekolah)
                $ph = implode(',', array_fill(0, count($bulk_students), '?'));
                $stmtStu = $pdo->prepare("
                    SELECT u.id, u.username,
                           i.id AS int_id,
                           i.guru_id, gi.username AS guru_name,
                           i.industri_id, ind.username AS industri_name,
                           i.penguji_id, i.company_id
                    FROM users u
                    LEFT JOIN internships i ON u.id = i.student_id
                    LEFT JOIN users gi ON i.guru_id = gi.id AND gi.role = 'guru'
                    LEFT JOIN users ind ON i.industri_id = ind.id AND ind.role = 'guru_industri'
                    WHERE u.role = 'murid' AND (u.school_id = ? OR u.school_id IS NULL OR u.school_id = 0)
                      AND u.id IN ($ph)
                ");
                $stmtStu->execute(array_merge([$admin_school_id], $bulk_students));
                $rows = $stmtStu->fetchAll();

                if (empty($rows)) {
                    $alertMessage = 'Tidak ada siswa valid yang dipilih untuk penugasan.';
                    $alertType = 'error';
                } else {
                    // Klasifikasi: NEW (tidak menimpa) vs OVERWRITE (menimpa penugasan aktif)
                    $newIds = []; $overwriteIds = []; $stuMap = [];
                    foreach ($rows as $r) {
                        $sid = (int)$r['id'];
                        $stuMap[$sid] = $r;
                        $isOverwrite = false;
                        if ($bulk_guru_id > 0 && $r['guru_id'] !== null && (int)$r['guru_id'] !== $bulk_guru_id) $isOverwrite = true;
                        if ($bulk_industri_id > 0 && $r['industri_id'] !== null && (int)$r['industri_id'] !== $bulk_industri_id) $isOverwrite = true;
                        if ($isOverwrite) { $overwriteIds[] = $sid; } else { $newIds[] = $sid; }
                    }
                    $allIds = array_merge($newIds, $overwriteIds);
                    $invalidCount = count($bulk_students) - count($rows);

                    if (!$confirm_overwrite) {
                        // FASE 1: PREVIEW / KONFIRMASI (belum menyimpan)
                        $bulkPreview = [
                            'guru_id'        => $bulk_guru_id,
                            'industri_id'    => $bulk_industri_id,
                            'guru_name'      => $guruName,
                            'industri_name'  => $industriName,
                            'new_ids'        => $newIds,
                            'overwrite_ids'  => $overwriteIds,
                            'all_ids'        => $allIds,
                            'stu_map'        => $stuMap,
                            'invalid_count'  => $invalidCount,
                        ];
                    } else {
                        // FASE 2: SIMPAN DALAM TRANSAKSI (tidak setengah berhasil)
                        try {
                            $pdo->beginTransaction();
                            $stmtUpd = $pdo->prepare("UPDATE internships SET guru_id = ?, industri_id = ?, penguji_id = ?, company_id = ?, school_id = ? WHERE student_id = ?");
                            $stmtIns = $pdo->prepare("INSERT INTO internships (student_id, guru_id, industri_id, penguji_id, company_id, school_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                            foreach ($stuMap as $sid => $r) {
                                $finalGuru     = $bulk_guru_id > 0 ? $bulk_guru_id : $r['guru_id'];
                                $finalIndustri = $bulk_industri_id > 0 ? $bulk_industri_id : $r['industri_id'];
                                $keepPenguji   = $r['penguji_id'];   // pertahankan, jangan diubah massal
                                $keepCompany   = $r['company_id'];   // pertahankan
                                if (!empty($r['int_id'])) {
                                    $stmtUpd->execute([$finalGuru, $finalIndustri, $keepPenguji, $keepCompany, $admin_school_id, $sid]);
                                } else {
                                    $stmtIns->execute([$sid, $finalGuru, $finalIndustri, $keepPenguji, $keepCompany, $admin_school_id]);
                                }
                            }
                            $pdo->commit();
                            $saved = count($stuMap);
                            $detail = [];
                            if ($bulk_guru_id > 0) { $detail[] = 'Pembimbing Sekolah: <strong>' . htmlspecialchars($guruName) . '</strong>'; }
                            if ($bulk_industri_id > 0) { $detail[] = 'Pembimbing Industri: <strong>' . htmlspecialchars($industriName) . '</strong>'; }
                            $alertMessage = 'Penugasan massal berhasil disimpan! <strong>' . $saved . ' siswa</strong> diperbarui (' . implode(' &bull; ', $detail) . '). Riwayat bimbingan & penilaian tidak berubah. &#10003;';
                            $alertType = 'success';
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) { $pdo->rollBack(); }
                            $alertMessage = 'Gagal menyimpan penugasan massal: ' . $e->getMessage();
                            $alertType = 'error';
                        }
                    }
                }
            }
        }
    }

    // ------------------------------------------
    // E3. PENUGASAN PENGUJI MASSAL (PRODUKTIF / JURNAL)
    // ------------------------------------------
    if ($action === 'assign_penguji_bulk') {
        $activeTab = 'section-penguji';
        $ex_penguji_id = !empty($_POST['bulk_penguji_id']) ? (int)$_POST['bulk_penguji_id'] : 0;
        $ex_category   = trim($_POST['bulk_category'] ?? '');
        $ex_students   = $_POST['bulk_students'] ?? [];
        if (!is_array($ex_students)) { $ex_students = []; }
        $ex_students = array_values(array_unique(array_filter(array_map('intval', $ex_students), fn($v) => $v > 0)));
        $ex_confirm    = !empty($_POST['confirm_overwrite']);
        $ex_category   = in_array($ex_category, ['produktif','jurnal'], true) ? $ex_category : '';

        if ($ex_penguji_id === 0 || $ex_category === '') {
            $alertMessage = 'Pilih penguji dan kategori (Produktif/Jurnal) untuk penugasan massal.';
            $alertType = 'error';
        } elseif (empty($ex_students)) {
            $alertMessage = 'Pilih minimal satu siswa yang akan ditugaskan.';
            $alertType = 'error';
        } else {
            // Validasi penguji (role penguji + sekolah)
            $stmtVp = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'penguji' AND (school_id = ? OR school_id IS NULL OR school_id = 0)");
            $stmtVp->execute([$ex_penguji_id, $admin_school_id]);
            $pg = $stmtVp->fetch();
            if (!$pg) {
                $alertMessage = 'Penguji tidak ditemukan atau bukan milik sekolah ini.';
                $alertType = 'error';
            } else {
                $pengujiName = $pg['username'];
                // Ambil siswa terpilih + penugasan saat ini untuk kategori ini
                $ph = implode(',', array_fill(0, count($ex_students), '?'));
                $stmtStu = $pdo->prepare("
                    SELECT u.id, u.username,
                           ea.penguji_id AS cur_penguji_id, pu.username AS cur_penguji_name
                    FROM users u
                    LEFT JOIN examiner_assignments ea ON ea.student_id = u.id AND ea.category = ?
                    LEFT JOIN users pu ON ea.penguji_id = pu.id
                    WHERE u.role = 'murid' AND (u.school_id = ? OR u.school_id IS NULL OR u.school_id = 0)
                      AND u.id IN ($ph)
                ");
                $stmtStu->execute(array_merge([$ex_category, $admin_school_id], $ex_students));
                $rows = $stmtStu->fetchAll();

                if (empty($rows)) {
                    $alertMessage = 'Tidak ada siswa valid yang dipilih.';
                    $alertType = 'error';
                } else {
                    $newIds = []; $overwriteIds = []; $stuMap = [];
                    foreach ($rows as $r) {
                        $sid = (int)$r['id'];
                        $stuMap[$sid] = $r;
                        $isOverwrite = ($r['cur_penguji_id'] !== null && (int)$r['cur_penguji_id'] !== $ex_penguji_id);
                        if ($isOverwrite) { $overwriteIds[] = $sid; } else { $newIds[] = $sid; }
                    }
                    $allIds = array_merge($newIds, $overwriteIds);

                    if (!$ex_confirm) {
                        // FASE 1: PREVIEW (belum menyimpan)
                        $examinerPreview = [
                            'penguji_id'   => $ex_penguji_id,
                            'category'     => $ex_category,
                            'penguji_name' => $pengujiName,
                            'new_ids'      => $newIds,
                            'overwrite_ids'=> $overwriteIds,
                            'all_ids'      => $allIds,
                            'stu_map'      => $stuMap,
                        ];
                    } else {
                        // FASE 2: SIMPAN DALAM TRANSAKSI
                        try {
                            $pdo->beginTransaction();
                            $stmtUp = $pdo->prepare("INSERT INTO examiner_assignments (student_id, penguji_id, category, school_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE penguji_id = VALUES(penguji_id), school_id = VALUES(school_id)");
                            foreach ($stuMap as $sid => $r) {
                                $stmtUp->execute([$sid, $ex_penguji_id, $ex_category, $admin_school_id]);
                            }
                            $pdo->commit();
                            $catLabel = $ex_category === 'produktif' ? 'Produktif' : 'Jurnal';
                            $alertMessage = 'Penugasan Penguji ' . $catLabel . ' <strong>' . htmlspecialchars($pengujiName) . '</strong> berhasil disimpan untuk <strong>' . count($stuMap) . ' siswa</strong>. &#10003;';
                            $alertType = 'success';
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) { $pdo->rollBack(); }
                            $alertMessage = 'Gagal menyimpan penugasan penguji: ' . $e->getMessage();
                            $alertType = 'error';
                        }
                    }
                }
            }
        }
    }

    // ------------------------------------------
    // F. TAMBAH PERUSAHAAN PKL
    // ------------------------------------------
    if ($action === 'add_company') {
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');

        if ($name === '') {
            $alertMessage = 'Nama perusahaan / instansi PKL wajib diisi.';
            $alertType = 'error';
            $activeTab = 'section-perusahaan';
        } else {
            try {
                $stmtAddC = $pdo->prepare("
                    INSERT INTO companies (school_id, name, address, phone, created_at)
                    VALUES (:sid, :name, :address, :phone, NOW())
                ");
                $stmtAddC->execute([
                    ':sid'     => $admin_school_id,
                    ':name'    => $name,
                    ':address' => $address,
                    ':phone'   => $phone
                ]);
                $alertMessage = 'Perusahaan mitra <strong>' . htmlspecialchars($name) . '</strong> berhasil ditambahkan! ✓';
                $alertType = 'success';
                $activeTab = 'section-perusahaan';
            } catch (Exception $e) {
                $alertMessage = 'Gagal menambahkan perusahaan: ' . $e->getMessage();
                $alertType = 'error';
                $activeTab = 'section-perusahaan';
            }
        }
    }

    // ------------------------------------------
    // G. EDIT PERUSAHAAN PKL
    // ------------------------------------------
    if ($action === 'edit_company') {
        $company_id = (int)($_POST['company_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');

        if ($company_id <= 0 || $name === '') {
            $alertMessage = 'Nama perusahaan wajib diisi.';
            $alertType = 'error';
            $activeTab = 'section-perusahaan';
        } else {
            try {
                $stmtUpC = $pdo->prepare("
                    UPDATE companies 
                    SET name = :name, address = :address, phone = :phone 
                    WHERE id = :id AND school_id = :sid
                ");
                $stmtUpC->execute([
                    ':name'    => $name,
                    ':address' => $address,
                    ':phone'   => $phone,
                    ':id'      => $company_id,
                    ':sid'     => $admin_school_id
                ]);
                $alertMessage = 'Data perusahaan <strong>' . htmlspecialchars($name) . '</strong> berhasil diperbarui! ✓';
                $alertType = 'success';
                $activeTab = 'section-perusahaan';
            } catch (Exception $e) {
                $alertMessage = 'Gagal memperbarui data perusahaan: ' . $e->getMessage();
                $alertType = 'error';
                $activeTab = 'section-perusahaan';
            }
        }
    }

    // ------------------------------------------
    // H. HAPUS PERUSAHAAN PKL (PROTEKSI RELASI)
    // ------------------------------------------
    if ($action === 'delete_company') {
        $company_id = (int)($_POST['company_id'] ?? 0);

        try {
            $stmtC = $pdo->prepare("SELECT name FROM companies WHERE id = :id AND school_id = :sid");
            $stmtC->execute([':id' => $company_id, ':sid' => $admin_school_id]);
            $compData = $stmtC->fetch();

            if (!$compData) {
                $alertMessage = 'Perusahaan tidak ditemukan.';
                $alertType = 'error';
                $activeTab = 'section-perusahaan';
            } else {
                // Cek relasi murid yang sedang PKL di perusahaan ini
                $stmtCheckUse = $pdo->prepare("SELECT COUNT(*) FROM internships WHERE company_id = :cid");
                $stmtCheckUse->execute([':cid' => $company_id]);
                $usedCount = (int)$stmtCheckUse->fetchColumn();

                if ($usedCount > 0) {
                    $alertMessage = 'Perusahaan <strong>' . htmlspecialchars($compData['name']) . '</strong> tidak dapat dihapus karena sedang menjadi lokasi penempatan aktif bagi ' . $usedCount . ' murid PKL.';
                    $alertType = 'error';
                    $activeTab = 'section-perusahaan';
                } else {
                    $stmtDelC = $pdo->prepare("DELETE FROM companies WHERE id = :id AND school_id = :sid");
                    $stmtDelC->execute([':id' => $company_id, ':sid' => $admin_school_id]);
                    $alertMessage = 'Perusahaan <strong>' . htmlspecialchars($compData['name']) . '</strong> berhasil dihapus. ✓';
                    $alertType = 'success';
                    $activeTab = 'section-perusahaan';
                }
            }
        } catch (Exception $e) {
            $alertMessage = 'Gagal menghapus perusahaan: ' . $e->getMessage();
            $alertType = 'error';
            $activeTab = 'section-perusahaan';
        }
    }
}

// ==========================================
// 2. QUERY DATA DAN STATISTIK (DARI DATABASE)
// ==========================================

// A. Statistik Dashboard (100% Real Database Queries)
$stmtTotalMurid = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'murid' AND school_id = :sid");
$stmtTotalMurid->execute([':sid' => $admin_school_id]);
$totalMurid = (int)$stmtTotalMurid->fetchColumn();

$stmtTotalGuru = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'guru' AND school_id = :sid");
$stmtTotalGuru->execute([':sid' => $admin_school_id]);
$totalGuru = (int)$stmtTotalGuru->fetchColumn();

$stmtTotalIndustri = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'guru_industri' AND (school_id = :sid OR school_id IS NULL OR school_id = 0)");
$stmtTotalIndustri->execute([':sid' => $admin_school_id]);
$totalIndustri = (int)$stmtTotalIndustri->fetchColumn();

$stmtTotalPenguji = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'penguji' AND (school_id = :sid OR school_id IS NULL OR school_id = 0)");
$stmtTotalPenguji->execute([':sid' => $admin_school_id]);
$totalPenguji = (int)$stmtTotalPenguji->fetchColumn();

$stmtMuridWithGuru = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id) 
    FROM users u 
    JOIN internships i ON u.id = i.student_id 
    WHERE u.role = 'murid' AND u.school_id = :sid AND i.guru_id IS NOT NULL
");
$stmtMuridWithGuru->execute([':sid' => $admin_school_id]);
$muridSudahAdaPembimbing = (int)$stmtMuridWithGuru->fetchColumn();

$muridBelumAdaPembimbing = max(0, $totalMurid - $muridSudahAdaPembimbing);

$stmtMuridLengkap = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    JOIN internships i ON u.id = i.student_id
    WHERE u.role = 'murid' AND u.school_id = :sid
      AND i.guru_id IS NOT NULL
      AND i.industri_id IS NOT NULL
      AND i.penguji_id IS NOT NULL
");
$stmtMuridLengkap->execute([':sid' => $admin_school_id]);
$muridSudahLengkap = (int)$stmtMuridLengkap->fetchColumn();
$muridBelumLengkap = max(0, $totalMurid - $muridSudahLengkap);

$stmtTotalCompanies = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE school_id = :sid");
$stmtTotalCompanies->execute([':sid' => $admin_school_id]);
$totalPerusahaan = (int)$stmtTotalCompanies->fetchColumn();

// B. Query Daftar Seluruh Guru, Pembimbing Industri, dan Penguji
$stmtStaffList = $pdo->prepare("
    SELECT g.id, g.username, g.email, g.role, g.created_at,
           COUNT(DISTINCT i.student_id) AS total_bimbingan,
           GROUP_CONCAT(DISTINCT m.username ORDER BY m.username ASC SEPARATOR ', ') AS student_names
    FROM users g
    LEFT JOIN internships i ON (
        (g.role = 'guru' AND g.id = i.guru_id) OR
        (g.role = 'guru_industri' AND g.id = i.industri_id) OR
        (g.role = 'penguji' AND g.id = i.penguji_id)
    )
    LEFT JOIN users m ON i.student_id = m.id AND m.role = 'murid'
    WHERE g.role IN ('guru', 'guru_industri', 'penguji') AND (g.school_id = :sid OR g.school_id IS NULL OR g.school_id = 0)
    GROUP BY g.id
    ORDER BY FIELD(g.role, 'guru', 'guru_industri', 'penguji'), g.username ASC
");
$stmtStaffList->execute([':sid' => $admin_school_id]);
$allStaff = $stmtStaffList->fetchAll();

// Pisahkan array untuk select dropdown sesuai peran masing-masing
$allTeachers = array_values(array_filter($allStaff, fn($u) => $u['role'] === 'guru'));
$allIndustri = array_values(array_filter($allStaff, fn($u) => $u['role'] === 'guru_industri'));
$allPenguji  = array_values(array_filter($allStaff, fn($u) => $u['role'] === 'penguji'));

// C. Query Seluruh Murid Beserta Status Pembimbing Sekolah, Industri, Penguji & Perusahaan
$studentProfileSelect = '';
foreach (['nama', 'kelas', 'jurusan'] as $profileColumn) {
    if (in_array($profileColumn, $studentProfileColumns, true)) {
        $studentProfileSelect .= ", u.`{$profileColumn}`";
    }
}
$stmtMuridList = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at{$studentProfileSelect},
           i.guru_id,
           g.username AS guru_name,
           g.email AS guru_email,
           i.industri_id,
           ind.username AS industri_name,
           ind.email AS industri_email,
           i.penguji_id,
           pj.username AS penguji_name,
           pj.email AS penguji_email,
           i.company_id,
           c.name AS company_name,
           COUNT(DISTINCT j.id) AS total_journals,
           r.status AS report_status,
           a.final_score AS assessment_score,
           ia.final_score AS industry_score,
           sa.final_score AS sidang_score
    FROM users u
    LEFT JOIN internships i ON u.id = i.student_id
    LEFT JOIN users g ON i.guru_id = g.id AND g.role = 'guru'
    LEFT JOIN users ind ON i.industri_id = ind.id AND ind.role = 'guru_industri'
    LEFT JOIN users pj ON i.penguji_id = pj.id AND pj.role = 'penguji'
    LEFT JOIN companies c ON i.company_id = c.id
    LEFT JOIN pkl_journals j ON u.id = j.student_id
    LEFT JOIN pkl_reports r ON u.id = r.student_id
    LEFT JOIN pkl_assessments a ON u.id = a.student_id
    LEFT JOIN industry_assessments ia ON u.id = ia.student_id AND ia.industri_id = i.industri_id
    LEFT JOIN sidang_assessments sa ON u.id = sa.student_id AND sa.penguji_id = i.penguji_id
    WHERE u.role = 'murid' AND u.school_id = :sid
    GROUP BY u.id
    ORDER BY u.id DESC
");
$stmtMuridList->execute([':sid' => $admin_school_id]);
$allStudents = $stmtMuridList->fetchAll();

// D. Query Daftar Perusahaan Beserta Jumlah Murid yang PKL di Sana
$stmtCompList = $pdo->prepare("
    SELECT c.*,
           COUNT(DISTINCT i.student_id) AS total_interns
    FROM companies c
    LEFT JOIN internships i ON c.id = i.company_id
    WHERE c.school_id = :sid
    GROUP BY c.id
    ORDER BY c.name ASC
");
$stmtCompList->execute([':sid' => $admin_school_id]);
$allCompanies = $stmtCompList->fetchAll();

// E. Query Daftar Penugasan & Status Penilaian Penguji (Produktif & Jurnal)
$stmtExaminerList = $pdo->prepare("
    SELECT u.id, u.username, u.nama, u.kelas, u.jurusan,
           ep.penguji_id AS produktif_penguji_id, pk.username AS produktif_penguji,
           ej.penguji_id AS jurnal_penguji_id, jn.username AS jurnal_penguji,
           (SELECT COUNT(*) FROM produktif_assessments pa WHERE pa.student_id = u.id AND pa.penguji_id = ep.penguji_id) AS produktif_done,
           (SELECT COUNT(*) FROM jurnal_assessments ja WHERE ja.student_id = u.id AND ja.penguji_id = ej.penguji_id) AS jurnal_done
    FROM users u
    LEFT JOIN examiner_assignments ep ON ep.student_id = u.id AND ep.category = 'produktif'
    LEFT JOIN users pk ON ep.penguji_id = pk.id
    LEFT JOIN examiner_assignments ej ON ej.student_id = u.id AND ej.category = 'jurnal'
    LEFT JOIN users jn ON ej.penguji_id = jn.id
    WHERE u.role = 'murid' AND u.school_id = :sid
    ORDER BY u.id DESC
");
$stmtExaminerList->execute([':sid' => $admin_school_id]);
$examinerList = $stmtExaminerList->fetchAll();

// Helper Sanitasi Output
function admin_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Administrator - PKL Journey</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../css/style.css">
  
  <style>
    /* Styling pelengkap khusus Panel Admin */
    /* Tab Navigation Visibility */
    .content-tab-view {
      display: none;
    }
    .content-tab-view.active {
      display: block;
    }

    /* Mencegah horizontal scroll di seluruh halaman */
    html, body.dashboard-body {
      overflow-x: hidden;
      max-width: 100vw;
    }
    .dashboard-layout {
      min-height: 100vh;
      width: 100%;
      max-width: 100vw;
      overflow-x: hidden;
      position: relative;
    }
    .dashboard-main {
      flex: 1;
      min-width: 0;
      max-width: 100%;
      overflow-x: hidden;
    }

    .admin-badge {
      padding: 0.35rem 0.85rem;
      background: var(--color-accent-light);
      color: var(--color-primary);
      font-weight: 800;
      font-size: 0.78rem;
      border-radius: var(--radius-full);
      border: 1px solid rgba(2, 132, 199, 0.25);
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }
    .badge-assigned {
      background: #E8F5E9;
      color: var(--color-success);
      border: 1px solid #A5D6A7;
    }
    .badge-unassigned {
      background: #FFFBEB;
      color: #B45309;
      border: 1px solid #FDE68A;
    }
    .btn-action-group {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      flex-wrap: wrap;
    }
    .btn-action-sm {
      padding: 0.38rem 0.75rem;
      font-size: 0.78rem;
      font-weight: 700;
      border-radius: var(--radius-sm);
      border: 1px solid var(--color-border);
      background: var(--color-white);
      color: var(--color-text);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      transition: var(--transition);
      text-decoration: none;
      font-family: inherit;
    }
    .btn-action-sm:hover {
      background: var(--color-slate-100);
      color: var(--color-primary);
      border-color: var(--color-border);
    }
    .btn-action-sm.danger {
      color: var(--color-error);
      border-color: #F8B4B4;
    }
    .btn-action-sm.danger:hover {
      background: #FDE8E8;
      border-color: var(--color-error);
    }
    .btn-action-sm.primary {
      background: var(--color-orange);
      color: var(--color-white);
      border-color: var(--color-dark-orange);
    }
    .btn-action-sm.primary:hover {
      background: var(--color-dark-orange);
    }
    .search-filter-wrap {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1.25rem;
      width: 100%;
    }
    .search-input-box {
      flex: 1;
      min-width: 240px;
      position: relative;
    }
    .search-input-box input {
      width: 100%;
      padding: 0.65rem 1rem 0.65rem 2.4rem;
      font-size: 0.88rem;
      font-family: inherit;
      border: 1.5px solid var(--color-border);
      border-radius: var(--radius-md);
      background: var(--color-white);
      color: var(--color-text);
      outline: none;
      transition: var(--transition);
    }
    .search-input-box input:focus {
      border-color: var(--color-orange);
      box-shadow: 0 0 0 3px var(--color-orange-glow);
    }
    .search-icon-pos {
      position: absolute;
      left: 0.85rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--color-muted);
      font-size: 0.95rem;
      pointer-events: none;
    }
    .stats-row-5 {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1.15rem;
      margin-bottom: 2rem;
    }
    .table-container-responsive {
      width: 100%;
      max-width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      background: var(--color-white);
      border-radius: var(--radius-md);
      border: 1px solid var(--color-border);
    }
    .table-container-responsive table {
      min-width: 640px;
    }
    .student-pills-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.3rem;
      max-width: 320px;
    }
    .student-mini-pill {
      padding: 0.18rem 0.5rem;
      background: var(--color-slate-100);
      color: var(--color-text);
      border-radius: var(--radius-full);
      font-size: 0.74rem;
      font-weight: 600;
    }
    .inline-assign-form {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin: 0;
    }
    .inline-assign-form select {
      padding: 0.45rem 0.75rem;
      font-size: 0.82rem;
      font-family: inherit;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--color-border);
      background: var(--color-white);
      color: var(--color-text);
      min-width: 180px;
    }
    .inline-assign-form select:focus {
      border-color: var(--color-orange);
      outline: none;
    }

    /* Mobile Backdrop for Sidebar */
    .sidebar-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 39, 68, 0.45);
      backdrop-filter: blur(2px);
      z-index: 99;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .sidebar-backdrop.active {
      opacity: 1;
      visibility: visible;
    }

    /* Responsive Breakpoints */
    @media (max-width: 768px) {
      .dashboard-main {
        margin-left: 0;
        padding: 1rem 0.85rem;
        width: 100%;
      }
      .dashboard-topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.85rem;
      }
      .topbar-actions {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
      }
      .stats-row-5 {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.25rem;
      }
      .stat-card {
        padding: 1rem 0.85rem;
      }
      .stat-icon-badge {
        width: 38px;
        height: 38px;
        font-size: 1.15rem;
      }
      .stat-value {
        font-size: 1.1rem;
      }
      .section-panel {
        padding: 1.15rem 0.9rem;
      }
      .section-panel-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
      }
      .modal-box {
        padding: 1.25rem 1rem;
        max-width: 95vw;
      }
      .inline-assign-form {
        flex-direction: column;
        align-items: stretch;
        gap: 0.4rem;
        width: 100%;
      }
      .inline-assign-form select {
        width: 100%;
        min-width: 0;
      }
      .inline-assign-form button {
        width: 100%;
        justify-content: center;
      }
      .search-filter-wrap {
        gap: 0.5rem;
      }
      .search-input-box {
        min-width: 100%;
      }
    }

    /* Import Data Murid dari Excel */
    .import-upload-row {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
      margin: 0;
    }
    .import-upload-row input[type="file"] {
      flex: 1;
      min-width: 240px;
      padding: 0.55rem;
      border: 1.5px dashed var(--color-border);
      border-radius: var(--radius-md);
      background: var(--color-slate-100);
      color: var(--color-text);
      font-size: 0.85rem;
      font-family: inherit;
    }
    .import-summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 0.75rem;
      margin-bottom: 0.9rem;
    }
    .import-summary-card {
      padding: 0.85rem 1rem;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      background: var(--color-white);
    }
    .import-summary-card .label {
      font-size: 0.72rem;
      font-weight: 800;
      color: var(--color-muted);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .import-summary-card .value {
      margin-top: 0.2rem;
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--color-text);
    }
    .import-preview-scroll {
      max-height: 420px;
      overflow: auto;
    }
    .import-preview-scroll table {
      min-width: 860px;
    }
    .import-password {
      display: inline-block;
      padding: 0.2rem 0.45rem;
      border-radius: 4px;
      background: var(--color-slate-100);
      color: var(--color-text);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-weight: 700;
      font-size: 0.82rem;
    }
  </style>
</head>
<body class="dashboard-body">

  <div class="dashboard-layout">
    
    <!-- Sidebar Admin PKL Journey -->
    <aside class="app-sidebar" id="app-sidebar">
      <div class="sidebar-header">
        <a href="admin.php" class="sidebar-brand">
          <span class="brand-logo-icon"><?= pkl_logo(34) ?></span> PKL <span class="brand-text-accent">Journey</span>
        </a>
        <div class="sidebar-user-card">
          <div class="user-avatar-circle" style="background: linear-gradient(135deg, var(--color-orange), var(--color-dark-orange));">
            <?= strtoupper(substr($admin_username, 0, 1)) ?>
          </div>
          <div class="user-meta">
            <div class="user-meta-name" title="<?= admin_h($admin_username) ?>"><?= admin_h($admin_username) ?></div>
            <div class="user-meta-school" title="<?= admin_h($admin_school_name) ?>"><?= admin_h($admin_school_name) ?></div>
          </div>
        </div>
      </div>

      <!-- Menu Sidebar Admin -->
      <nav class="sidebar-menu">
        <button type="button" class="nav-link-item <?= $activeTab === 'section-dashboard' ? 'active' : '' ?>" data-target="section-dashboard" id="nav-dashboard">
          <span class="nav-icon"><?= pkl_icon('dashboard') ?></span>
          <span>Dashboard</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-guru' ? 'active' : '' ?>" data-target="section-guru" id="nav-guru">
          <span class="nav-icon"><?= pkl_icon('guru') ?></span>
          <span>Kelola Guru</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-murid' ? 'active' : '' ?>" data-target="section-murid" id="nav-murid">
          <span class="nav-icon"><?= pkl_icon('users') ?></span>
          <span>Data Murid</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-pembimbing' ? 'active' : '' ?>" data-target="section-pembimbing" id="nav-pembimbing">
          <span class="nav-icon"><?= pkl_icon('pembimbing_sekolah') ?></span>
          <span>Atur Guru Pembimbing</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-perusahaan' ? 'active' : '' ?>" data-target="section-perusahaan" id="nav-perusahaan">
          <span class="nav-icon"><?= pkl_icon('perusahaan') ?></span>
          <span>Data Perusahaan PKL</span>
        </button>
        <button type="button" class="nav-link-item <?= $activeTab === 'section-penguji' ? 'active' : '' ?>" data-target="section-penguji" id="nav-penguji">
          <span class="nav-icon"><?= pkl_icon('penguji') ?></span>
          <span>Penugasan Penguji</span>
        </button>
      </nav>

      <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link-logout">
          <span class="nav-icon"><?= pkl_icon('logout') ?></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Backdrop Overlay Mobile -->
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <!-- Konten Utama Dashboard Admin -->
    <main class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="topbar-title-wrap">
          <h1 class="topbar-greeting">Halo, <span><?= admin_h($admin_username) ?></span>! <?= pkl_icon('settings', '', 20) ?></h1>
          <p class="topbar-sub">Panel Manajemen Administrator • <?= admin_h($admin_school_name) ?></p>
        </div>
        <div class="topbar-actions">
          <button type="button" class="mobile-menu-btn" id="mobile-menu-toggle" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <button type="button" class="btn-primary" onclick="openAddGuruModal()" style="width:auto;margin-top:0;padding:0.65rem 1.25rem;font-size:0.88rem;">
            <span><?= pkl_icon('plus', '', 16) ?></span> Tambah Akun Guru
          </button>
        </div>
      </header>

      <?php if ($alertMessage !== ''): ?>
        <div class="status-box <?= $alertType === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 1.5rem;">
          <?= $alertMessage ?>
        </div>
      <?php endif; ?>

      <!-- ==========================================
           TAB 1: DASHBOARD UTAMA
           ========================================== -->
      <div class="content-tab-view <?= $activeTab === 'section-dashboard' ? 'active' : '' ?>" id="section-dashboard" <?= $activeTab === 'section-dashboard' ? '' : 'hidden' ?>>
        
        <!-- 5 Kartu Statistik Utama (Real DB Values) -->
        <div class="stats-row-5">
          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Total Murid</div>
              <div class="stat-value"><?= (int)$totalMurid ?> Siswa</div>
              <div class="stat-sub">Terdaftar di sekolah</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('users', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Total Guru</div>
              <div class="stat-value"><?= (int)$totalGuru ?> Guru</div>
              <div class="stat-sub">Pembimbing aktif</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('guru', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Sudah Ada Pembimbing</div>
              <div class="stat-value" style="color: var(--color-success);"><?= (int)$muridSudahAdaPembimbing ?> Siswa</div>
              <div class="stat-sub"><?= $totalMurid > 0 ? round(($muridSudahAdaPembimbing / $totalMurid) * 100) : 0 ?>% dari total murid</div>
            </div>
            <div class="stat-icon-badge" style="background:#E8F5E9;color:var(--color-success);"><?= pkl_icon('check', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Belum Ada Pembimbing</div>
              <div class="stat-value" style="color: var(--color-orange);"><?= (int)$muridBelumAdaPembimbing ?> Siswa</div>
              <div class="stat-sub">Perlu penugasan guru</div>
            </div>
            <div class="stat-icon-badge" style="background:var(--color-accent-light);color:var(--color-accent);"><?= pkl_icon('clock', '', 24) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-body">
              <div class="stat-label">Perusahaan PKL</div>
              <div class="stat-value"><?= (int)$totalPerusahaan ?> Mitra</div>
              <div class="stat-sub">Instansi / DUDI</div>
            </div>
            <div class="stat-icon-badge"><?= pkl_icon('perusahaan', '', 24) ?></div>
          </div>
        </div>

        <!-- Ringkasan Pembagian Murid Per Guru -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('guru', '', 20) ?></span> Ringkasan Pembagian Murid per Guru Pembimbing
              <span class="sec-badge"><?= count($allTeachers) ?> Guru</span>
            </div>
            <button type="button" class="btn-action-sm primary" onclick="switchTab('section-pembimbing')">
              <span><?= pkl_icon('pembimbing_sekolah', '', 16) ?></span> Atur Pembimbing Sekarang
            </button>
          </div>

          <?php if (empty($allTeachers)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('guru', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Akun Guru</div>
              <div class="empty-state-desc">Tambahkan akun guru pembimbing untuk memulai penugasan bimbingan murid PKL.</div>
              <button type="button" class="btn-primary" onclick="openAddGuruModal()" style="width:auto;margin:auto;">
                <span><?= pkl_icon('plus', '', 16) ?></span> Tambah Guru Pertama
              </button>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Guru</th>
                    <th>Email</th>
                    <th>Jumlah Bimbingan</th>
                    <th>Daftar Murid yang Dibimbing</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allTeachers as $g): ?>
                    <tr>
                      <td style="font-weight: 700; width: 50px;"><?= $no++ ?></td>
                      <td>
                        <div style="font-weight: 700; color: var(--color-text);"><?= admin_h($g['username']) ?></div>
                        <span class="admin-badge" style="font-size:0.7rem;padding:0.15rem 0.5rem;margin-top:0.2rem;">Guru Pembimbing</span>
                      </td>
                      <td style="color: var(--color-muted);"><?= admin_h($g['email']) ?></td>
                      <td>
                        <span class="status-pill <?= (int)$g['total_bimbingan'] > 0 ? 'disetujui' : 'menunggu' ?>">
                          <?= (int)$g['total_bimbingan'] ?> Murid
                        </span>
                      </td>
                      <td>
                        <?php if (!empty($g['student_names'])): ?>
                          <div class="student-pills-list">
                            <?php foreach (explode(', ', $g['student_names']) as $sName): ?>
                              <span class="student-mini-pill"><?= pkl_icon('user', '', 12) ?> <?= admin_h($sName) ?></span>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <span style="font-size: 0.82rem; color: var(--color-muted); font-style: italic;">Belum ada murid bimbingan</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm" onclick="switchTab('section-pembimbing')">
                          <span><?= pkl_icon('settings', '', 14) ?></span> Kelola Siswa
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Quick Summary Murid Belum Ada Pembimbing -->
        <?php if ($muridBelumAdaPembimbing > 0): ?>
          <div class="section-panel" style="border-left: 4px solid var(--color-orange);">
            <div class="section-panel-header">
              <div class="section-panel-title" style="color: var(--color-dark-orange);">
                <span><?= pkl_icon('alert', '', 20) ?></span> Terdapat <?= $muridBelumAdaPembimbing ?> Murid Belum Memiliki Guru Pembimbing
              </div>
              <button type="button" class="btn-action-sm primary" onclick="switchTab('section-pembimbing')">
                Tugaskan Pembimbing →
              </button>
            </div>
            <p style="font-size: 0.9rem; color: var(--color-muted);">
              Siswa tanpa guru pembimbing tidak dapat diverifikasi jurnal harian dan laporannya oleh guru. Silakan tetapkan pembimbing melalui menu <strong>Atur Guru Pembimbing</strong>.
            </p>
          </div>
        <?php endif; ?>

      </div>

      <!-- ==========================================
           TAB 2: KELOLA GURU
           ========================================== -->
      <div class="content-tab-view <?= $activeTab === 'section-guru' ? 'active' : '' ?>" id="section-guru" <?= $activeTab === 'section-guru' ? '' : 'hidden' ?>>
        
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('users', '', 20) ?></span> Daftar Akun Guru, Pembimbing Industri & Penguji Sidang
              <span class="sec-badge"><?= count($allStaff) ?> Akun</span>
            </div>
            <button type="button" class="btn-primary" onclick="openAddGuruModal()" style="width:auto;margin-top:0;padding:0.65rem 1.25rem;font-size:0.88rem;">
              <span><?= pkl_icon('plus', '', 16) ?></span> Tambah Akun Staff / Guru
            </button>
          </div>

          <div class="search-filter-wrap">
            <div class="search-input-box">
              <span class="search-icon-pos"><?= pkl_icon('search', '', 16) ?></span>
              <input type="text" id="searchGuruInput" placeholder="Cari berdasarkan nama, email, atau role..." onkeyup="filterTable('tableGuru', this.value)">
            </div>
          </div>

          <?php if (empty($allStaff)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('users', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Akun Staff / Guru</div>
              <div class="empty-state-desc">Tambahkan akun guru pembimbing, pembimbing industri, atau penguji sidang untuk sekolah Anda.</div>
              <button type="button" class="btn-primary" onclick="openAddGuruModal()" style="width:auto;margin:auto;">
                <span><?= pkl_icon('plus', '', 16) ?></span> Tambah Akun Pertama
              </button>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table" id="tableGuru">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Peran / Role</th>
                    <th>Jumlah Murid</th>
                    <th>Terdaftar Pada</th>
                    <th>Aksi Akun</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStaff as $g): ?>
                    <tr>
                      <td style="font-weight: 700; width: 45px;"><?= $no++ ?></td>
                      <td>
                        <div style="font-weight: 700; color: var(--color-text);"><?= admin_h($g['username']) ?></div>
                        <span style="font-size: 0.72rem; color: var(--color-muted);">ID: #<?= (int)$g['id'] ?></span>
                      </td>
                      <td style="color: var(--color-text);"><?= admin_h($g['email']) ?></td>
                      <td>
                        <?php if ($g['role'] === 'guru_industri'): ?>
                          <span class="admin-badge" style="background:var(--color-accent-light);color:var(--color-accent-hover);border:1px solid rgba(2,132,199,0.25);font-size:0.75rem;padding:0.2rem 0.6rem;"><?= pkl_icon('industri', '', 13) ?> Pembimbing Industri</span>
                        <?php elseif ($g['role'] === 'penguji'): ?>
                          <span class="admin-badge" style="background:var(--color-slate-100);color:var(--color-primary-dark);border:1px solid var(--color-slate-200);font-size:0.75rem;padding:0.2rem 0.6rem;"><?= pkl_icon('penguji', '', 13) ?> Penguji Sidang</span>
                        <?php else: ?>
                          <span class="admin-badge" style="background:var(--color-accent-light);color:var(--color-primary);border:1px solid rgba(2,132,199,0.25);font-size:0.75rem;padding:0.2rem 0.6rem;"><?= pkl_icon('guru', '', 13) ?> Guru Sekolah</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="status-pill <?= (int)$g['total_bimbingan'] > 0 ? 'disetujui' : 'menunggu' ?>">
                          <?= (int)$g['total_bimbingan'] ?> Murid
                        </span>
                      </td>
                      <td style="color: var(--color-muted); font-size: 0.82rem;">
                        <?= !empty($g['created_at']) ? date('d M Y', strtotime($g['created_at'])) : '-' ?>
                      </td>
                      <td>
                        <div class="btn-action-group">
                          <button type="button" class="btn-action-sm" onclick="openEditGuruModal(<?= (int)$g['id'] ?>, '<?= admin_h($g['username']) ?>', '<?= admin_h($g['email']) ?>', '<?= admin_h($g['role']) ?>')">
                            <span><?= pkl_icon('edit', '', 14) ?></span> Edit
                          </button>
                          <button type="button" class="btn-action-sm" onclick="openResetPasswordModal(<?= (int)$g['id'] ?>, '<?= admin_h($g['username']) ?>')">
                            <span><?= pkl_icon('key', '', 14) ?></span> Reset Pass
                          </button>
                          <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun staff <?= admin_h($g['username']) ?>?');">
                            <input type="hidden" name="action" value="delete_guru">
                            <input type="hidden" name="current_tab" value="section-guru">
                            <input type="hidden" name="guru_id" value="<?= (int)$g['id'] ?>">
                            <button type="submit" class="btn-action-sm danger">
                              <span><?= pkl_icon('trash', '', 14) ?></span> Hapus
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ==========================================
           TAB 3: DATA MURID
           ========================================== -->
      <div class="content-tab-view <?= $activeTab === 'section-murid' ? 'active' : '' ?>" id="section-murid" <?= $activeTab === 'section-murid' ? '' : 'hidden' ?>>
        
        <?php if (is_array($importResult) && !empty($importResult['rows'])): ?>
        <div class="section-panel" style="border-left: 4px solid var(--color-success);">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('check', '', 20) ?></span> Import Data Murid Berhasil
              <span class="sec-badge" style="background:#E8F5E9;color:var(--color-success);border-color:#A5D6A7;"><?= (int)$importResult['imported'] ?> Akun Dibuat</span>
            </div>
          </div>

          <div class="status-box success" style="margin-bottom: 1rem;">
            <?= (int)$importResult['imported'] ?> akun murid berhasil dibuat pada <?= admin_h($importResult['imported_at'] ?? '-') ?>.
            Username dan password awal otomatis menggunakan NIS siswa. Siswa wajib mengganti password saat login pertama kali.
          </div>
          <p style="font-size: 0.85rem; color: var(--color-muted); margin-bottom: 0.75rem;">
            Daftar akun murid yang baru diimport:
          </p>

          <div class="table-container-responsive">
            <table class="journal-table">
              <thead>
                <tr>
                  <th>No</th>
                  <th>NIS (Username)</th>
                  <th>Nama</th>
                  <th>Kelas</th>
                  <th>Jurusan</th>
                  <th>Jalur PKL</th>
                  <th>Perusahaan PKL</th>
                  <th>Password Awal</th>
                </tr>
              </thead>
              <tbody>
                <?php $importNo = 1; foreach ($importResult['rows'] as $importRow): ?>
                  <tr>
                    <td style="font-weight: 700;"><?= $importNo++ ?></td>
                    <td><strong><?= admin_h($importRow['nis'] ?? '') ?></strong></td>
                    <td><?= admin_h($importRow['nama'] ?? '') ?></td>
                    <td><?= admin_h($importRow['kelas'] ?? '') ?></td>
                    <td><?= admin_h($importRow['jurusan'] ?? '') ?></td>
                    <td><?= admin_h($importRow['jalur_pkl'] ?? '-') ?></td>
                    <td><?= admin_h($importRow['perusahaan'] ?? '-') ?></td>
                    <td><span class="import-password"><?= admin_h($importRow['password'] ?? '') ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <div class="section-panel" style="border-left: 4px solid var(--color-accent);">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('laporan', '', 20) ?></span> Import Data Murid dari Excel
              <span class="sec-badge">Admin Only</span>
            </div>
            <a href="admin.php?action=download_template" class="btn-action-sm primary" style="text-decoration: none;">
              <span><?= pkl_icon('save', '', 14) ?></span> Download Template Excel
            </a>
          </div>

          <p style="font-size: 0.88rem; color: var(--color-muted); margin-bottom: 0.9rem;">
            Unduh template, isi kolom <strong>NIS, Nama, Kelas, Jurusan, Jalur PKL, Nama Perusahaan</strong>, lalu upload untuk melihat preview.
            Data baru disimpan setelah Admin mengonfirmasi. Perusahaan harus sudah terdaftar di <strong>Data Perusahaan PKL</strong>.
          </p>

          <?php if (!pkl_student_import_library_ready()): ?>
            <div class="status-box error">
              Library Excel (PhpSpreadsheet) belum tersedia. Jalankan <code>composer install</code> pada folder project.
            </div>
          <?php else: ?>
            <form method="POST" action="admin.php" enctype="multipart/form-data" class="import-upload-row">
              <input type="hidden" name="action" value="import_preview">
              <input type="hidden" name="current_tab" value="section-murid">
              <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)PKL_STUDENT_IMPORT_MAX_BYTES ?>">
              <input type="file" name="import_file" accept=".xlsx,.xls" required>
              <button type="submit" class="btn-primary" style="width: auto; margin-top: 0; padding: 0.65rem 1.25rem; font-size: 0.88rem;">
                <span><?= pkl_icon('send', '', 16) ?></span> Upload &amp; Preview
              </button>
            </form>
            <small style="display: block; font-size: 0.75rem; color: var(--color-muted); margin-top: 0.4rem;">
              Format .xlsx / .xls, maksimal <?= (int)(PKL_STUDENT_IMPORT_MAX_BYTES / 1024 / 1024) ?> MB dan
              <?= (int)PKL_STUDENT_IMPORT_MAX_ROWS ?> baris per file.
            </small>
          <?php endif; ?>

          <?php if (is_array($importPreview)): ?>
            <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 1.25rem 0;">

            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
              <div style="font-size: 0.9rem; color: var(--color-text);">
                Preview: <strong><?= admin_h($importPreview['filename'] ?? 'file.xlsx') ?></strong>
                <span style="color: var(--color-muted);">• <?= count($importPreview['rows'] ?? []) ?> baris data</span>
              </div>
              <span style="font-size: 0.75rem; color: var(--color-muted);">Preview berlaku 30 menit</span>
            </div>

            <div class="import-summary-grid">
              <div class="import-summary-card">
                <div class="label">Total Data</div>
                <div class="value"><?= (int)($importPreview['total'] ?? 0) ?></div>
              </div>
              <div class="import-summary-card" style="border-color: #A5D6A7;">
                <div class="label" style="color: var(--color-success);">Data Valid</div>
                <div class="value" style="color: var(--color-success);"><?= (int)($importPreview['valid'] ?? 0) ?></div>
              </div>
              <div class="import-summary-card" style="border-color: #F8B4B4;">
                <div class="label" style="color: var(--color-error);">Data Ditolak</div>
                <div class="value" style="color: var(--color-error);"><?= (int)($importPreview['invalid'] ?? 0) ?></div>
              </div>
            </div>

            <div class="table-container-responsive import-preview-scroll">
              <table class="journal-table">
                <thead>
                  <tr>
                    <th>Baris</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Jurusan</th>
                    <th>Jalur PKL</th>
                    <th>Perusahaan</th>
                    <th>Status</th>
                    <th>Alasan Ditolak</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (($importPreview['rows'] ?? []) as $previewRow): ?>
                    <tr <?= ($previewRow['status'] ?? '') === 'invalid' ? 'style="background: #FFF5F5;"' : '' ?>>
                      <td style="font-weight: 700;"><?= (int)($previewRow['row_number'] ?? 0) ?></td>
                      <td><strong><?= admin_h($previewRow['nis'] ?? '') ?></strong></td>
                      <td><?= admin_h($previewRow['nama'] ?? '') ?></td>
                      <td><?= admin_h($previewRow['kelas'] ?? '') ?></td>
                      <td><?= admin_h($previewRow['jurusan'] ?? '') ?></td>
                      <td><?= admin_h($previewRow['jalur_pkl'] ?? '-') ?></td>
                      <td><?= admin_h($previewRow['perusahaan'] ?? '-') ?></td>
                      <td>
                        <?php if (($previewRow['status'] ?? '') === 'valid'): ?>
                          <span class="status-pill disetujui"><?= pkl_icon('check', '', 12) ?> Valid</span>
                        <?php else: ?>
                          <span class="status-pill revisi"><?= pkl_icon('alert', '', 12) ?> Ditolak</span>
                        <?php endif; ?>
                      </td>
                      <td style="font-size: 0.8rem; color: <?= ($previewRow['status'] ?? '') === 'invalid' ? 'var(--color-error)' : 'var(--color-muted)' ?>;">
                        <?= admin_h($previewRow['reason'] ?? '-') ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap;">
              <form method="POST" action="admin.php" style="display: inline;">
                <input type="hidden" name="action" value="import_cancel">
                <input type="hidden" name="current_tab" value="section-murid">
                <button type="submit" class="btn-action-sm">Batalkan Preview</button>
              </form>

              <?php
                // Hitung perusahaan dari baris valid yang belum terdaftar di DB
                $previewCompanies = pkl_student_import_resolve_companies($pdo, (int)$admin_school_id);
                $missingCompanies = [];
                foreach (($importPreview['rows'] ?? []) as $_pr) {
                    if (($_pr['status'] ?? '') !== 'valid') continue;
                    $key = strtolower(trim($_pr['perusahaan'] ?? ''));
                    if ($key !== '' && !isset($previewCompanies[$key])) {
                        $missingCompanies[$key] = trim($_pr['perusahaan'] ?? '');
                    }
                }
              ?>
              <?php if (!empty($missingCompanies)): ?>
                <div class="status-box error" style="width:100%;margin-bottom:0.5rem;">
                  <strong><?= pkl_icon('alert', '', 15) ?> Perusahaan belum terdaftar — tambahkan ke Master Perusahaan sebelum import:</strong>
                  <ul style="margin:0.5rem 0 0 1.25rem;padding:0;">
                    <?php foreach ($missingCompanies as $mc): ?>
                      <li><?= admin_h($mc) ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <div style="margin-top:0.5rem;font-size:0.82rem;">Tambahkan perusahaan di tab <strong>Data Perusahaan PKL</strong>, lalu upload ulang file ini.</div>
                </div>
              <?php endif; ?>

              <?php if ((int)($importPreview['valid'] ?? 0) > 0 && empty($missingCompanies)): ?>
                <form method="POST" action="admin.php" style="display: inline;" onsubmit="return confirm('Import <?= (int)$importPreview['valid'] ?> data valid sekarang? Password akun akan ditampilkan sekali setelah import.');">
                  <input type="hidden" name="action" value="import_confirm">
                  <input type="hidden" name="current_tab" value="section-murid">
                  <input type="hidden" name="import_token" value="<?= admin_h($importPreview['token'] ?? '') ?>">
                  <button type="submit" class="btn-primary" style="width: auto; margin-top: 0; padding: 0.65rem 1.25rem; font-size: 0.88rem;">
                    <span><?= pkl_icon('check', '', 16) ?></span> Konfirmasi &amp; Import <?= (int)$importPreview['valid'] ?> Data Valid
                  </button>
                </form>
              <?php elseif ((int)($importPreview['valid'] ?? 0) > 0 && !empty($missingCompanies)): ?>
                <span style="font-size: 0.82rem; color: var(--color-error); align-self: center;">Tambahkan perusahaan di atas terlebih dahulu, lalu upload ulang.</span>
              <?php else: ?>
                <span style="font-size: 0.82rem; color: var(--color-error); align-self: center;">Tidak ada data valid untuk diimport.</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('users', '', 20) ?></span> Data Murid PKL
              <span class="sec-badge"><?= count($allStudents) ?> Siswa</span>
            </div>
            <button type="button" class="btn-action-sm primary" onclick="switchTab('section-pembimbing')">
              <span><?= pkl_icon('pembimbing_sekolah', '', 16) ?></span> Buka Menu Penetapan Pembimbing
            </button>
          </div>

          <div class="search-filter-wrap">
            <div class="search-input-box">
              <span class="search-icon-pos"><?= pkl_icon('search', '', 16) ?></span>
              <input type="text" id="searchMuridInput" placeholder="Cari siswa berdasarkan nama, NIS/username, email, kelas, atau tempat PKL..." onkeyup="filterTable('tableMurid', this.value)">
            </div>
          </div>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('users', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Data Murid</div>
              <div class="empty-state-desc">Murid yang mendaftar di sistem PKL Journey akan muncul di daftar ini.</div>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table" id="tableMurid">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama / Username Murid</th>
                    <th>Email</th>
                    <th>Tempat PKL</th>
                    <th>Pembimbing & Penguji</th>
                    <th>Status Penugasan</th>
                    <th>Aktivitas & Nilai</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $s): ?>
                    <tr>
                      <td style="font-weight: 700; width: 45px;"><?= $no++ ?></td>
                      <td>
                        <div style="font-weight: 700; color: var(--color-text);"><?= admin_h($s['username']) ?></div>
                        <?php if (!empty($s['nama'])): ?>
                          <div style="font-size: 0.82rem; color: var(--color-text);"><?= admin_h($s['nama']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($s['kelas']) || !empty($s['jurusan'])): ?>
                          <div style="font-size: 0.72rem; color: var(--color-muted);">
                            <?= !empty($s['kelas']) ? 'Kelas ' . admin_h($s['kelas']) : '' ?>
                            <?= !empty($s['kelas']) && !empty($s['jurusan']) ? ' • ' : '' ?>
                            <?= !empty($s['jurusan']) ? admin_h($s['jurusan']) : '' ?>
                          </div>
                        <?php endif; ?>
                        <span style="font-size: 0.72rem; color: var(--color-muted);">ID Siswa: #<?= (int)$s['id'] ?></span>
                      </td>
                      <td style="color: var(--color-text);"><?= admin_h($s['email']) ?></td>
                      <td>
                        <?php if (!empty($s['company_name'])): ?>
                          <div style="font-weight: 600; color: var(--color-text);display:flex;align-items:center;gap:4px;"><?= pkl_icon('perusahaan', '', 14) ?> <?= admin_h($s['company_name']) ?></div>
                        <?php else: ?>
                          <span style="color: var(--color-muted); font-size: 0.82rem; font-style: italic;">Belum ditentukan</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-size:0.8rem;line-height:1.4;">
                          <div><?= pkl_icon('pembimbing_sekolah', '', 14) ?> <strong>Sekolah:</strong> <?= !empty($s['guru_name']) ? admin_h($s['guru_name']) : '<span style="color:var(--color-muted);">-</span>' ?></div>
                          <div><?= pkl_icon('industri', '', 14) ?> <strong>Industri:</strong> <?= !empty($s['industri_name']) ? admin_h($s['industri_name']) : '<span style="color:var(--color-muted);">-</span>' ?></div>
                          <div><?= pkl_icon('penguji', '', 14) ?> <strong>Penguji:</strong> <?= !empty($s['penguji_name']) ? admin_h($s['penguji_name']) : '<span style="color:var(--color-muted);">-</span>' ?></div>
                        </div>
                      </td>
                      <td>
                        <?php 
                          $isComplete = !empty($s['guru_id']) && !empty($s['industri_id']) && !empty($s['penguji_id']);
                          $isPartial  = !empty($s['guru_id']) || !empty($s['industri_id']) || !empty($s['penguji_id']);
                        ?>
                        <?php if ($isComplete): ?>
                          <span class="status-pill disetujui"><?= pkl_icon('check', '', 12) ?> Lengkap</span>
                        <?php elseif ($isPartial): ?>
                          <span class="status-pill menunggu"><?= pkl_icon('clock', '', 12) ?> Sebagian</span>
                        <?php else: ?>
                          <span class="status-pill revisi"><?= pkl_icon('alert', '', 12) ?> Belum Ada</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-size: 0.8rem; color: var(--color-text);">
                          <?= pkl_icon('jurnal', '', 13) ?> <strong><?= (int)$s['total_journals'] ?></strong> Jurnal
                        </div>
                        <div style="font-size: 0.73rem; color: var(--color-muted); margin-top:0.15rem;">
                          Sekolah: <strong><?= $s['assessment_score'] !== null ? (int)$s['assessment_score'] : '-' ?></strong> |
                          Industri: <strong><?= $s['industry_score'] !== null ? (int)$s['industry_score'] : '-' ?></strong> |
                          Sidang: <strong><?= $s['sidang_score'] !== null ? (int)$s['sidang_score'] : '-' ?></strong>
                        </div>
                      </td>
                      <td>
                        <div class="btn-action-group">
                          <button type="button" class="btn-action-sm primary" onclick="openAssignModal(<?= (int)$s['id'] ?>, '<?= admin_h($s['username']) ?>', <?= (int)($s['guru_id'] ?? 0) ?>, <?= (int)($s['industri_id'] ?? 0) ?>, <?= (int)($s['penguji_id'] ?? 0) ?>, <?= (int)($s['company_id'] ?? 0) ?>, 'section-murid')">
                            <span><?= pkl_icon('settings', '', 14) ?></span> Atur
                          </button>
                          <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Kelola akun murid ini?\n\n- Akun DENGAN riwayat (jurnal/penilaian/penugasan) akan DINONAKTIFKAN, data dipertahankan.\n- Akun TANPA riwayat akan dihapus permanen.');">
                            <input type="hidden" name="action" value="delete_murid">
                            <input type="hidden" name="current_tab" value="section-murid">
                            <input type="hidden" name="murid_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn-action-sm danger" title="Hapus / Nonaktifkan akun murid">
                              <span><?= pkl_icon('trash', '', 14) ?></span> Hapus
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ==========================================
           TAB 4: ATUR GURU PEMBIMBING (FITUR UTAMA)
           ========================================== -->
      <div class="content-tab-view <?= $activeTab === 'section-pembimbing' ? 'active' : '' ?>" id="section-pembimbing" <?= $activeTab === 'section-pembimbing' ? '' : 'hidden' ?>>

        <?php if (!empty($bulkPreview)):
              $bp = $bulkPreview;
              $bpNew = count($bp['new_ids']);
              $bpOw  = count($bp['overwrite_ids']);
              $bpAll = count($bp['all_ids']);
        ?>
        <!-- KONFIRMASI PENUGASAN MASSAL -->
        <div class="section-panel" style="border-left:4px solid var(--color-orange);margin-bottom:1.25rem;">
          <div class="section-panel-header">
            <div class="section-panel-title"><span><?= pkl_icon('alert', '', 20) ?></span> Konfirmasi Penugasan Massal</div>
          </div>

          <div style="font-size:0.9rem;margin-bottom:0.85rem;">
            Akan menugaskan
            <?php if ($bp['guru_id'] > 0): ?> Pembimbing Sekolah <strong><?= admin_h($bp['guru_name']) ?></strong><?php endif; ?>
            <?php if ($bp['guru_id'] > 0 && $bp['industri_id'] > 0): ?> &amp; <?php endif; ?>
            <?php if ($bp['industri_id'] > 0): ?> Pembimbing Industri <strong><?= admin_h($bp['industri_name']) ?></strong><?php endif; ?>
            ke <strong><?= $bpAll ?> siswa</strong>.
          </div>

          <div class="status-box success" style="margin-bottom:0.75rem;">
            <strong><?= $bpNew ?> siswa</strong> akan ditugaskan tanpa menimpa penugasan aktif<?php if (!empty($bp['invalid_count'])): ?> &middot; <?= (int)$bp['invalid_count'] ?> siswa tidak valid dilewati<?php endif; ?>.
          </div>

          <?php if ($bpOw > 0): ?>
            <div class="status-box error" style="margin-bottom:0.75rem;">
              <strong><?= $bpOw ?> siswa</strong> sudah memiliki penugasan aktif dan <strong>akan ditimpa</strong>:
              <div style="margin-top:0.5rem;max-height:200px;overflow:auto;">
                <table class="journal-table" style="font-size:0.82rem;">
                  <thead><tr><th>Siswa</th><th>Pembimbing Sekolah</th><th>Pembimbing Industri</th></tr></thead>
                  <tbody>
                  <?php foreach ($bp['overwrite_ids'] as $sid):
                        $r = $bp['stu_map'][$sid] ?? null;
                        if (!$r) continue; ?>
                    <tr>
                      <td><strong><?= admin_h($r['username']) ?></strong></td>
                      <td>
                        <?= !empty($r['guru_name']) ? admin_h($r['guru_name']) : '<span style="color:var(--color-muted);">-</span>' ?>
                        <?php if ($bp['guru_id'] > 0): ?> &rarr; <strong><?= admin_h($bp['guru_name']) ?></strong><?php endif; ?>
                      </td>
                      <td>
                        <?= !empty($r['industri_name']) ? admin_h($r['industri_name']) : '<span style="color:var(--color-muted);">-</span>' ?>
                        <?php if ($bp['industri_id'] > 0): ?> &rarr; <strong><?= admin_h($bp['industri_name']) ?></strong><?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.9rem;margin:0.75rem 0;">
              <input type="checkbox" id="confirmOverwriteChk" onchange="document.getElementById('bulkConfirmBtn').disabled = !this.checked">
              Saya memahami bahwa penugasan aktif pada <strong><?= $bpOw ?> siswa</strong> di atas akan ditimpa.
            </label>
          <?php endif; ?>

          <form method="POST" action="admin.php" style="margin-top:1rem;">
            <input type="hidden" name="action" value="assign_guru_bulk">
            <input type="hidden" name="current_tab" value="section-pembimbing">
            <input type="hidden" name="confirm_overwrite" value="1">
            <input type="hidden" name="bulk_guru_id" value="<?= (int)$bp['guru_id'] ?>">
            <input type="hidden" name="bulk_industri_id" value="<?= (int)$bp['industri_id'] ?>">
            <?php foreach ($bp['all_ids'] as $sid): ?>
              <input type="hidden" name="bulk_students[]" value="<?= (int)$sid ?>">
            <?php endforeach; ?>
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;">
              <a href="admin.php?tab=section-pembimbing" class="btn-action-sm">Batal</a>
              <button type="submit" id="bulkConfirmBtn" class="btn-primary" style="width:auto;margin-top:0;" <?= $bpOw > 0 ? 'disabled' : '' ?>>
                <span><?= pkl_icon('check', '', 16) ?></span> Konfirmasi &amp; Simpan (<?= $bpAll ?> siswa)
              </button>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- PENUGASAN PEMBIMBING MASSAL -->
        <div class="section-panel" style="border-left:4px solid var(--color-accent);margin-bottom:1.25rem;">
          <div class="section-panel-header">
            <div>
              <div class="section-panel-title">
                <span><?= pkl_icon('users', '', 20) ?></span> Penugasan Pembimbing Massal
                <span class="sec-badge">Baru</span>
              </div>
              <div style="font-size:0.85rem;color:var(--color-muted);margin-top:0.25rem;">
                Tugaskan satu Guru Pembimbing Sekolah dan/atau Guru Pembimbing Industri ke banyak siswa sekaligus. Penugasan aktif tidak akan ditimpa tanpa konfirmasi khusus.
              </div>
            </div>
          </div>

          <form method="POST" action="admin.php" id="formBulkAssign" onsubmit="return validateBulkAssign(event)">
            <input type="hidden" name="action" value="assign_guru_bulk">
            <input type="hidden" name="current_tab" value="section-pembimbing">

            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;">
              <div class="form-group" style="margin:0;flex:1 1 260px;">
                <label class="form-label" for="bulk_guru_id">Guru Pembimbing Sekolah</label>
                <select name="bulk_guru_id" id="bulk_guru_id" class="form-control">
                  <option value="">-- Tidak mengubah / Kosongkan --</option>
                  <?php if (empty($allTeachers)): ?>
                    <option value="" disabled>Belum ada akun Guru</option>
                  <?php else: foreach ($allTeachers as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= admin_h($g['username']) ?> (<?= (int)$g['total_bimbingan'] ?> murid)</option>
                  <?php endforeach; endif; ?>
                </select>
              </div>
              <div class="form-group" style="margin:0;flex:1 1 260px;">
                <label class="form-label" for="bulk_industri_id">Guru Pembimbing Industri</label>
                <select name="bulk_industri_id" id="bulk_industri_id" class="form-control">
                  <option value="">-- Tidak mengubah / Kosongkan --</option>
                  <?php if (empty($allIndustri)): ?>
                    <option value="" disabled>Belum ada akun Pembimbing Industri</option>
                  <?php else: foreach ($allIndustri as $ind): ?>
                    <option value="<?= (int)$ind['id'] ?>"><?= admin_h($ind['username']) ?> (<?= (int)$ind['total_bimbingan'] ?> murid)</option>
                  <?php endforeach; endif; ?>
                </select>
              </div>
            </div>

            <div class="search-filter-wrap" style="margin:0.75rem 0;">
              <div class="search-input-box">
                <span class="search-icon-pos"><?= pkl_icon('search', '', 16) ?></span>
                <input type="text" id="bulkSearchInput" placeholder="Cari NIS / nama siswa..." oninput="filterBulkStudents()">
              </div>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <select id="bulkClassFilter" class="form-control" style="width:auto;" onchange="filterBulkStudents()">
                  <option value="">Semua Kelas</option>
                  <?php
                    $seenKelas = [];
                    foreach ($allStudents as $s) {
                        $k = trim($s['kelas'] ?? '');
                        if ($k !== '' && !isset($seenKelas[$k])) {
                            $seenKelas[$k] = 1;
                            echo '<option value="' . admin_h($k) . '">' . admin_h($k) . '</option>';
                        }
                    }
                  ?>
                </select>
                <label style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.85rem;white-space:nowrap;">
                  <input type="checkbox" id="bulkSelectAll" onchange="toggleBulkSelectAll(this)"> Pilih semua (hasil filter)
                </label>
                <span id="bulkSelectedCount" style="font-size:0.85rem;font-weight:700;color:var(--color-accent);">0 siswa dipilih</span>
              </div>
            </div>

            <?php if (empty($allStudents)): ?>
              <div class="empty-state">
                <div class="empty-state-icon"><?= pkl_icon('siswa', '', 44) ?></div>
                <div class="empty-state-title">Belum Ada Murid</div>
                <div class="empty-state-desc">Tambahkan akun murid terlebih dahulu sebelum melakukan penugasan massal.</div>
              </div>
            <?php else: ?>
              <div class="table-container-responsive" style="max-height:380px;overflow:auto;border:1px solid var(--color-border, #e2e8f0);border-radius:0.5rem;">
                <table class="journal-table" id="tableBulkStudents">
                  <thead>
                    <tr>
                      <th style="width:42px;"><input type="checkbox" id="bulkSelectAllHead" onchange="toggleBulkSelectAll(this)"></th>
                      <th>NIS / Username</th>
                      <th>Nama</th>
                      <th>Kelas</th>
                      <th>Pembimbing Saat Ini</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($allStudents as $s):
                        $hasGuru = !empty($s['guru_id']);
                        $hasInd  = !empty($s['industri_id']);
                        $assigned = $hasGuru || $hasInd;
                        $searchStr = strtolower(trim(($s['username'] ?? '') . ' ' . ($s['nama'] ?? '')));
                  ?>
                    <tr data-kelas="<?= admin_h($s['kelas'] ?? '') ?>" data-search="<?= admin_h($searchStr) ?>">
                      <td><input type="checkbox" name="bulk_students[]" value="<?= (int)$s['id'] ?>" class="bulk-student-chk" onchange="updateBulkSelectedCount()"></td>
                      <td style="font-weight:700;"><?= admin_h($s['username']) ?></td>
                      <td><?= admin_h($s['nama'] ?? '-') ?></td>
                      <td><?= admin_h($s['kelas'] ?? '-') ?></td>
                      <td style="font-size:0.8rem;line-height:1.5;">
                        <?php if ($hasGuru): ?>
                          <span class="status-pill disetujui"><?= pkl_icon('pembimbing_sekolah', '', 12) ?> <?= admin_h($s['guru_name']) ?></span>
                        <?php else: ?>
                          <span class="status-pill menunggu">Sekolah: -</span>
                        <?php endif; ?>
                        <br>
                        <?php if ($hasInd): ?>
                          <span class="status-pill disetujui"><?= pkl_icon('industri', '', 12) ?> <?= admin_h($s['industri_name']) ?></span>
                        <?php else: ?>
                          <span class="status-pill menunggu">Industri: -</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($assigned): ?>
                          <span class="status-pill disetujui">Sudah ditugaskan</span>
                        <?php else: ?>
                          <span class="status-pill menunggu">Belum ditugaskan</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
                <button type="submit" class="btn-primary" style="width:auto;margin-top:0;">
                  <span><?= pkl_icon('plus', '', 16) ?></span> Tinjau &amp; Konfirmasi Penugasan
                </button>
              </div>
            <?php endif; ?>
          </form>
        </div>

        <div class="section-panel">
          <div class="section-panel-header">
            <div>
              <div class="section-panel-title">
                <span><?= pkl_icon('pembimbing_sekolah', '', 20) ?></span> Penetapan Pembimbing Sekolah, Industri & Penguji Sidang
                <span class="sec-badge">Fitur Utama</span>
              </div>
              <div style="font-size: 0.85rem; color: var(--color-muted); margin-top: 0.25rem;">
                Atur Guru Pembimbing Sekolah, Guru Pembimbing Industri, dan Penguji Sidang PKL untuk setiap murid.
              </div>
            </div>
            <button type="button" class="btn-primary" onclick="openAssignModal(0, '', 0, 0, 0, 0, 'section-pembimbing')" style="width:auto;margin-top:0;padding:0.65rem 1.25rem;font-size:0.88rem;">
              <span><?= pkl_icon('plus', '', 16) ?></span> Tugaskan Pembimbing / Penguji
            </button>
          </div>

          <div class="search-filter-wrap">
            <div class="search-input-box">
              <span class="search-icon-pos"><?= pkl_icon('search', '', 16) ?></span>
              <input type="text" id="searchPembimbingInput" placeholder="Cari nama murid, guru, industri, atau penguji..." onkeyup="filterTable('tablePembimbing', this.value)">
            </div>
            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
              <button type="button" class="btn-action-sm primary" onclick="filterPembimbingStatus('all', this)">Semua (<?= count($allStudents) ?>)</button>
              <button type="button" class="btn-action-sm" onclick="filterPembimbingStatus('unassigned', this)">Belum Lengkap (<?= $muridBelumLengkap ?>)</button>
              <button type="button" class="btn-action-sm" onclick="filterPembimbingStatus('assigned', this)">Sudah Lengkap (<?= $muridSudahLengkap ?>)</button>
            </div>
          </div>

          <?php if (empty($allStudents)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('pembimbing_sekolah', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Murid Terdaftar</div>
              <div class="empty-state-desc">Murid akan muncul di daftar ini setelah akun terdaftar di sistem.</div>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table" id="tablePembimbing">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Murid</th>
                    <th>Perusahaan PKL</th>
                    <th>Guru Sekolah</th>
                    <th>Pembimbing Industri</th>
                    <th>Penguji Sidang</th>
                    <th>Aksi Penetapan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allStudents as $s): ?>
                    <tr data-status="<?= (!empty($s['guru_id']) && !empty($s['industri_id']) && !empty($s['penguji_id'])) ? 'assigned' : 'unassigned' ?>">
                      <td style="font-weight: 700; width: 45px;"><?= $no++ ?></td>
                      <td>
                        <div style="font-weight: 700; color: var(--color-text); font-size: 0.95rem;">
                          <?= admin_h($s['username']) ?>
                        </div>
                        <span style="font-size: 0.72rem; color: var(--color-muted);"><?= admin_h($s['email']) ?></span>
                      </td>
                      <td>
                        <?php if (!empty($s['company_name'])): ?>
                          <span style="font-weight: 600; color: var(--color-text);display:inline-flex;align-items:center;gap:4px;"><?= pkl_icon('perusahaan', '', 14) ?> <?= admin_h($s['company_name']) ?></span>
                        <?php else: ?>
                          <span style="color: var(--color-muted); font-size: 0.82rem; font-style: italic;">Belum ada penempatan</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($s['guru_name'])): ?>
                          <span class="status-pill disetujui" style="font-size: 0.8rem;">
                            <?= pkl_icon('pembimbing_sekolah', '', 12) ?> <?= admin_h($s['guru_name']) ?>
                          </span>
                        <?php else: ?>
                          <span class="status-pill menunggu" style="font-size: 0.8rem;">
                            <?= pkl_icon('alert', '', 12) ?> Belum Ada
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($s['industri_name'])): ?>
                          <span class="status-pill disetujui" style="font-size: 0.8rem;">
                            <?= pkl_icon('industri', '', 12) ?> <?= admin_h($s['industri_name']) ?>
                          </span>
                        <?php else: ?>
                          <span class="status-pill menunggu" style="font-size: 0.8rem;">
                            <?= pkl_icon('alert', '', 12) ?> Belum Ada
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($s['penguji_name'])): ?>
                          <span class="status-pill disetujui" style="font-size: 0.8rem;">
                            <?= pkl_icon('penguji', '', 12) ?> <?= admin_h($s['penguji_name']) ?>
                          </span>
                        <?php else: ?>
                          <span class="status-pill menunggu" style="font-size: 0.8rem;">
                            <?= pkl_icon('alert', '', 12) ?> Belum Ada
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn-action-sm primary" onclick="openAssignModal(<?= (int)$s['id'] ?>, '<?= admin_h($s['username']) ?>', <?= (int)($s['guru_id'] ?? 0) ?>, <?= (int)($s['industri_id'] ?? 0) ?>, <?= (int)($s['penguji_id'] ?? 0) ?>, <?= (int)($s['company_id'] ?? 0) ?>, 'section-pembimbing')" title="Atur Guru Sekolah, Industri, Penguji & Lokasi">
                          <span><?= pkl_icon('settings', '', 14) ?></span> Atur Lengkap
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ==========================================
           TAB 5: DATA PERUSAHAAN PKL
           ========================================== -->
      <div class="content-tab-view <?= $activeTab === 'section-perusahaan' ? 'active' : '' ?>" id="section-perusahaan" <?= $activeTab === 'section-perusahaan' ? '' : 'hidden' ?>>
        
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title">
              <span><?= pkl_icon('perusahaan', '', 20) ?></span> Data Perusahaan / Instansi Mitra PKL
              <span class="sec-badge"><?= count($allCompanies) ?> Perusahaan</span>
            </div>
            <button type="button" class="btn-primary" onclick="openAddCompanyModal()" style="width:auto;margin-top:0;padding:0.65rem 1.25rem;font-size:0.88rem;">
              <span><?= pkl_icon('plus', '', 16) ?></span> Tambah Perusahaan
            </button>
          </div>

          <div class="search-filter-wrap">
            <div class="search-input-box">
              <span class="search-icon-pos"><?= pkl_icon('search', '', 16) ?></span>
              <input type="text" id="searchCompanyInput" placeholder="Cari perusahaan berdasarkan nama atau alamat..." onkeyup="filterTable('tableCompany', this.value)">
            </div>
          </div>

          <?php if (empty($allCompanies)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= pkl_icon('perusahaan', '', 44) ?></div>
              <div class="empty-state-title">Belum Ada Perusahaan PKL</div>
              <div class="empty-state-desc">Tambahkan profil perusahaan tempat murid melaksanakan program PKL.</div>
              <button type="button" class="btn-primary" onclick="openAddCompanyModal()" style="width:auto;margin:auto;">
                <span><?= pkl_icon('plus', '', 16) ?></span> Tambah Perusahaan Pertama
              </button>
            </div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table" id="tableCompany">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Perusahaan</th>
                    <th>Alamat</th>
                    <th>No. Telepon / Kontak</th>
                    <th>Jumlah Murid PKL</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($allCompanies as $c): ?>
                    <tr>
                      <td style="font-weight: 700; width: 45px;"><?= $no++ ?></td>
                      <td>
                        <div style="font-weight: 700; color: var(--color-text);"><?= admin_h($c['name']) ?></div>
                        <span style="font-size: 0.72rem; color: var(--color-muted);">ID: #<?= (int)$c['id'] ?></span>
                      </td>
                      <td style="color: var(--color-text); max-width: 250px;">
                        <?= !empty($c['address']) ? admin_h($c['address']) : '<span style="color:var(--color-muted);font-style:italic;">Belum diisi</span>' ?>
                      </td>
                      <td style="color: var(--color-text);">
                        <?= !empty($c['phone']) ? admin_h($c['phone']) : '<span style="color:var(--color-muted);font-style:italic;">-</span>' ?>
                      </td>
                      <td>
                        <span class="status-pill <?= (int)$c['total_interns'] > 0 ? 'disetujui' : 'menunggu' ?>">
                          <?= (int)$c['total_interns'] ?> Murid
                        </span>
                      </td>
                      <td>
                        <div class="btn-action-group">
                          <button type="button" class="btn-action-sm" onclick="openEditCompanyModal(<?= (int)$c['id'] ?>, '<?= admin_h($c['name']) ?>', '<?= admin_h($c['address'] ?? '') ?>', '<?= admin_h($c['phone'] ?? '') ?>')">
                            <span><?= pkl_icon('edit', '', 14) ?></span> Edit
                          </button>
                          <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus perusahaan <?= admin_h($c['name']) ?>?');">
                            <input type="hidden" name="action" value="delete_company">
                            <input type="hidden" name="current_tab" value="section-perusahaan">
                            <input type="hidden" name="company_id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn-action-sm danger">
                              <span><?= pkl_icon('trash', '', 14) ?></span> Hapus
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ============================================
           TAB: PENUGASAN & MONITORING PENGUJI (PRODUKTIF & JURNAL)
           ============================================ -->
      <div class="content-tab-view <?= $activeTab === 'section-penguji' ? 'active' : '' ?>" id="section-penguji" <?= $activeTab === 'section-penguji' ? '' : 'hidden' ?>>

        <?php if (!empty($examinerPreview)):
              $ep = $examinerPreview;
              $epNew = count($ep['new_ids']);
              $epOw  = count($ep['overwrite_ids']);
              $epAll = count($ep['all_ids']);
              $catLabel = $ep['category'] === 'produktif' ? 'Produktif' : 'Jurnal';
        ?>
        <!-- Konfirmasi Penugasan Penguji Massal -->
        <div class="section-panel" style="border-left:4px solid var(--color-orange);margin-bottom:1.25rem;">
          <div class="section-panel-header">
            <div class="section-panel-title"><span><?= pkl_icon('alert', '', 20) ?></span> Konfirmasi Penugasan Penguji <?= $catLabel ?></div>
          </div>
          <div style="font-size:0.9rem;margin-bottom:0.85rem;">
            Akan menugaskan <strong><?= admin_h($ep['penguji_name']) ?></strong> sebagai Penguji <strong><?= $catLabel ?></strong> ke <strong><?= $epAll ?> siswa</strong>.
          </div>
          <div class="status-box success" style="margin-bottom:0.75rem;">
            <strong><?= $epNew ?> siswa</strong> ditugaskan baru (tanpa menimpa penugasan aktif).
          </div>
          <?php if ($epOw > 0): ?>
            <div class="status-box error" style="margin-bottom:0.75rem;">
              <strong><?= $epOw ?> siswa</strong> sudah memiliki Penguji <?= $catLabel ?> lain dan <strong>akan ditimpa</strong>:
              <div style="margin-top:0.5rem;max-height:200px;overflow:auto;">
                <table class="journal-table" style="font-size:0.82rem;">
                  <thead><tr><th>Siswa</th><th>Penguji <?= $catLabel ?> Saat Ini</th></tr></thead>
                  <tbody>
                  <?php foreach ($ep['overwrite_ids'] as $sid): $r = $ep['stu_map'][$sid] ?? null; if(!$r) continue; ?>
                    <tr><td><strong><?= admin_h($r['username']) ?></strong></td><td><?= !empty($r['cur_penguji_name']) ? admin_h($r['cur_penguji_name']) : '<span style="color:var(--color-muted);">-</span>' ?> &rarr; <strong><?= admin_h($ep['penguji_name']) ?></strong></td></tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.9rem;margin:0.75rem 0;">
              <input type="checkbox" id="confirmExaminerOverwriteChk" onchange="document.getElementById('examinerConfirmBtn').disabled = !this.checked">
              Saya memahami penugasan aktif pada <strong><?= $epOw ?> siswa</strong> di atas akan ditimpa.
            </label>
          <?php endif; ?>
          <form method="POST" action="admin.php" style="margin-top:1rem;">
            <input type="hidden" name="action" value="assign_penguji_bulk">
            <input type="hidden" name="current_tab" value="section-penguji">
            <input type="hidden" name="confirm_overwrite" value="1">
            <input type="hidden" name="bulk_penguji_id" value="<?= (int)$ep['penguji_id'] ?>">
            <input type="hidden" name="bulk_category" value="<?= admin_h($ep['category']) ?>">
            <?php foreach ($ep['all_ids'] as $sid): ?><input type="hidden" name="bulk_students[]" value="<?= (int)$sid ?>"><?php endforeach; ?>
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;">
              <a href="admin.php?tab=section-penguji" class="btn-action-sm">Batal</a>
              <button type="submit" id="examinerConfirmBtn" class="btn-primary" style="width:auto;margin-top:0;" <?= $epOw > 0 ? 'disabled' : '' ?>><span><?= pkl_icon('check', '', 16) ?></span> Konfirmasi &amp; Simpan (<?= $epAll ?> siswa)</button>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- Penugasan Massal Penguji -->
        <div class="section-panel" style="border-left:4px solid var(--color-accent);margin-bottom:1.25rem;">
          <div class="section-panel-header">
            <div>
              <div class="section-panel-title"><span><?= pkl_icon('penguji', '', 20) ?></span> Penugasan Penguji Massal (Produktif &amp; Jurnal) <span class="sec-badge">Baru</span></div>
              <div style="font-size:0.85rem;color:var(--color-muted);margin-top:0.25rem;">Tugaskan satu Penguji ke banyak siswa untuk satu kategori (Produktif/Jurnal). Satu siswa dapat memiliki dua penguji berbeda (satu per kategori). Penugasan aktif tidak ditimpa tanpa konfirmasi.</div>
            </div>
          </div>
          <form method="POST" action="admin.php" id="formExaminerBulk" onsubmit="return validateExaminerBulk(event)">
            <input type="hidden" name="action" value="assign_penguji_bulk">
            <input type="hidden" name="current_tab" value="section-penguji">
            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;">
              <div class="form-group" style="margin:0;flex:1 1 260px;">
                <label class="form-label" for="bulk_penguji_id">Penguji</label>
                <select name="bulk_penguji_id" id="bulk_penguji_id" class="form-control">
                  <option value="">-- Pilih Penguji --</option>
                  <?php if (empty($allPenguji)): ?><option value="" disabled>Belum ada akun penguji</option><?php else: foreach ($allPenguji as $pj): ?>
                    <option value="<?= (int)$pj['id'] ?>"><?= admin_h($pj['username']) ?> (<?= (int)$pj['total_bimbingan'] ?> penugasan)</option>
                  <?php endforeach; endif; ?>
                </select>
              </div>
              <div class="form-group" style="margin:0;flex:1 1 200px;">
                <label class="form-label" for="bulk_category">Kategori Penilaian</label>
                <select name="bulk_category" id="bulk_category" class="form-control" onchange="filterExaminerStudents()">
                  <option value="produktif">Penguji Produktif</option>
                  <option value="jurnal">Penguji Jurnal</option>
                </select>
              </div>
            </div>
            <div class="search-filter-wrap" style="margin:0.75rem 0;">
              <div class="search-input-box">
                <span class="search-icon-pos"><?= pkl_icon('search', '', 16) ?></span>
                <input type="text" id="examinerSearchInput" placeholder="Cari NIS / nama siswa..." oninput="filterExaminerStudents()">
              </div>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <select id="examinerClassFilter" class="form-control" style="width:auto;" onchange="filterExaminerStudents()">
                  <option value="">Semua Kelas</option>
                  <?php $seenK2=[]; foreach ($examinerList as $s){ $k=trim($s['kelas'] ?? ''); if($k!=='' && !isset($seenK2[$k])){ $seenK2[$k]=1; echo '<option value="'.admin_h($k).'">'.admin_h($k).'</option>'; } } ?>
                </select>
                <select id="examinerStatusFilter" class="form-control" style="width:auto;" onchange="filterExaminerStudents()">
                  <option value="">Semua Status</option>
                  <option value="assigned">Sudah ditugaskan (kategori)</option>
                  <option value="unassigned">Belum ditugaskan (kategori)</option>
                </select>
                <label style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.85rem;white-space:nowrap;">
                  <input type="checkbox" id="examinerSelectAll" onchange="toggleExaminerSelectAll(this)"> Pilih semua (hasil filter)
                </label>
                <span id="examinerSelectedCount" style="font-size:0.85rem;font-weight:700;color:var(--color-accent);">0 siswa dipilih</span>
              </div>
            </div>
            <?php if (empty($examinerList)): ?>
              <div class="empty-state"><div class="empty-state-icon"><?= pkl_icon('siswa', '', 44) ?></div><div class="empty-state-title">Belum Ada Murid</div><div class="empty-state-desc">Tambahkan akun murid terlebih dahulu.</div></div>
            <?php else: ?>
              <div class="table-container-responsive" style="max-height:360px;overflow:auto;border:1px solid var(--color-border, #e2e8f0);border-radius:0.5rem;">
                <table class="journal-table" id="tableExaminerStudents">
                  <thead><tr><th style="width:42px;"><input type="checkbox" id="examinerSelectAllHead" onchange="toggleExaminerSelectAll(this)"></th><th>NIS / Username</th><th>Nama</th><th>Kelas</th><th>Penguji Produktif</th><th>Penguji Jurnal</th><th>Status Kategori</th></tr></thead>
                  <tbody>
                  <?php foreach ($examinerList as $s):
                    $hasP = !empty($s['produktif_penguji_id']);
                    $hasJ = !empty($s['jurnal_penguji_id']);
                    $searchStr = strtolower(trim(($s['username'] ?? '').' '.($s['nama'] ?? '')));
                  ?>
                    <tr data-kelas="<?= admin_h($s['kelas'] ?? '') ?>" data-search="<?= admin_h($searchStr) ?>" data-produktif="<?= $hasP ? '1':'0' ?>" data-jurnal="<?= $hasJ ? '1':'0' ?>">
                      <td><input type="checkbox" name="bulk_students[]" value="<?= (int)$s['id'] ?>" class="examiner-student-chk" onchange="updateExaminerSelectedCount()"></td>
                      <td style="font-weight:700;"><?= admin_h($s['username']) ?></td>
                      <td><?= admin_h($s['nama'] ?? '-') ?></td>
                      <td><?= admin_h($s['kelas'] ?? '-') ?></td>
                      <td style="font-size:0.8rem;"><?= $hasP ? '<span class="status-pill disetujui">'.admin_h($s['produktif_penguji']).'</span>' : '<span class="status-pill menunggu">-</span>' ?></td>
                      <td style="font-size:0.8rem;"><?= $hasJ ? '<span class="status-pill disetujui">'.admin_h($s['jurnal_penguji']).'</span>' : '<span class="status-pill menunggu">-</span>' ?></td>
                      <td style="font-size:0.78rem;">
                        <span class="status-pill <?= $hasP ? 'disetujui' : 'menunggu' ?>">P:<?= $hasP ? '&#10003;' : '-' ?></span>
                        <span class="status-pill <?= $hasJ ? 'disetujui' : 'menunggu' ?>">J:<?= $hasJ ? '&#10003;' : '-' ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
                <button type="submit" class="btn-primary" style="width:auto;margin-top:0;"><span><?= pkl_icon('plus', '', 16) ?></span> Tinjau &amp; Konfirmasi Penugasan</button>
              </div>
            <?php endif; ?>
          </form>
        </div>

        <!-- Monitoring Penilaian Penguji -->
        <div class="section-panel">
          <div class="section-panel-header">
            <div class="section-panel-title"><span><?= pkl_icon('penilaian', '', 20) ?></span> Monitoring Penugasan &amp; Penilaian Penguji</div>
          </div>
          <?php if (empty($examinerList)): ?>
            <div class="empty-state"><div class="empty-state-icon"><?= pkl_icon('penguji', '', 44) ?></div><div class="empty-state-title">Belum Ada Murid</div></div>
          <?php else: ?>
            <div class="table-container-responsive">
              <table class="journal-table">
                <thead><tr><th>No</th><th>Siswa</th><th>Kelas</th><th>Penguji Produktif</th><th>Penilaian Produktif</th><th>Penguji Jurnal</th><th>Penilaian Jurnal</th></tr></thead>
                <tbody>
                <?php $no=1; foreach ($examinerList as $s): ?>
                  <tr>
                    <td style="font-weight:700;"><?= $no++ ?></td>
                    <td><div style="font-weight:700;"><?= admin_h($s['username']) ?></div><span style="font-size:0.72rem;color:var(--color-muted);"><?= admin_h($s['nama'] ?? '') ?></span></td>
                    <td><?= admin_h($s['kelas'] ?? '-') ?></td>
                    <td><?= !empty($s['produktif_penguji']) ? admin_h($s['produktif_penguji']) : '<span style="color:var(--color-muted);">-</span>' ?></td>
                    <td><?php if(empty($s['produktif_penguji_id'])): ?><span class="status-pill menunggu">Belum ditugaskan</span><?php elseif(!empty($s['produktif_done'])): ?><span class="status-pill disetujui">Sudah dinilai</span><?php else: ?><span class="status-pill menunggu">Belum dinilai</span><?php endif; ?></td>
                    <td><?= !empty($s['jurnal_penguji']) ? admin_h($s['jurnal_penguji']) : '<span style="color:var(--color-muted);">-</span>' ?></td>
                    <td><?php if(empty($s['jurnal_penguji_id'])): ?><span class="status-pill menunggu">Belum ditugaskan</span><?php elseif(!empty($s['jurnal_done'])): ?><span class="status-pill disetujui">Sudah dinilai</span><?php else: ?><span class="status-pill menunggu">Belum dinilai</span><?php endif; ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </main>
  </div>

  <!-- ==========================================
       MODAL 1: TAMBAH AKUN GURU / STAFF
       ========================================== -->
  <div class="modal-backdrop" id="modalAddGuru">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('plus', '', 18) ?> Tambah Akun Guru / Pembimbing / Penguji</div>
        <button type="button" class="modal-close-btn" onclick="closeModal('modalAddGuru')">&times;</button>
      </div>
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="add_guru">
        <input type="hidden" name="current_tab" value="section-guru">

        <div class="form-group">
          <label class="form-label" for="add_guru_role">Peran / Role Akun *</label>
          <select name="role" id="add_guru_role" class="form-control" required>
            <option value="guru">Guru Pembimbing Sekolah</option>
            <option value="guru_industri">Guru Pembimbing Industri</option>
            <option value="penguji">Penguji Sidang PKL</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="add_guru_username">Username *</label>
          <input type="text" name="username" id="add_guru_username" class="form-control" required placeholder="Contoh: pak_budi / industri_astra" autocomplete="off">
        </div>

        <div class="form-group">
          <label class="form-label" for="add_guru_email">Email Akun *</label>
          <input type="email" name="email" id="add_guru_email" class="form-control" required placeholder="staff@instansi.com">
        </div>

        <div class="form-group">
          <label class="form-label" for="add_guru_password">Password Awal *</label>
          <div class="input-wrapper">
            <input type="password" name="password" id="add_guru_password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('add_guru_password')"><?= pkl_icon('eye', '', 18) ?></button>
          </div>
          <small style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem; display: block;">
            Password otomatis dienkripsi dengan <code>password_hash()</code> dan tidak disimpan plaintext.
          </small>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
          <button type="button" class="btn-action-sm" onclick="closeModal('modalAddGuru')">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto; margin-top:0;">Simpan Akun</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==========================================
       MODAL 2: EDIT DATA GURU / STAFF
       ========================================== -->
  <div class="modal-backdrop" id="modalEditGuru">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('edit', '', 18) ?> Edit Data Staff / Guru</div>
        <button type="button" class="modal-close-btn" onclick="closeModal('modalEditGuru')">&times;</button>
      </div>
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="edit_guru">
        <input type="hidden" name="current_tab" value="section-guru">
        <input type="hidden" name="guru_id" id="edit_guru_id">

        <div class="form-group">
          <label class="form-label" for="edit_guru_role">Peran / Role Akun *</label>
          <select name="role" id="edit_guru_role" class="form-control" required>
            <option value="guru">Guru Pembimbing Sekolah</option>
            <option value="guru_industri">Guru Pembimbing Industri</option>
            <option value="penguji">Penguji Sidang PKL</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="edit_guru_username">Username *</label>
          <input type="text" name="username" id="edit_guru_username" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="edit_guru_email">Email Akun *</label>
          <input type="email" name="email" id="edit_guru_email" class="form-control" required>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
          <button type="button" class="btn-action-sm" onclick="closeModal('modalEditGuru')">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto; margin-top:0;">Perbarui Data</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==========================================
       MODAL 3: RESET PASSWORD GURU / STAFF
       ========================================== -->
  <div class="modal-backdrop" id="modalResetPassword">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('key', '', 18) ?> Reset Password Staff / Guru</div>
        <button type="button" class="modal-close-btn" onclick="closeModal('modalResetPassword')">&times;</button>
      </div>
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="reset_password_guru">
        <input type="hidden" name="current_tab" value="section-guru">
        <input type="hidden" name="guru_id" id="reset_guru_id">

        <p style="font-size: 0.9rem; color: var(--color-muted); margin-bottom: 1rem;">
          Reset password untuk akun <strong id="reset_guru_name" style="color: var(--color-text);"></strong>:
        </p>

        <div class="form-group">
          <label class="form-label" for="reset_new_password">Password Baru *</label>
          <div class="input-wrapper">
            <input type="password" name="new_password" id="reset_new_password" class="form-control" required minlength="6" placeholder="Masukkan password baru">
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('reset_new_password')"><?= pkl_icon('eye', '', 18) ?></button>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
          <button type="button" class="btn-action-sm" onclick="closeModal('modalResetPassword')">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto; margin-top:0;">Reset Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==========================================
       MODAL 4: ATUR PEMBIMBING & TEMPAT PKL MURID
       ========================================== -->
  <div class="modal-backdrop" id="modalAssignMurid">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('pembimbing_sekolah', '', 18) ?> Penetapan Pembimbing, Penguji & Lokasi PKL</div>
        <button type="button" class="modal-close-btn" onclick="closeModal('modalAssignMurid')">&times;</button>
      </div>
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="assign_guru">
        <input type="hidden" name="current_tab" id="assign_current_tab" value="section-pembimbing">

        <div class="form-group">
          <label class="form-label" for="assign_student_id">Pilih Murid *</label>
          <select name="student_id" id="assign_student_id" class="form-control" required onchange="onStudentSelectChange(this)">
            <option value="">-- Pilih Murid yang Akan Ditugaskan --</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= (int)$st['id'] ?>" data-name="<?= admin_h($st['username']) ?>" data-guru="<?= (int)($st['guru_id'] ?? 0) ?>" data-industri="<?= (int)($st['industri_id'] ?? 0) ?>" data-penguji="<?= (int)($st['penguji_id'] ?? 0) ?>" data-company="<?= (int)($st['company_id'] ?? 0) ?>">
                <?= admin_h($st['username']) ?> (<?= admin_h($st['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="assign_guru_id">1. Guru Pembimbing Sekolah</label>
          <select name="guru_id" id="assign_guru_id" class="form-control">
            <option value="">-- Kosongkan / Belum Ada Pembimbing Sekolah --</option>
            <?php foreach ($allTeachers as $g): ?>
              <option value="<?= (int)$g['id'] ?>">
                <?= admin_h($g['username']) ?> (<?= (int)$g['total_bimbingan'] ?> murid)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem; display: block;">
            Membimbing, memvalidasi jurnal mingguan, dan membina siswa selama PKL.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="assign_industri_id">2. Guru Pembimbing Industri (DUDI)</label>
          <select name="industri_id" id="assign_industri_id" class="form-control">
            <option value="">-- Kosongkan / Belum Ada Pembimbing Industri --</option>
            <?php foreach ($allIndustri as $ind): ?>
              <option value="<?= (int)$ind['id'] ?>">
                <?= admin_h($ind['username']) ?> (<?= (int)$ind['total_bimbingan'] ?> murid)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem; display: block;">
            Mentor di tempat PKL yang menilai kedisiplinan, kehadiran, dan hasil kerja siswa.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="assign_penguji_id">3. Penguji Sidang PKL</label>
          <select name="penguji_id" id="assign_penguji_id" class="form-control">
            <option value="">-- Kosongkan / Belum Ada Penguji Sidang --</option>
            <?php foreach ($allPenguji as $pj): ?>
              <option value="<?= (int)$pj['id'] ?>">
                <?= admin_h($pj['username']) ?> (<?= (int)$pj['total_bimbingan'] ?> murid)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="font-size: 0.75rem; color: var(--color-muted); margin-top: 0.25rem; display: block;">
            Menelaah jurnal & laporan PKL serta memberikan nilai sidang ujian PKL.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="assign_company_id">4. Perusahaan / Lokasi PKL (Opsional)</label>
          <select name="company_id" id="assign_company_id" class="form-control">
            <option value="">-- Pertahankan / Belum Ditentukan --</option>
            <?php foreach ($allCompanies as $c): ?>
              <option value="<?= (int)$c['id'] ?>">
                <?= admin_h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
          <button type="button" class="btn-action-sm" onclick="closeModal('modalAssignMurid')">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto; margin-top:0;">Simpan Penugasan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==========================================
       MODAL 5: TAMBAH PERUSAHAAN PKL
       ========================================== -->
  <div class="modal-backdrop" id="modalAddCompany">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('plus', '', 18) ?> Tambah Perusahaan Mitra PKL</div>
        <button type="button" class="modal-close-btn" onclick="closeModal('modalAddCompany')">&times;</button>
      </div>
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="add_company">
        <input type="hidden" name="current_tab" value="section-perusahaan">

        <div class="form-group">
          <label class="form-label" for="add_company_name">Nama Perusahaan / Instansi *</label>
          <input type="text" name="name" id="add_company_name" class="form-control" required placeholder="Contoh: PT Telkom Indonesia">
        </div>

        <div class="form-group">
          <label class="form-label" for="add_company_address">Alamat Perusahaan</label>
          <textarea name="address" id="add_company_address" class="form-control" rows="3" placeholder="Jl. Raya No. 1, Kota ..."></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="add_company_phone">No. Telepon / Kontak</label>
          <input type="text" name="phone" id="add_company_phone" class="form-control" placeholder="021-8841000 / 0812...">
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
          <button type="button" class="btn-action-sm" onclick="closeModal('modalAddCompany')">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto; margin-top:0;">Simpan Perusahaan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==========================================
       MODAL 6: EDIT PERUSAHAAN PKL
       ========================================== -->
  <div class="modal-backdrop" id="modalEditCompany">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title"><?= pkl_icon('edit', '', 18) ?> Edit Data Perusahaan PKL</div>
        <button type="button" class="modal-close-btn" onclick="closeModal('modalEditCompany')">&times;</button>
      </div>
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="edit_company">
        <input type="hidden" name="current_tab" value="section-perusahaan">
        <input type="hidden" name="company_id" id="edit_company_id">

        <div class="form-group">
          <label class="form-label" for="edit_company_name">Nama Perusahaan *</label>
          <input type="text" name="name" id="edit_company_name" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="edit_company_address">Alamat Perusahaan</label>
          <textarea name="address" id="edit_company_address" class="form-control" rows="3"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="edit_company_phone">No. Telepon / Kontak</label>
          <input type="text" name="phone" id="edit_company_phone" class="form-control">
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
          <button type="button" class="btn-action-sm" onclick="closeModal('modalEditCompany')">Batal</button>
          <button type="submit" class="btn-primary" style="width:auto; margin-top:0;">Perbarui Perusahaan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==========================================
       JAVASCRIPT DASHBOARD ADMIN
       ========================================== -->
  <script>
    // Tab Switching Navigation
    function switchTab(targetId) {
      if (!targetId) return;
      if (!targetId.startsWith('section-')) {
        targetId = 'section-' + targetId;
      }
      const targetView = document.getElementById(targetId);
      if (!targetView) return;

      document.querySelectorAll('.nav-link-item').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-target') === targetId);
      });

      document.querySelectorAll('.content-tab-view').forEach(view => {
        if (view.id === targetId) {
          view.classList.add('active');
          view.removeAttribute('hidden');
        } else {
          view.classList.remove('active');
          view.setAttribute('hidden', 'true');
        }
      });

      // Update URL hash
      if (history.replaceState) {
        history.replaceState(null, null, '#' + targetId);
      } else {
        window.location.hash = targetId;
      }

      // Close mobile sidebar and backdrop if open
      const sidebar = document.getElementById('app-sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
      }
      if (backdrop && backdrop.classList.contains('active')) {
        backdrop.classList.remove('active');
      }

      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Attach click listeners to sidebar tabs
    document.querySelectorAll('.nav-link-item').forEach(btn => {
      btn.addEventListener('click', function(e) {
        const target = this.getAttribute('data-target');
        if (target) {
          switchTab(target);
        }
      });
    });

    // Handle hash on page load and hash change
    window.addEventListener('DOMContentLoaded', () => {
      const hash = window.location.hash.substring(1);
      if (hash && document.getElementById(hash)) {
        switchTab(hash);
      } else {
        const activeView = document.querySelector('.content-tab-view.active');
        if (activeView) {
          switchTab(activeView.id);
        } else {
          switchTab('section-dashboard');
        }
      }
    });

    window.addEventListener('hashchange', () => {
      const hash = window.location.hash.substring(1);
      if (hash && document.getElementById(hash)) {
        switchTab(hash);
      }
    });

    // Mobile Sidebar Toggle and Backdrop
    const mobileBtn = document.getElementById('mobile-menu-toggle');
    const appSidebar = document.getElementById('app-sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    if (mobileBtn && appSidebar) {
      mobileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = appSidebar.classList.toggle('open');
        if (sidebarBackdrop) {
          sidebarBackdrop.classList.toggle('active', isOpen);
        }
      });
    }
    if (sidebarBackdrop) {
      sidebarBackdrop.addEventListener('click', () => {
        if (appSidebar) appSidebar.classList.remove('open');
        sidebarBackdrop.classList.remove('active');
      });
    }

    // Modal Control Functions
    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('active');
      }
    }

    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('active');
      }
    }

    // Close modal when clicking backdrop
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          this.classList.remove('active');
        }
      });
    });

    // Modal Triggers
    function openAddGuruModal() {
      document.getElementById('add_guru_role').value = 'guru';
      document.getElementById('add_guru_username').value = '';
      document.getElementById('add_guru_email').value = '';
      document.getElementById('add_guru_password').value = '';
      openModal('modalAddGuru');
    }

    function openEditGuruModal(id, username, email, role) {
      document.getElementById('edit_guru_id').value = id;
      document.getElementById('edit_guru_username').value = username;
      document.getElementById('edit_guru_email').value = email;
      const roleSelect = document.getElementById('edit_guru_role');
      if (roleSelect) {
        roleSelect.value = role || 'guru';
      }
      openModal('modalEditGuru');
    }

    function openResetPasswordModal(id, username) {
      document.getElementById('reset_guru_id').value = id;
      document.getElementById('reset_guru_name').innerText = username;
      document.getElementById('reset_new_password').value = '';
      openModal('modalResetPassword');
    }

    function openAssignModal(studentId, studentName, guruId, industriId, pengujiId, companyId, currentTab) {
      const studentSelect = document.getElementById('assign_student_id');
      if (studentSelect) {
        studentSelect.value = studentId || '';
      }
      const guruSelect = document.getElementById('assign_guru_id');
      if (guruSelect) {
        guruSelect.value = guruId || '';
      }
      const industriSelect = document.getElementById('assign_industri_id');
      if (industriSelect) {
        industriSelect.value = industriId || '';
      }
      const pengujiSelect = document.getElementById('assign_penguji_id');
      if (pengujiSelect) {
        pengujiSelect.value = pengujiId || '';
      }
      const compSelect = document.getElementById('assign_company_id');
      if (compSelect) {
        compSelect.value = companyId || '';
      }
      const tabInput = document.getElementById('assign_current_tab');
      if (tabInput) {
        tabInput.value = currentTab || 'section-pembimbing';
      }
      openModal('modalAssignMurid');
    }

    function onStudentSelectChange(selectEl) {
      const selectedOption = selectEl.options[selectEl.selectedIndex];
      if (selectedOption && selectedOption.value) {
        const defaultGuru = selectedOption.getAttribute('data-guru') || '';
        const defaultIndustri = selectedOption.getAttribute('data-industri') || '';
        const defaultPenguji = selectedOption.getAttribute('data-penguji') || '';
        const defaultComp = selectedOption.getAttribute('data-company') || '';
        document.getElementById('assign_guru_id').value = defaultGuru;
        document.getElementById('assign_industri_id').value = defaultIndustri;
        document.getElementById('assign_penguji_id').value = defaultPenguji;
        document.getElementById('assign_company_id').value = defaultComp;
      }
    }

    function openAddCompanyModal() {
      document.getElementById('add_company_name').value = '';
      document.getElementById('add_company_address').value = '';
      document.getElementById('add_company_phone').value = '';
      openModal('modalAddCompany');
    }

    function openEditCompanyModal(id, name, address, phone) {
      document.getElementById('edit_company_id').value = id;
      document.getElementById('edit_company_name').value = name;
      document.getElementById('edit_company_address').value = address;
      document.getElementById('edit_company_phone').value = phone;
      openModal('modalEditCompany');
    }

    function togglePasswordVisibility(inputId) {
      const input = document.getElementById(inputId);
      if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
      }
    }

    // Live Table Filtering
    function filterTable(tableId, query) {
      const table = document.getElementById(tableId);
      if (!table) return;
      const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
      const q = query.toLowerCase().trim();

      for (let i = 0; i < rows.length; i++) {
        const text = rows[i].textContent.toLowerCase();
        if (text.includes(q)) {
          rows[i].style.display = '';
        } else {
          rows[i].style.display = 'none';
        }
      }
    }

    // Filter Pembimbing Status (All / Assigned / Unassigned)
    function filterPembimbingStatus(status, btnEl) {
      const table = document.getElementById('tablePembimbing');
      if (!table) return;
      const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

      for (let i = 0; i < rows.length; i++) {
        const rowStatus = rows[i].getAttribute('data-status');
        if (status === 'all' || rowStatus === status) {
          rows[i].style.display = '';
        } else {
          rows[i].style.display = 'none';
        }
      }

      if (btnEl) {
        const parent = btnEl.parentElement;
        if (parent) {
          parent.querySelectorAll('.btn-action-sm').forEach(b => b.classList.remove('primary'));
          btnEl.classList.add('primary');
        }
      }
    }
    // ===== Penugasan Pembimbing Massal =====
    function filterBulkStudents() {
      const q = (document.getElementById('bulkSearchInput') || {}).value || '';
      const kelas = (document.getElementById('bulkClassFilter') || {}).value || '';
      const query = q.toLowerCase().trim();
      const rows = document.querySelectorAll('#tableBulkStudents tbody tr');
      rows.forEach(function(row) {
        const matchSearch = !query || (row.getAttribute('data-search') || '').includes(query);
        const matchKelas = !kelas || row.getAttribute('data-kelas') === kelas;
        row.style.display = (matchSearch && matchKelas) ? '' : 'none';
      });
      updateBulkSelectedCount();
    }
    function visibleBulkRows() {
      return Array.prototype.slice.call(document.querySelectorAll('#tableBulkStudents tbody tr'))
        .filter(function(tr) { return tr.style.display !== 'none'; });
    }
    function toggleBulkSelectAll(src) {
      const checked = src.checked;
      visibleBulkRows().forEach(function(tr) {
        const cb = tr.querySelector('.bulk-student-chk');
        if (cb) cb.checked = checked;
      });
      const head = document.getElementById('bulkSelectAllHead');
      const filt = document.getElementById('bulkSelectAll');
      if (src.id === 'bulkSelectAll' && head) head.checked = checked;
      if (src.id === 'bulkSelectAllHead' && filt) filt.checked = checked;
      updateBulkSelectedCount();
    }
    function updateBulkSelectedCount() {
      const n = document.querySelectorAll('#tableBulkStudents .bulk-student-chk:checked').length;
      const el = document.getElementById('bulkSelectedCount');
      if (el) el.textContent = n + ' siswa dipilih';
      const vis = visibleBulkRows();
      const allChecked = vis.length > 0 && vis.every(function(tr) { const cb = tr.querySelector('.bulk-student-chk'); return cb && cb.checked; });
      const head = document.getElementById('bulkSelectAllHead'); if (head) head.checked = allChecked;
      const filt = document.getElementById('bulkSelectAll'); if (filt) filt.checked = allChecked;
    }
    function validateBulkAssign(e) {
      const guru = (document.getElementById('bulk_guru_id') || {}).value || '';
      const ind = (document.getElementById('bulk_industri_id') || {}).value || '';
      const n = document.querySelectorAll('#tableBulkStudents .bulk-student-chk:checked').length;
      if (!guru && !ind) { alert('Pilih minimal satu pembimbing (Sekolah atau Industri).'); e.preventDefault(); return false; }
      if (n === 0) { alert('Pilih minimal satu siswa melalui checkbox.'); e.preventDefault(); return false; }
      return true;
    }

    // ===== Penugasan Penguji Massal (Produktif & Jurnal) =====
    function filterExaminerStudents() {
      const q = (document.getElementById('examinerSearchInput') || {}).value || '';
      const kelas = (document.getElementById('examinerClassFilter') || {}).value || '';
      const statusFilter = (document.getElementById('examinerStatusFilter') || {}).value || '';
      const category = (document.getElementById('bulk_category') || {}).value || '';
      const query = q.toLowerCase().trim();
      document.querySelectorAll('#tableExaminerStudents tbody tr').forEach(function(row) {
        const matchSearch = !query || (row.getAttribute('data-search') || '').includes(query);
        const matchKelas  = !kelas || row.getAttribute('data-kelas') === kelas;
        let matchStatus = true;
        if (statusFilter === 'assigned') {
          matchStatus = category === 'jurnal'
            ? row.getAttribute('data-jurnal') === '1'
            : row.getAttribute('data-produktif') === '1';
        } else if (statusFilter === 'unassigned') {
          matchStatus = category === 'jurnal'
            ? row.getAttribute('data-jurnal') !== '1'
            : row.getAttribute('data-produktif') !== '1';
        }
        row.style.display = (matchSearch && matchKelas && matchStatus) ? '' : 'none';
      });
      updateExaminerSelectedCount();
    }
    function visibleExaminerRows() {
      return Array.prototype.slice.call(
        document.querySelectorAll('#tableExaminerStudents tbody tr')
      ).filter(function(tr) { return tr.style.display !== 'none'; });
    }
    function toggleExaminerSelectAll(src) {
      const checked = src.checked;
      visibleExaminerRows().forEach(function(tr) {
        const cb = tr.querySelector('.examiner-student-chk');
        if (cb) cb.checked = checked;
      });
      const head = document.getElementById('examinerSelectAllHead');
      const filt = document.getElementById('examinerSelectAll');
      if (src.id === 'examinerSelectAll' && head) head.checked = checked;
      if (src.id === 'examinerSelectAllHead' && filt) filt.checked = checked;
      updateExaminerSelectedCount();
    }
    function updateExaminerSelectedCount() {
      const n = document.querySelectorAll('#tableExaminerStudents .examiner-student-chk:checked').length;
      const el = document.getElementById('examinerSelectedCount');
      if (el) el.textContent = n + ' siswa dipilih';
      const vis = visibleExaminerRows();
      const allChecked = vis.length > 0 && vis.every(function(tr) {
        const cb = tr.querySelector('.examiner-student-chk');
        return cb && cb.checked;
      });
      const head = document.getElementById('examinerSelectAllHead');
      const filt = document.getElementById('examinerSelectAll');
      if (head) head.checked = allChecked;
      if (filt) filt.checked = allChecked;
    }
    function validateExaminerBulk(e) {
      const pj = (document.getElementById('bulk_penguji_id') || {}).value || '';
      const cat = (document.getElementById('bulk_category') || {}).value || '';
      const n = document.querySelectorAll('#tableExaminerStudents .examiner-student-chk:checked').length;
      if (!pj) { alert('Pilih penguji terlebih dahulu.'); e.preventDefault(); return false; }
      if (!cat) { alert('Pilih kategori (Produktif atau Jurnal).'); e.preventDefault(); return false; }
      if (n === 0) { alert('Pilih minimal satu siswa melalui checkbox.'); e.preventDefault(); return false; }
      return true;
    }
  </script>

</body>
</html>
