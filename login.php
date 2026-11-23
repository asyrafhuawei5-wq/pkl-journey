<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Metode request tidak valid.'
    ]);
    exit;
}

// Get input (supports FormData & JSON body)
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role     = trim($_POST['role'] ?? '');

// If empty from POST, check JSON input
if (empty($username) && empty($password)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data) {
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role     = trim($data['role'] ?? '');
    }
}

// Validation: check empty fields
if ($username === '' || $password === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'NIS / Username dan password wajib diisi.'
    ]);
    exit;
}

try {
    // 1. Fetch user by username (NIS) or email with school data
    $stmt = $pdo->prepare("
        SELECT u.*, s.name AS school_name 
        FROM users u 
        LEFT JOIN schools s ON u.school_id = s.id 
        WHERE LOWER(u.username) = LOWER(:username) OR LOWER(u.email) = LOWER(:email) 
        LIMIT 1
    ");
    $stmt->execute([
        ':username' => $username,
        ':email'    => $username
    ]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode([
            'status' => 'error',
            'message' => 'NIS atau Username tidak ditemukan.'
        ]);
        exit;
    }

    // 2. Verifikasi Password dengan password_verify()
    $passwordValid = false;
    if (password_verify($password, $user['password'])) {
        $passwordValid = true;
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $rehashStmt = $pdo->prepare("UPDATE users SET password = :pwd WHERE id = :id");
            $rehashStmt->execute([':pwd' => $newHash, ':id' => $user['id']]);
        }
    } elseif ($password === $user['password'] || trim($password) === trim($user['password'])) {
        $passwordValid = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehashStmt = $pdo->prepare("UPDATE users SET password = :pwd WHERE id = :id");
        $rehashStmt->execute([':pwd' => $newHash, ':id' => $user['id']]);
    }

    if (!$passwordValid) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Password yang dimasukkan salah.'
        ]);
        exit;
    }

    // 3. Sistem otomatis mengenali role dari database
    $userRole = strtolower($user['role']);

    // 4. Setup PHP session
    $_SESSION['user_id']              = (int)$user['id'];
    $_SESSION['username']             = $user['username'];
    $_SESSION['email']                = $user['email'];
    $_SESSION['role']                 = $user['role'];
    $_SESSION['nama']                 = $user['nama'] ?? '';
    $_SESSION['kelas']                = $user['kelas'] ?? '';
    $_SESSION['jurusan']              = $user['jurusan'] ?? '';
    $_SESSION['school_id']            = (int)$user['school_id'];
    $_SESSION['school_name']          = $user['school_name'] ?? 'Sekolah Terdaftar';
    $_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);

    // 5. Target dashboard redirect sesuai role
    $redirects = [
        'murid'         => 'dashboard/murid.php',
        'guru'          => 'dashboard/guru.php',
        'admin'         => 'dashboard/admin.php',
        'guru_industri' => 'dashboard/industri.php',
        'penguji'       => 'dashboard/penguji.php'
    ];

    $redirectUrl = $redirects[$user['role']] ?? 'dashboard/murid.php';

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Journey ready ✓',
        'redirect' => $redirectUrl,
        'user'     => [
            'username'    => $user['username'],
            'nama'        => $user['nama'] ?? '',
            'role'        => $user['role'],
            'school_name' => $user['school_name'] ?? 'Sekolah Terdaftar'
        ]
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
    exit;
}
