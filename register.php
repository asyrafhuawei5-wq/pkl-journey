<?php
// register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

// Blokir pendaftaran mandiri untuk publik. Hanya proses internal Admin yang diizinkan.
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Pendaftaran mandiri dinonaktifkan. Seluruh akun dikelola oleh Administrator.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Metode request tidak valid.'
    ]);
    exit;
}

// Get input data
$username         = trim($_POST['username'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// If empty from POST, check JSON input
if (empty($username) && empty($email)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data) {
        $username         = trim($data['username'] ?? '');
        $email            = trim($data['email'] ?? '');
        $password         = $data['password'] ?? '';
        $confirm_password = $data['confirm_password'] ?? '';
    }
}

// 1. Validation: all required fields
if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Semua kolom wajib diisi.'
    ]);
    exit;
}

// 2. Validation: valid email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Format email tidak valid.'
    ]);
    exit;
}

// 3. Validation: password confirmation match
if ($password !== $confirm_password) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Konfirmasi password tidak cocok.'
    ]);
    exit;
}

// 4. Validation: minimum password length
if (strlen($password) < 6) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password minimal harus 6 karakter.'
    ]);
    exit;
}

try {
    // 5. Validation: check if username or email already exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->execute([
        ':username' => $username,
        ':email'    => $email
    ]);
    $existing = $stmt->fetch();

    if ($existing) {
        if (strtolower($existing['username']) === strtolower($username)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Username sudah digunakan, silakan pilih yang lain.'
            ]);
            exit;
        }
        if (strtolower($existing['email']) === strtolower($email)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email sudah terdaftar.'
            ]);
            exit;
        }
    }

    // 6. Default parameters: SMK Telekomunikasi Telesandi Bekasi (school_id = 1), Role = murid
    $school_id = 1;
    $role = 'murid';

    // 7. Simpan user dengan password_hash()
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $insertStmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, school_id, created_at)
        VALUES (:username, :email, :password, :role, :school_id, NOW())
    ");

    $insertStmt->execute([
        ':username'  => $username,
        ':email'     => $email,
        ':password'  => $hashedPassword,
        ':role'      => $role,
        ':school_id' => $school_id
    ]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Journey Started ✓'
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mendaftar: ' . $e->getMessage()
    ]);
    exit;
}
