<?php
$navItems = [
    ['slug' => 'beranda',  'href' => '/dashboard.php',   'icon' => '🏠', 'label' => 'Beranda'],
    ['slug' => 'tugas',    'href' => '/tasks.php',       'icon' => '✅', 'label' => 'Tugas'],
    ['slug' => 'fokus',    'href' => '/focus.php',       'icon' => '⏱️', 'label' => 'Fokus'],
    ['slug' => 'planner',  'href' => '/planner.php',     'icon' => '📅', 'label' => 'Planner'],
    ['slug' => 'catatan',  'href' => '/notes.php',       'icon' => '📝', 'label' => 'Catatan'],
    ['slug' => 'materi',   'href' => '/materials.php',   'icon' => '📚', 'label' => 'Materi'],
    ['slug' => 'kuis',     'href' => '/quiz.php',        'icon' => '🧠', 'label' => 'Kuis'],
    ['slug' => 'progress', 'href' => '/progress.php',    'icon' => '📊', 'label' => 'Progress'],
    ['slug' => 'target',   'href' => '/targets.php',     'icon' => '🎯', 'label' => 'Target'],
    ['slug' => 'pencapaian','href' => '/achievements.php','icon' => '🏆', 'label' => 'Pencapaian'],
    ['slug' => 'resource', 'href' => '/resources.php',   'icon' => '📂', 'label' => 'Resource'],
];
$active = $activeNav ?? '';
?>
<nav class="sidebar" id="sidebarNav">
    <div class="sidebar__brand">
        <span class="sidebar__logo">Fokusin</span>
    </div>

    <ul class="sidebar__list">
        <?php foreach ($navItems as $item): ?>
            <li>
                <a href="<?= BASE_URL . e($item['href']) ?>"
                   class="sidebar__link <?= $active === $item['slug'] ? 'is-active' : '' ?>">
                    <span class="sidebar__icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar__divider"></div>

    <ul class="sidebar__list sidebar__list--bottom">
        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <li>
            <a href="<?= BASE_URL ?>/admin/index.php" class="sidebar__link">
                <span class="sidebar__icon" aria-hidden="true">🛠️</span>
                <span>Admin</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?= BASE_URL ?>/profile.php" class="sidebar__link <?= $active === 'profil' ? 'is-active' : '' ?>">
                <span class="sidebar__icon" aria-hidden="true">👤</span>
                <span>Profil</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/settings.php" class="sidebar__link <?= $active === 'pengaturan' ? 'is-active' : '' ?>">
                <span class="sidebar__icon" aria-hidden="true">⚙️</span>
                <span>Pengaturan</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/logout.php" class="sidebar__link sidebar__link--logout">
                <span class="sidebar__icon" aria-hidden="true">🚪</span>
                <span>Keluar</span>
            </a>
        </li>
    </ul>
</nav>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
