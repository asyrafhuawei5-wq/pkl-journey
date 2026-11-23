<?php
// config/database.example.php
//
// Contoh konfigurasi koneksi database.
// Salin file ini menjadi config/database.php, lalu sesuaikan nilainya.
// Jangan meng-commit config/database.php karena berisi kredensial lokal.

$host     = 'localhost';
$db       = 'smart_pkl';
$user     = 'pkl_user';
$pass     = 'ubah_password_database_ini';
$charset  = 'utf8mb4';


$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

/**
 * Pastikan riwayat penilaian tidak dibatasi satu baris per siswa.
 * Indeks unik lama (student_id) diganti menjadi (student_id, mentor_id)
 * agar penilai baru tidak menimpa penilaian penilai sebelumnya.
 */
function pkl_ensure_assessment_history_schema(PDO $pdo, string $table, string $mentorColumn, string $uniqueName): void
{
    try {
        $indexRows = $pdo->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return;
    }

    $indexes = [];
    foreach ($indexRows as $row) {
        $keyName = (string)($row['Key_name'] ?? '');
        if ($keyName === '') {
            continue;
        }
        if (!isset($indexes[$keyName])) {
            $indexes[$keyName] = [
                'non_unique' => (int)($row['Non_unique'] ?? 1),
                'columns'    => [],
            ];
        }
        $indexes[$keyName]['columns'][] = (string)($row['Column_name'] ?? '');
    }

    $hasComposite = false;
    $singleColumnUnique = null;
    foreach ($indexes as $keyName => $index) {
        if ($index['non_unique'] !== 0) {
            continue;
        }
        if ($index['columns'] === ['student_id', $mentorColumn]) {
            $hasComposite = true;
        } elseif ($index['columns'] === ['student_id']) {
            $singleColumnUnique = $keyName;
        }
    }

    try {
        if (!$hasComposite) {
            $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$uniqueName}` (`student_id`, `{$mentorColumn}`)");
        }
        if ($singleColumnUnique !== null) {
            $safeIndexName = str_replace('`', '``', $singleColumnUnique);
            $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$safeIndexName}`");
        }
    } catch (Exception $e) {
        // Jika ALTER tidak diizinkan, biarkan skema lama tetap aktif.
    }
}

/**
 * Pastikan ENUM role dan kolom penugasan industri/penguji tersedia
 * tanpa menghapus data lama.
 */
function pkl_ensure_staff_schema(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $roleCol  = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        $roleType = $roleCol ? (string)$roleCol['Type'] : '';
        if ($roleCol && (stripos($roleType, 'guru_industri') === false || stripos($roleType, 'penguji') === false)) {
            $pdo->exec("ALTER TABLE `users` MODIFY `role` ENUM('murid','guru','admin','guru_industri','penguji') NOT NULL DEFAULT 'murid'");
        }

        $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($userCols)) {
            if (!in_array('nama', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `nama` VARCHAR(100) NULL AFTER `username`");
            }
            if (!in_array('kelas', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `kelas` VARCHAR(50) NULL AFTER `nama`");
            }
            if (!in_array('jurusan', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `jurusan` VARCHAR(100) NULL AFTER `kelas`");
            }
            if (!in_array('must_change_password', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `jurusan`");
            }
        }
    } catch (Exception $e) {
        // Abaikan jika tabel users belum ada
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `internships` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `student_id` INT NOT NULL UNIQUE,
              `guru_id` INT NULL,
              `industri_id` INT NULL,
              `penguji_id` INT NULL,
              `company_id` INT NULL,
              `school_id` INT NOT NULL DEFAULT 1,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
        // Abaikan jika tabel sudah ada
    }

    try {
        $internshipCols = $pdo->query("SHOW COLUMNS FROM internships")->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($internshipCols) && !in_array('industri_id', $internshipCols, true)) {
            $pdo->exec("ALTER TABLE `internships` ADD COLUMN `industri_id` INT NULL AFTER `guru_id`");
        }
        $internshipCols = $pdo->query("SHOW COLUMNS FROM internships")->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($internshipCols) && !in_array('penguji_id', $internshipCols, true)) {
            $pdo->exec("ALTER TABLE `internships` ADD COLUMN `penguji_id` INT NULL AFTER `industri_id`");
        }
    } catch (Exception $e) {
        // Abaikan jika kolom sudah ada
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `industry_assessments` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `student_id` INT NOT NULL,
              `industri_id` INT NOT NULL,
              `score_discipline` INT NOT NULL DEFAULT 0,
              `score_attendance` INT NOT NULL DEFAULT 0,
              `score_work_quality` INT NOT NULL DEFAULT 0,
              `final_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
              `feedback` TEXT NULL,
              `assessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `uq_industry_assessment_student_industri` (`student_id`, `industri_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `sidang_assessments` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `student_id` INT NOT NULL,
              `penguji_id` INT NOT NULL,
              `score_understanding` INT NOT NULL DEFAULT 0,
              `score_presentation` INT NOT NULL DEFAULT 0,
              `score_qa` INT NOT NULL DEFAULT 0,
              `final_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
              `feedback` TEXT NULL,
              `assessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `uq_sidang_assessment_student_penguji` (`student_id`, `penguji_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
        // Abaikan jika tabel penilaian sudah tersedia
    }

    pkl_ensure_assessment_history_schema($pdo, 'industry_assessments', 'industri_id', 'uq_industry_assessment_student_industri');
    pkl_ensure_assessment_history_schema($pdo, 'sidang_assessments', 'penguji_id', 'uq_sidang_assessment_student_penguji');
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    pkl_ensure_staff_schema($pdo);
} catch (PDOException $e) {
    // If request is JSON / AJAX, return JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' 
        || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Koneksi database gagal: ' . $e->getMessage()
        ]);
        exit;
    }
    die("Koneksi Database Gagal: " . $e->getMessage() . "<br><small>Pastikan MySQL aktif dan database 'smart_pkl' sudah diimpor.</small>");
}
