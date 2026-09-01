<?php
// admin/auth.php
// Pengendali autentikasi login dan logout petugas admin

session_start();
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

// 1. Proses Login Admin
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Username dan password wajib diisi!";
        header("Location: login.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM tbl_admin WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        // Simpan sesi login admin
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_nama']      = $admin['nama_lengkap'];
        $_SESSION['admin_msg']       = "Selamat datang kembali, " . htmlspecialchars($admin['nama_lengkap']) . "!";
        
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['login_error'] = "Username atau password salah!";
        header("Location: login.php");
        exit;
    }
}

// 2. Proses Logout Admin
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit;
}

header("Location: login.php");
exit;