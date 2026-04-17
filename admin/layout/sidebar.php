<?php
/**
 * admin/layout/sidebar.php
 * Left sidebar navigation. Expects $currentPage (string) for active highlighting.
 */
$currentPage = $currentPage ?? '';
?>
<!-- Sidebar -->
<nav class="sidebar" id="sidebar">

    <div class="nav-section-title">Principal</div>
    <a href="<?= BASE_URL ?>admin/dashboard.php"
       class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="sidebar-divider"></div>
    <div class="nav-section-title">Indicadores</div>

    <!-- eSF e eAP -->
    <div class="collapse-toggle"
         data-bs-toggle="collapse"
         data-bs-target="#menu-esf"
         aria-expanded="<?= str_starts_with($currentPage, 'esf') ? 'true' : 'false' ?>"
         role="button">
        <i class="bi bi-people-fill"></i>
        <span>eSF e eAP</span>
        <i class="bi bi-chevron-right chevron"></i>
    </div>
    <div class="collapse sub-nav <?= str_starts_with($currentPage, 'esf') ? 'show' : '' ?>" id="menu-esf">
        <a href="<?= BASE_URL ?>admin/esf_eap/mais_acesso.php"
           class="nav-link <?= $currentPage === 'esf_mais_acesso' ? 'active' : '' ?>">
            <i class="bi bi-door-open"></i>
            Mais Acesso à APS
        </a>
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-baby"></i>
            Cuidado no Des. Infantil
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-gender-female"></i>
            Cuidado da Gestante e Puérpera
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-droplet-half"></i>
            Cuidado — Diabetes Mellitus
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-heart"></i>
            Cuidado — Hipertensão Arterial
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-person-cane"></i>
            Cuidado da Pessoa Idosa
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-shield-check"></i>
            Cuidado — Prevenção do Câncer
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
    </div>

    <!-- eSB -->
    <div class="collapse-toggle"
         data-bs-toggle="collapse"
         data-bs-target="#menu-esb"
         aria-expanded="false"
         role="button">
        <i class="bi bi-tooth"></i>
        <span>eSB</span>
        <i class="bi bi-chevron-right chevron"></i>
    </div>
    <div class="collapse sub-nav" id="menu-esb">
        <?php
        $esbItems = [
            'Consulta Odontológica Programada na APS',
            'Tratamento Odontológico Concluído',
            'Taxa de Exodontias na APS',
            'Escovação Supervisionada na APS',
            'Procedimentos Odontológicos Preventivos na APS',
            'Tratamento Restaurador Atraumático na APS',
        ];
        foreach ($esbItems as $item): ?>
            <span class="nav-link disabled" title="Em breve">
                <i class="bi bi-dash"></i>
                <?= htmlspecialchars($item) ?>
                <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
            </span>
        <?php endforeach; ?>
    </div>

    <!-- eMulti -->
    <div class="collapse-toggle"
         data-bs-toggle="collapse"
         data-bs-target="#menu-emulti"
         aria-expanded="false"
         role="button">
        <i class="bi bi-person-lines-fill"></i>
        <span>eMulti</span>
        <i class="bi bi-chevron-right chevron"></i>
    </div>
    <div class="collapse sub-nav" id="menu-emulti">
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-dash"></i>
            Média de Atendimentos da eMulti por Pessoa
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
        <span class="nav-link disabled" title="Em breve">
            <i class="bi bi-dash"></i>
            Ações Interprofissionais da eMulti na APS
            <span class="badge bg-secondary ms-auto" style="font-size:.65rem;">Em breve</span>
        </span>
    </div>

    <div class="sidebar-divider"></div>
    <div class="nav-section-title">Conta</div>
    <a href="<?= BASE_URL ?>logout.php" class="nav-link text-danger-emphasis">
        <i class="bi bi-box-arrow-right"></i> Sair
    </a>

</nav>
<!-- /Sidebar -->

<!-- Sidebar overlay for mobile -->
<div class="d-md-none" id="sidebarOverlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1029;"
     onclick="closeSidebar()"></div>

<script>
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('show');
    document.getElementById('sidebarOverlay').style.display = 'none';
}
document.getElementById('sidebarToggle').addEventListener('click', function () {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    sb.classList.toggle('show');
    ov.style.display = sb.classList.contains('show') ? 'block' : 'none';
});
</script>

<!-- Page content wrapper -->
<div class="main-content">
