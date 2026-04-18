<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/auth/check_auth.php';

// Admin only
if ($currentUser['type'] !== 'admin') {
    header('Location: ' . BASE_URL . 'user/view.php');
    exit;
}

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType    = $_SESSION['flash_type']    ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Overwrite warning: remember which competency is pending confirmation
$overwriteComp = $_SESSION['overwrite_competency'] ?? '';
unset($_SESSION['overwrite_competency']);

$adminId       = $currentUser['admin_id'];
$selectedComp  = trim($_GET['competency'] ?? '');
$imports       = [];
$tableData     = [];
$chartColors   = [];
$pieCounts     = ['otimo' => 0, 'bom' => 0, 'suficiente' => 0, 'regular' => 0];

try {
    $pdo = Database::getInstance();

    // All imports for this admin + indicator
    $stmt = $pdo->prepare(
        'SELECT id, competency, filename, imported_at
         FROM competency_imports
         WHERE admin_id = ? AND indicator_key = ?
         ORDER BY competency DESC'
    );
    $stmt->execute([$adminId, 'esf_eap_mais_acesso']);
    $imports = $stmt->fetchAll();

    // If no competency selected but imports exist, default to latest
    if ($selectedComp === '' && !empty($imports)) {
        $selectedComp = $imports[0]['competency'];
    }

    // Load data for selected competency
    if ($selectedComp !== '') {
        $stmt = $pdo->prepare(
            'SELECT equipe, categoria,
                    atendimento_urgencia, consulta_agendada,
                    consulta_agendada_programada, consulta_no_dia, total
             FROM mais_acesso_data
             WHERE admin_id = ? AND competency = ?
             ORDER BY equipe, categoria'
        );
        $stmt->execute([$adminId, $selectedComp]);
        $rows = $stmt->fetchAll();

        // Group by equipe
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['equipe']][] = $row;
        }

        // Calculate score per equipe
        foreach ($grouped as $equipe => $categories) {
            $dn = 0;
            $nm = 0;
            foreach ($categories as $cat) {
                $dn += (int) $cat['total'];
                $nm += (int) $cat['consulta_agendada'] + (int) $cat['consulta_agendada_programada'];
            }
            $score        = $dn > 0 ? ($nm / $dn) * 100 : 0;
            $tableData[]  = [
                'equipe'    => $equipe,
                'dn'        => $dn,
                'nm'        => $nm,
                'score'     => $score,
                'score_fmt' => number_format($score, 2, ',', '.'),
            ];
            // Color logic: Ótimo >50≤70, Bom >30≤50, Suficiente >10≤30, Regular ≤10 ou >70
            if ($score > 50 && $score <= 70) {
                $pieCounts['otimo']++;
            } elseif ($score > 30 && $score <= 50) {
                $pieCounts['bom']++;
            } elseif ($score > 10 && $score <= 30) {
                $pieCounts['suficiente']++;
            } else {
                $pieCounts['regular']++;
            }
        }

        // Sort by score desc
        usort($tableData, fn($a, $b) => $b['score'] <=> $a['score']);
    }

} catch (PDOException $e) {
    $flashMessage = 'Erro ao carregar dados: ' . $e->getMessage();
    $flashType    = 'danger';
}

$pageTitle   = 'Mais Acesso à APS';
$currentPage = 'esf_mais_acesso';
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">eSF e eAP</li>
                <li class="breadcrumb-item active">Mais Acesso à APS</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0">Mais Acesso à Atenção Primária à Saúde</h4>
        <p class="text-muted small mb-0">Indicador 1 — eSF e eAP</p>
    </div>
</div>

