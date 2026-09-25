<?php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$pdo = getDbConnection();
$adminUser = currentUser();
$csrfToken = generateCsrfToken();

function adminUrl(string $path, array $query = []): string
{
    $url = BASE_URL . '/admin/' . ltrim($path, '/');
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function adminPageStart(string $title, string $active): void
{
    global $csrfToken;
    $pageTitle = $title;
    include __DIR__ . '/../includes/header.php';
    ?>
    <section class="admin-shell">
        <div class="admin-head">
            <div>
                <p class="admin-kicker">Panel Admin</p>
                <h1><?= e($title) ?></h1>
            </div>
            <div class="admin-head__actions">
                <a class="btn btn-secondary" href="<?= e(BASE_URL . '/dashboard.php') ?>">← Kembali ke Fokusin</a>
            </div>
        </div>
        <nav class="admin-nav" aria-label="Navigasi admin">
            <?php
            $items = [
                ['key' => 'dashboard', 'href' => adminUrl('index.php'), 'label' => '📊 Dashboard'],
                ['key' => 'users', 'href' => adminUrl('users.php'), 'label' => '👥 Pengguna'],
                ['key' => 'resources', 'href' => adminUrl('resources.php'), 'label' => '📂 Resource'],
                ['key' => 'music', 'href' => adminUrl('music.php'), 'label' => '🎧 Focus Music'],
            ];
            foreach ($items as $item):
            ?>
                <a class="admin-nav__link <?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>">
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php
}

function adminPageEnd(): void
{
    include __DIR__ . '/../includes/footer.php';
}
