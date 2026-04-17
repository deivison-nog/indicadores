<?php
require_once dirname(__DIR__) . '/../config/config.php';
require_once dirname(__DIR__) . '/../config/database.php';
require_once dirname(__DIR__) . '/../auth/check_auth.php';

// Admin only
if ($currentUser['type'] !== 'admin') {
    header('Location: ' . BASE_URL . 'user/view.php');
    exit;
}

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType    = $_SESSION['flash_type']    ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

try {
    $pdo = Database::getInstance();

    // Get admin's public token for sharing
    $stmt = $pdo->prepare('SELECT name, public_token FROM administrators WHERE id = ? LIMIT 1');
    $stmt->execute([$currentUser['admin_id']]);
    $admin = $stmt->fetch();

    // Count imports for this admin
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM competency_imports WHERE admin_id = ?');
    $stmt->execute([$currentUser['admin_id']]);
    $totalImports = (int) $stmt->fetchColumn();

    // Count distinct competencies
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT competency) FROM competency_imports WHERE admin_id = ?');
    $stmt->execute([$currentUser['admin_id']]);
    $totalCompetencies = (int) $stmt->fetchColumn();

    // Count teams in mais_acesso
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT equipe) FROM mais_acesso_data WHERE admin_id = ?');
    $stmt->execute([$currentUser['admin_id']]);
    $totalTeams = (int) $stmt->fetchColumn();

    // Last import date
    $stmt = $pdo->prepare('SELECT MAX(imported_at) FROM competency_imports WHERE admin_id = ?');
    $stmt->execute([$currentUser['admin_id']]);
    $lastImport = $stmt->fetchColumn();

} catch (PDOException $e) {
    $admin             = ['name' => $currentUser['name'], 'public_token' => ''];
    $totalImports      = 0;
    $totalCompetencies = 0;
    $totalTeams        = 0;
    $lastImport        = null;
}

$publicUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . BASE_URL . 'user/view.php?token=' . urlencode($admin['public_token'] ?? '');

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/layout/sidebar.php';
?>

<!-- Page Title -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0">Dashboard</h4>
        <p class="text-muted mb-0 small">Bem-vindo, <?= htmlspecialchars($currentUser['name']) ?>!</p>
    </div>
    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
        <i class="bi bi-shield-check me-1"></i>Administrador
    </span>
</div>

<?php if ($flashMessage !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success fs-3">
                    <i class="bi bi-upload"></i>
                </div>
                <div>
                    <div class="text-muted small">Importações</div>
                    <div class="fw-bold fs-4"><?= $totalImports ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary fs-3">
                    <i class="bi bi-calendar3"></i>
                </div>
                <div>
                    <div class="text-muted small">Competências</div>
                    <div class="fw-bold fs-4"><?= $totalCompetencies ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info fs-3">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="text-muted small">Equipes</div>
                    <div class="fw-bold fs-4"><?= $totalTeams ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning fs-3">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="text-muted small">Última Importação</div>
                    <div class="fw-bold" style="font-size:.95rem;">
                        <?= $lastImport ? date('d/m/Y H:i', strtotime($lastImport)) : '—' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Public link card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-share me-2 text-success"></i>Link Público de Visualização
    </div>
    <div class="card-body">
        <p class="text-muted mb-3 small">
            Compartilhe este link com usuários para que possam visualizar os indicadores sem necessidade de login.
        </p>
        <div class="input-group">
            <input type="text" class="form-control font-monospace small" id="publicUrl"
                   value="<?= htmlspecialchars($publicUrl) ?>" readonly>
            <button class="btn btn-outline-success" onclick="copyPublicUrl()" type="button">
                <i class="bi bi-clipboard me-1"></i>Copiar
            </button>
            <a href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- Quick links -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-lightning-charge me-2 text-success"></i>Acesso Rápido
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <a href="<?= BASE_URL ?>admin/esf_eap/mais_acesso.php"
                   class="card h-100 border border-success-subtle text-decoration-none p-3 d-flex flex-row align-items-center gap-3 rounded-3 hover-card">
                    <div class="rounded-3 bg-success bg-opacity-10 text-success p-2 fs-4">
                        <i class="bi bi-door-open"></i>
                    </div>
                    <div>
                        <div class="fw-semibold text-dark" style="font-size:.9rem;">Mais Acesso à APS</div>
                        <div class="text-muted" style="font-size:.78rem;">eSF e eAP · Importar CSV</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card { transition: box-shadow .18s, transform .18s; }
.hover-card:hover { box-shadow: 0 4px 16px rgba(25,135,84,.2); transform: translateY(-2px); }
</style>

<script>
function copyPublicUrl() {
    const el = document.getElementById('publicUrl');
    el.select();
    navigator.clipboard.writeText(el.value).then(() => {
        const btn = el.nextElementSibling;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copiado!';
        btn.classList.replace('btn-outline-success', 'btn-success');
        setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar';
            btn.classList.replace('btn-success', 'btn-outline-success');
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