<?php if ($flashMessage !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($flashMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- LEFT: Upload + Imports List -->
    <div class="col-lg-4">

        <!-- Upload Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-upload me-2 text-success"></i>Importar CSV
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Exporte o relatório <strong>Gerencial — Mais Acesso à Atenção Primária à Saúde</strong>
                    do e-SUS APS em formato CSV (separado por ponto-e-vírgula) e faça o upload abaixo.
                </p>
                <form action="<?= BASE_URL ?>admin/esf_eap/upload_handler.php"
                      method="POST"
                      enctype="multipart/form-data"
                      id="uploadForm">

                    <div class="mb-3">
                        <label for="competency" class="form-label fw-semibold small">Competência <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="competency"
                            name="competency"
                            placeholder="MM/AAAA"
                            pattern="^\d{2}/\d{4}$"
                            maxlength="7"
                            value="<?= htmlspecialchars($overwriteComp) ?>"
                            required
                        >
                        <div class="form-text">Formato: 01/2024</div>
                    </div>

                    <div class="mb-3">
                        <label for="csvFile" class="form-label fw-semibold small">Arquivo CSV <span class="text-danger">*</span></label>
                        <input
                            type="file"
                            class="form-control"
                            id="csvFile"
                            name="csv_file"
                            accept=".csv,text/csv"
                            required
                        >
                    </div>

                    <?php if ($overwriteComp !== ''): ?>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="confirmOverwrite"
                               name="confirm_overwrite" value="1" required>
                        <label class="form-check-label text-warning fw-semibold small" for="confirmOverwrite">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Confirmo que desejo substituir os dados da competência <strong><?= htmlspecialchars($overwriteComp) ?></strong>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="bi bi-cloud-upload me-2"></i>Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Imports History -->
        <?php if (!empty($imports)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-clock-history me-2 text-success"></i>Importações
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($imports as $imp): ?>
                    <a href="?competency=<?= urlencode($imp['competency']) ?>"
                       class="list-group-item list-group-item-action px-3 py-2
                              <?= $imp['competency'] === $selectedComp ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                                <strong><?= htmlspecialchars($imp['competency']) ?></strong>
                            </div>
                            <small class="<?= $imp['competency'] === $selectedComp ? 'text-white-50' : 'text-muted' ?>">
                                <?= date('d/m/y', strtotime($imp['imported_at'])) ?>
                            </small>
                        </div>
                        <div class="small text-truncate <?= $imp['competency'] === $selectedComp ? 'text-white-50' : 'text-muted' ?>"
                             style="max-width:220px;">
                            <?= htmlspecialchars($imp['filename']) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /col -->

    <!-- RIGHT: Results -->
    <div class="col-lg-8">
        <?php if ($selectedComp !== '' && !empty($tableData)): ?>

        <!-- Chart -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
                <span class="fw-semibold">
                    <i class="bi bi-pie-chart-fill me-2 text-success"></i>
                    Pontuação por Equipe — <?= htmlspecialchars($selectedComp) ?>
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <?= count($tableData) ?> equipes
                </span>
            </div>
            <div class="card-body">
                <canvas id="scoreChart" height="280"></canvas>
            </div>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-table me-2 text-success"></i>
                Resultados — Competência <?= htmlspecialchars($selectedComp) ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-3">Equipe</th>
                                <th scope="col" class="text-center">
                                    DN
                                    <i class="bi bi-info-circle text-muted ms-1"
                                       data-bs-toggle="tooltip"
                                       title="Denominador: total de atendimentos"></i>
                                </th>
                                <th scope="col" class="text-center">
                                    NM
                                    <i class="bi bi-info-circle text-muted ms-1"
                                       data-bs-toggle="tooltip"
                                       title="Numerador: consultas agendadas + agendadas programadas"></i>
                                </th>
                                <th scope="col" class="text-center">Pontuação</th>
                                <th scope="col" class="text-center">Dica</th>
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
                                    if ($s > 50 && $s <= 70) {
                                        $badgeClass = 'bg-success';
                                    } elseif ($s > 30 && $s <= 50) {
                                        $badgeClass = 'bg-primary';
                                    } elseif ($s > 10 && $s <= 30) {
                                        $badgeClass = 'bg-warning text-dark';
                                    } else {
                                        $badgeClass = 'bg-danger';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?> px-3 py-2" style="font-size:.85rem;">
                                        <?= $row['score_fmt'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    if ($s > 50 && $s <= 70) {
                                        $tip = 'Ótimo desempenho! A equipe mantém equilíbrio ideal entre demanda programada (>50% e ≤70%) e espontânea. Continue monitorando mensalmente para sustentar esse resultado.';
                                        $tipIcon = 'bi-check-circle-fill text-success';
                                    } elseif ($s > 30 && $s <= 50) {
                                        $tip = 'Bom desempenho. Para alcançar o Ótimo, amplie gradativamente as consultas agendadas, agendadas programadas e de cuidado continuado até atingir entre 50% e 70% do total de atendimentos.';
                                        $tipIcon = 'bi-lightbulb-fill text-primary';
                                    } elseif ($s > 10 && $s <= 30) {
                                        $tip = 'A equipe ainda concentra muitos atendimentos espontâneos. Invista na organização da agenda para ampliar consultas agendadas e de cuidado continuado, visando superar 30% de demanda programada.';
                                        $tipIcon = 'bi-lightbulb text-warning';
                                    } elseif ($s <= 10) {
                                        $tip = 'Atenção: percentual muito baixo de demanda programada. A equipe pode estar focada quase exclusivamente em demanda espontânea (urgência, consulta no dia). Revise o processo de agendamento e amplie as consultas agendadas e de cuidado continuado.';
                                        $tipIcon = 'bi-exclamation-triangle-fill text-danger';
                                    } else {
                                        $tip = 'Atenção: percentual muito elevado de demanda programada (>70%). Verifique se a equipe está aberta à demanda espontânea (escuta inicial, consulta no dia e urgências), pois esse excesso pode restringir o acesso imediato da população.';
                                        $tipIcon = 'bi-exclamation-triangle-fill text-danger';
                                    }
                                    ?>
                                    <button type="button" class="btn btn-sm btn-link p-0 border-0"
                                            data-bs-toggle="popover"
                                            data-bs-trigger="click"
                                            data-bs-placement="left"
                                            data-bs-content="<?= htmlspecialchars($tip) ?>">
                                        <i class="bi <?= $tipIcon ?> fs-5"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top small text-muted">
                Pontuação = (NM ÷ DN) × 100 &nbsp;|&nbsp;
                <span class="badge bg-success">Ótimo: &gt;50 e ≤70%</span>&nbsp;
                <span class="badge bg-primary">Bom: &gt;30 e ≤50%</span>&nbsp;
                <span class="badge bg-warning text-dark">Suficiente: &gt;10 e ≤30%</span>&nbsp;
                <span class="badge bg-danger">Regular: ≤10 ou &gt;70%</span>
            </div>
        </div>

        <?php elseif ($selectedComp !== '' && empty($tableData)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Nenhum dado encontrado para a competência <strong><?= htmlspecialchars($selectedComp) ?></strong>.
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                    <h5>Nenhuma importação ainda</h5>
                    <p class="mb-0">Importe um arquivo CSV para visualizar os resultados.</p>
                </div>
            </div>
        <?php endif; ?>
    </div><!-- /col -->
</div><!-- /row -->

<?php if (!empty($tableData)): ?>
<script>
(function () {
    const pieLabels = ['Ótimo', 'Bom', 'Suficiente', 'Regular'];
    const pieData   = <?= json_encode(array_values($pieCounts)) ?>;
    const pieColors = [
        'rgba(25,135,84,0.85)',
        'rgba(13,110,253,0.85)',
        'rgba(253,126,20,0.85)',
        'rgba(220,53,69,0.85)'
    ];

    new Chart(document.getElementById('scoreChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieData,
                backgroundColor: pieColors,
                borderColor: pieColors.map(c => c.replace('0.85', '1')),
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { padding: 16, font: { size: 13 } }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' equipe(s)'
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<script>
// Tooltip activation
const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
tooltips.forEach(el => new bootstrap.Tooltip(el));

// Popover activation
document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el, { html: false }));
// Close popovers when clicking outside
document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-bs-toggle="popover"]')) {
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
            bootstrap.Popover.getInstance(el)?.hide();
        });
    }
});

// Validate competency on input
document.getElementById('competency').addEventListener('input', function () {
    const re = /^\d{2}\/\d{4}$/;
    this.setCustomValidity(re.test(this.value) ? '' : 'Use o formato MM/AAAA (ex: 01/2024)');
});

// Show spinner on submit
document.getElementById('uploadForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importando...';
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
