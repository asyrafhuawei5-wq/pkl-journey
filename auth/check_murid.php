<?php
// auth/check_murid.php
// File penjaga hak akses khusus Murid / Siswa

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// 1. Cek keberadaan session login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit;
}

$session_user_id = $_SESSION['user_id'];

try {
    // 2. Verifikasi langsung ke database (tidak mempercayai frontend atau session mentah saja)
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.nama, u.kelas, u.jurusan, u.email, u.role, u.school_id, u.must_change_password, s.name AS school_name 
        FROM users u
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $session_user_id]);
    $currentUser = $stmt->fetch();

    // Jika user tidak ditemukan di database
    if (!$currentUser) {
        session_unset();
        session_destroy();
        header("Location: ../index.php");
        exit;
    }

    // 3. Cek apakah role di database benar-benar 'murid'
    if ($currentUser['role'] !== 'murid') {
        // Akses ilegal: redirect sesuai role sebenarnya di DB
        $roleRedirects = [
            'guru'          => '../dashboard/guru.php',
            'admin'         => '../dashboard/admin.php',
            'guru_industri' => '../dashboard/industri.php',
            'penguji'       => '../dashboard/penguji.php'
        ];
        if (isset($roleRedirects[$currentUser['role']])) {
            header("Location: " . $roleRedirects[$currentUser['role']]);
            exit;
        } else {
            session_unset();
            session_destroy();
            header("Location: ../index.php");
            exit;
        }
    }

    // 4. Sinkronisasi data session dengan database terkini
    $_SESSION['username']             = $currentUser['username'];
    $_SESSION['role']                 = $currentUser['role'];
    $_SESSION['nama']                 = $currentUser['nama'] ?? '';
    $_SESSION['kelas']                = $currentUser['kelas'] ?? '';
    $_SESSION['jurusan']              = $currentUser['jurusan'] ?? '';
    $_SESSION['school_id']            = $currentUser['school_id'];
    $_SESSION['school_name']          = $currentUser['school_name'] ?? 'Sekolah Terdaftar';
    $_SESSION['must_change_password'] = (int)($currentUser['must_change_password'] ?? 0);

    // Variabel aman untuk digunakan di halaman murid
    $murid_id                  = (int)$currentUser['id'];
    $murid_username            = $currentUser['username'];
    $murid_nama                = $currentUser['nama'] ?? '';
    $murid_kelas               = $currentUser['kelas'] ?? '';
    $murid_jurusan             = $currentUser['jurusan'] ?? '';
    $murid_email               = $currentUser['email'];
    $murid_school_id           = (int)$currentUser['school_id'];
    $murid_school_name         = $currentUser['school_name'] ?? 'Sekolah Terdaftar';
    $murid_must_change_password = (int)($currentUser['must_change_password'] ?? 0);

} catch (Exception $e) {
    die("Gagal memvalidasi autentikasi murid: " . htmlspecialchars($e->getMessage()));
}
