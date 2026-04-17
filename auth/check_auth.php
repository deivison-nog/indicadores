<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user_type'])) {
    $_SESSION['flash_message'] = 'Por favor, faça login para continuar.';
    $_SESSION['flash_type']    = 'warning';
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Admins only guard — include after check_auth.php when needed
// if ($_SESSION['user_type'] !== 'admin') { header('Location: ' . BASE_URL . 'user/view.php'); exit; }

$currentUser = [
    'id'       => $_SESSION['user_id'],
    'type'     => $_SESSION['user_type'],
    'name'     => $_SESSION['user_name'],
    'admin_id' => $_SESSION['admin_id'],
];
