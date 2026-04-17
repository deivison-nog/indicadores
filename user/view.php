<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token    = trim($_GET['token'] ?? '');
$admin    = null;
$tableData = [];
$imports   = [];
$chartColors = [];

if ($token === '') {
    http_response_code(400);
    $errorMsg = 'Token não informado.';
} else {
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, name FROM administrators WHERE public_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $admin = $stmt->fetch();

        if (!$admin) {
            http_response_code(404);
            $errorMsg = 'Painel não encontrado. Verifique o link.';
        } else {
            $adminId = $admin['id'];

            // Load competency list
            $stmt = $pdo->prepare(
                'SELECT id, competency, imported_at
                 FROM competency_imports
                 WHERE admin_id = ? AND indicator_key = ?
                 ORDER BY competency DESC'
            );
            $stmt->execute([$adminId, 'esf_eap_mais_acesso']);
            $imports = $stmt->fetchAll();

            // Selected competency
            $selectedComp = trim($_GET['competency'] ?? '');
            if ($selectedComp === '' && !empty($imports)) {
                $selectedComp = $imports[0]['competency'];
            }

            if ($selectedComp !== '') {
                $stmt = $pdo->prepare(
                    'SELECT equipe, categoria, consulta_agendada,
                            consulta_agendada_programada, total
                     FROM mais_acesso_data
                     WHERE admin_id = ? AND competency = ?
                     ORDER BY equipe, categoria'
                );
                $stmt->execute([$adminId, $selectedComp]);
                $rows = $stmt->fetchAll();

                // Group + calculate
                $grouped = [];
                foreach ($rows as $row) {
                    $grouped[$row['equipe']][] = $row;
                }
                foreach ($grouped as $equipe => $cats) {
                    $dn = 0; $nm = 0;
                    foreach ($cats as $cat) {
                        $dn += (int) $cat['total'];
                        $nm += (int) $cat['consulta_agendada'] + (int) $cat['consulta_agendada_programada'];
                    }
                    $score       = $dn > 0 ? ($nm / $dn) * 100 : 0;
                    $tableData[] = [
                        'equipe'    => $equipe,
                        'dn'        => $dn,
                        'nm'        => $nm,
                        'score'     => $score,
                        'score_fmt' => number_format($score, 2, ',', '.'),
                    ];
                    if ($score > 50 && $score <= 70) {
                        $chartColors[] = 'rgba(25,135,84,0.8)';   // Ótimo
                    } elseif ($score > 30 && $score <= 50) {
                        $chartColors[] = 'rgba(13,110,253,0.8)';  // Bom
                    } elseif ($score > 10 && $score <= 30) {
                        $chartColors[] = 'rgba(253,126,20,0.8)';  // Suficiente
                    } else {
                        $chartColors[] = 'rgba(220,53,69,0.8)';   // Regular (≤10 ou >70)
                    }
                }
                usort($tableData, fn($a, $b) => $b['score'] <=> $a['score']);
            }
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $errorMsg = 'Erro interno. Tente novamente mais tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($admin) ? htmlspecialchars($admin['name']) . ' — ' : '' ?><?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6f9; }
        .topbar {
            background: linear-gradient(135deg, #198754 0%, #0d4f2c 100%);
            padding: .75rem 1.5rem;
            color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }
        .readonly-badge {
            background: rgba(255,255,255,.15);
            border-radius: 20px;
            padding: .2rem .7rem;
            font-size: .78rem;
        }
    </style>
</head>
<body>

<div class="topbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-heart-pulse-fill fs-5"></i>
        <strong><?= htmlspecialchars(APP_NAME) ?></strong>
        <?php if ($admin): ?>
            <span class="opacity-75">— <?= htmlspecialchars($admin['name']) ?></span>
        <?php endif; ?>
    </div>
    <span class="readonly-badge"><i class="bi bi-eye me-1"></i>Visualização</span>
</div>

<div class="container-xl py-4">

<?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger mt-4">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMsg) ?>
    </div>
<?php elseif ($admin): ?>

    <h5 class="fw-bold mb-1">
        <i class="bi bi-door-open me-2 text-success"></i>
        Mais Acesso à Atenção Primária à Saúde
    </h5>
    <p class="text-muted small mb-4">eSF e eAP · Indicador 1 · Somente leitura</p>

    <?php if (!empty($imports)): ?>
    <!-- Competency selector -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-semibold text-muted small">Competência:</span>
            <?php foreach ($imports as $imp): ?>
                <a href="?token=<?= urlencode($token) ?>&competency=<?= urlencode($imp['competency']) ?>"
                   class="btn btn-sm <?= ($imp['competency'] === $selectedComp) ? 'btn-success' : 'btn-outline-secondary' ?>">
                    <?= htmlspecialchars($imp['competency']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($tableData)): ?>

    <!-- Chart -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-semibold">
            <i class="bi bi-bar-chart-fill me-2 text-success"></i>
            Pontuação por Equipe — <?= htmlspecialchars($selectedComp) ?>
        </div>
        <div class="card-body">
            <canvas id="scoreChart" height="260"></canvas>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom fw-semibold">
            <i class="bi bi-table me-2 text-success"></i>
            Resultados — Competência <?= htmlspecialchars($selectedComp) ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Equipe</th>
                        <th class="text-center">DN</th>
                        <th class="text-center">NM</th>
                        <th class="text-center">Pontuação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableData as $row): ?>
                    <tr>
                        <td class="ps-3 fw-semibold"><?= htmlspecialchars($row['equipe']) ?></td>
                        <td class="text-center"><?= number_format($row['dn'], 0, ',', '.') ?></td>
                        <td class="text-center"><?= number_format($row['nm'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <?php
                            $s = $row['score'];
                            if ($s > 50 && $s <= 70)     $bc = 'bg-success';
                            elseif ($s > 30 && $s <= 50) $bc = 'bg-primary';
                            elseif ($s > 10 && $s <= 30) $bc = 'bg-warning text-dark';
                            else                         $bc = 'bg-danger';
                            ?>
                            <span class="badge <?= $bc ?> px-3 py-2" style="font-size:.85rem;">
                                <?= $row['score_fmt'] ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top small text-muted">
            Pontuação = (NM ÷ DN) × 100 &nbsp;|&nbsp;
            <span class="badge bg-success">Ótimo: &gt;50 e ≤70%</span>&nbsp;
            <span class="badge bg-primary">Bom: &gt;30 e ≤50%</span>&nbsp;
            <span class="badge bg-warning text-dark">Suficiente: &gt;10 e ≤30%</span>&nbsp;
            <span class="badge bg-danger">Regular: ≤10 ou &gt;70%</span>
        </div>
    </div>

    <?php elseif (!empty($imports)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Nenhum dado disponível para a competência selecionada.
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                <h5>Nenhum dado disponível</h5>
                <p class="mb-0">Os indicadores ainda não foram publicados.</p>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<?php if (!empty($tableData)): ?>
<script>
(function () {
    const labels = <?= json_encode(array_column($tableData, 'equipe')) ?>;
    const scores = <?= json_encode(array_column($tableData, 'score')) ?>;
    const colors = <?= json_encode($chartColors) ?>;

    new Chart(document.getElementById('scoreChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Pontuação (%)',
                data: scores,
                backgroundColor: colors,
                borderColor: colors.map(c => c.replace('0.8', '1')),
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: { label: ctx => ctx.parsed.y.toFixed(2).replace('.', ',') + '%' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true, max: 100,
                    ticks: { callback: v => v + '%' },
                    grid: { color: 'rgba(0,0,0,.06)' }
                },
                x: { ticks: { maxRotation: 35, font: { size: 11 } } }
            }
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
