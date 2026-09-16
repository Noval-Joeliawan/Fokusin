<?php
$bottomItems = [
    ['slug' => 'beranda',  'href' => '/dashboard.php', 'icon' => '🏠', 'label' => 'Beranda'],
    ['slug' => 'tugas',    'href' => '/tasks.php',     'icon' => '✅', 'label' => 'Tugas'],
    ['slug' => 'fokus',    'href' => '/focus.php',     'icon' => '⏱️', 'label' => 'Fokus'],
    ['slug' => 'progress', 'href' => '/progress.php',  'icon' => '📊', 'label' => 'Progress'],
    ['slug' => 'profil',   'href' => '/profile.php',   'icon' => '👤', 'label' => 'Profil'],
];
$active = $activeNav ?? '';
?>
<nav class="bottom-nav" aria-label="Navigasi utama">
    <?php foreach ($bottomItems as $item): ?>
        <a href="<?= BASE_URL . e($item['href']) ?>"
           class="bottom-nav__link <?= $active === $item['slug'] ? 'is-active' : '' ?>">
            <span class="bottom-nav__icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <span class="bottom-nav__label"><?= e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
