<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → redirect
if (!empty($_SESSION['user_id'])) {
    $dest = $_SESSION['user_type'] === 'admin'
        ? BASE_URL . 'admin/dashboard.php'
        : BASE_URL . 'user/view.php';
    header('Location: ' . $dest);
    exit;
}

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType    = $_SESSION['flash_type']    ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a6b3c 0%, #0d4f2c 40%, #093d22 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.45);
            border: none;
        }
        .login-header {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border-radius: 16px 16px 0 0;
            padding: 2.2rem 2rem 1.8rem;
            text-align: center;
            color: #fff;
        }
        .login-header .app-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        .login-body {
            padding: 2rem;
            background: #fff;
            border-radius: 0 0 16px 16px;
        }
        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 .25rem rgba(25,135,84,.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border: none;
            color: #fff;
            padding: .65rem;
            font-size: 1.05rem;
            letter-spacing: .03rem;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .88; color: #fff; }
        .input-group-text { background: #f8f9fa; }
    </style>
</head>
<body>
<div class="login-card card">
    <div class="login-header">
        <div class="app-icon"><i class="bi bi-heart-pulse-fill"></i></div>
        <h4 class="fw-bold mb-0"><?= htmlspecialchars(APP_NAME) ?></h4>
        <p class="mb-0 opacity-75 small mt-1">Sistema de Indicadores de Atenção Primária</p>
    </div>
    <div class="login-body">
        <?php if ($flashMessage !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show py-2" role="alert">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= htmlspecialchars($flashMessage) ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>auth/login_handler.php" method="POST" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">E-mail</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        placeholder="seu@email.com"
                        autocomplete="email"
                        required
                    >
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label fw-semibold">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-login w-100 rounded-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const pwd  = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            pwd.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
</script>
</body>
</html>
