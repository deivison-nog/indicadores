<?php
/**
 * admin/layout/header.php
 * Included at the top of every admin page.
 * Expects: $currentUser (set by check_auth.php), $pageTitle (string).
 */
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(APP_NAME) ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #0d4f2c;
            --sidebar-hover: #146c43;
            --sidebar-active: #198754;
            --topbar-height: 56px;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        /* ── Top Navbar ── */
        .topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--topbar-height);
            background: linear-gradient(135deg, #198754 0%, #0d4f2c 100%);
            z-index: 1040;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }
        .topbar .brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.15rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .5rem;
            flex: 1;
        }
        .topbar .brand i { font-size: 1.4rem; }
        .topbar .user-info {
            color: rgba(255,255,255,.85);
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .topbar .user-badge {
            background: rgba(255,255,255,.15);
            border-radius: 20px;
            padding: .25rem .75rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            overflow-y: auto;
            z-index: 1030;
            transition: transform .3s ease;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,.2) transparent;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 4px; }

        .sidebar .nav-section-title {
            color: rgba(255,255,255,.45);
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 1.2rem 1rem .4rem;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: .55rem 1rem;
            border-radius: 6px;
            margin: 1px 8px;
            font-size: .88rem;
            display: flex;
            align-items: center;
            gap: .6rem;
            transition: background .18s, color .18s;
            text-decoration: none;
        }
        .sidebar .nav-link:hover:not(.disabled) {
            background: var(--sidebar-hover);
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: var(--sidebar-active);
            color: #fff;
            font-weight: 600;
        }
        .sidebar .nav-link.disabled {
            color: rgba(255,255,255,.3);
            cursor: default;
            pointer-events: none;
        }
        .sidebar .collapse-toggle {
            color: rgba(255,255,255,.9);
            font-weight: 600;
            font-size: .9rem;
            padding: .6rem 1rem;
            border-radius: 6px;
            margin: 2px 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: .6rem;
            transition: background .18s;
        }
        .sidebar .collapse-toggle:hover { background: var(--sidebar-hover); }
        .sidebar .collapse-toggle[aria-expanded="true"] { background: rgba(255,255,255,.08); }
        .sidebar .collapse-toggle .chevron {
            margin-left: auto;
            transition: transform .25s;
        }
        .sidebar .collapse-toggle[aria-expanded="true"] .chevron { transform: rotate(90deg); }

        .sidebar .sub-nav { padding-left: .5rem; }
        .sidebar .sub-nav .nav-link { font-size: .84rem; }

        .sidebar-divider {
            border-top: 1px solid rgba(255,255,255,.1);
            margin: .5rem 1rem;
        }

        /* ── Main Content ── */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            padding: 1.75rem;
            min-height: calc(100vh - var(--topbar-height));
        }

        /* ── Hamburger (mobile) ── */
        #sidebarToggle {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            margin-right: .75rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            #sidebarToggle { display: block; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>

<!-- Top Navbar -->
<nav class="topbar">
    <button id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="<?= BASE_URL ?>admin/dashboard.php" class="brand">
        <i class="bi bi-heart-pulse-fill"></i>
        <?= htmlspecialchars(APP_NAME) ?>
    </a>
    <div class="user-info">
        <div class="user-badge">
            <i class="bi bi-person-circle"></i>
            <span><?= htmlspecialchars($currentUser['name']) ?></span>
        </div>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-box-arrow-right me-1"></i>Sair
        </a>
    </div>
</nav>
