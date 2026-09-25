<?php
/**
 * Include ini membuka <html>...<body> dan merender header mobile + flash message.
 * Variabel $pageTitle dan $activeNav (slug menu aktif) harus diset sebelum include.
 */
$user = currentUser();
$theme = $user['theme'] ?? 'light';
$flash = getFlash();
$levelUp = $_SESSION['level_up'] ?? null;
unset($_SESSION['level_up']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= e($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= e($pageTitle ?? 'Dashboard') ?> — Fokusin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-shell">

    <header class="mobile-header">
        <span class="mobile-header__logo">Fokusin</span>
        <button class="mobile-header__menu" id="mobileMenuBtn" aria-label="Buka menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </header>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="app-main">
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" role="status">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>
        <?php if ($levelUp): ?>
            <div class="alert alert-success" role="status">🎉 Selamat! Kamu naik dari Level <?= (int) $levelUp['from'] ?> ke Level <?= (int) $levelUp['to'] ?>.</div>
        <?php endif; ?>
