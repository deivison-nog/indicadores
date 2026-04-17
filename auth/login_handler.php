<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    $_SESSION['flash_message'] = 'Preencha e-mail e senha.';
    $_SESSION['flash_type']    = 'danger';
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

try {
    $pdo = Database::getInstance();

    // Check administrators first
    $stmt = $pdo->prepare('SELECT id, name, password_hash FROM administrators WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $admin['id'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['user_name'] = $admin['name'];
        $_SESSION['admin_id']  = $admin['id'];
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
        exit;
    }

    // Check users table
    $stmt = $pdo->prepare('SELECT id, name, password_hash, admin_id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_type'] = 'user';
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['admin_id']  = $user['admin_id'];
        header('Location: ' . BASE_URL . 'user/view.php');
        exit;
    }

    $_SESSION['flash_message'] = 'E-mail ou senha incorretos.';
    $_SESSION['flash_type']    = 'danger';
    header('Location: ' . BASE_URL . 'index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = 'Erro de conexão com o banco de dados. Tente novamente.';
    $_SESSION['flash_type']    = 'danger';
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
