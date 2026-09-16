<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Materi';
$activeNav = 'materi';
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head"><h2>Materi</h2></div>
    <div class="empty-state">
        <span class="empty-state__icon" aria-hidden="true">📚</span>
        <p>Fitur Materi sedang dibangun.</p>
        <p class="empty-state__sub">Akan tersedia di Fase 3.</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
