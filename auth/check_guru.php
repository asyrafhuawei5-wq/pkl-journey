<?php
// auth/check_guru.php
// File penjaga hak akses khusus Guru

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
    // 2. Verifikasi langsung ke database (jangan hanya percaya data session mentah)
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.role, u.school_id, s.name AS school_name 
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

    // 3. Cek apakah role di database benar-benar 'guru'
    if ($currentUser['role'] !== 'guru') {
        // Akses ilegal: redirect sesuai role sebenarnya di DB
        $roleRedirects = [
            'murid'         => '../dashboard/murid.php',
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
    $_SESSION['username']    = $currentUser['username'];
    $_SESSION['role']        = $currentUser['role'];
    $_SESSION['school_id']   = $currentUser['school_id'];
    $_SESSION['school_name'] = $currentUser['school_name'] ?? 'Sekolah Terdaftar';

    // Variabel aman untuk digunakan di halaman guru
    $guru_id          = (int)$currentUser['id'];
    $guru_username    = $currentUser['username'];
    $guru_email       = $currentUser['email'];
    $guru_school_id   = (int)$currentUser['school_id'];
    $guru_school_name = $currentUser['school_name'] ?? 'Sekolah Terdaftar';

} catch (Exception $e) {
    die("Gagal memvalidasi autentikasi guru: " . htmlspecialchars($e->getMessage()));
}
