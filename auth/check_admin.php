<?php
// auth/check_admin.php
// File penjaga hak akses khusus Admin PKL Journey

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
    // 2. Verifikasi langsung ke database untuk integritas data
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

    // 3. Cek apakah role di database benar-benar 'admin'
    if ($currentUser['role'] !== 'admin') {
        // Akses ilegal: redirect sesuai role sebenarnya di DB
        $roleRedirects = [
            'murid'         => '../dashboard/murid.php',
            'guru'          => '../dashboard/guru.php',
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
    $_SESSION['school_name'] = $currentUser['school_name'] ?? 'Pusat Admin';

    // Variabel aman untuk digunakan di halaman admin
    $admin_id          = (int)$currentUser['id'];
    $admin_username    = $currentUser['username'];
    $admin_email       = $currentUser['email'];
    $admin_school_id   = (int)($currentUser['school_id'] ?: 1);
    $admin_school_name = $currentUser['school_name'] ?? 'SMK Telekomunikasi Telesandi Bekasi';

} catch (Exception $e) {
    die("Gagal memvalidasi autentikasi admin: " . htmlspecialchars($e->getMessage()));
}
