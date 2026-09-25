<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
ensureUserProgressRow($userId);
checkAndUnlockAchievements($pdo, $userId);
$achievements = getUserAchievements($pdo, $userId);
$unlockedCount = count(array_filter($achievements, static fn($a) => !empty($a['unlocked_at'])));

$pageTitle = 'Pencapaian';
$activeNav = 'pencapaian';
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <div>
            <h2>Pencapaian</h2>
            <p class="panel__sub">Kumpulkan badge dari kebiasaan belajar yang konsisten.</p>
        </div>
        <span class="achievement-count"><?= $unlockedCount ?>/<?= count($achievements) ?> terbuka</span>
    </div>

    <div class="achievement-grid">
        <?php foreach ($achievements as $achievement): ?>
            <?php $unlocked = !empty($achievement['unlocked_at']); ?>
            <article class="achievement-card <?= $unlocked ? 'achievement-card--unlocked' : 'achievement-card--locked' ?>">
                <div class="achievement-card__icon" aria-hidden="true"><?= e($achievement['icon'] ?? '🏆') ?></div>
                <div class="achievement-card__body">
                    <h3><?= e($achievement['name']) ?></h3>
                    <p><?= e($achievement['description']) ?></p>
                    <?php if ($unlocked): ?>
                        <span class="achievement-card__status">✓ Terbuka · <?= e((new DateTime($achievement['unlocked_at']))->format('d M Y')) ?></span>
                    <?php else: ?>
                        <span class="achievement-card__status">🔒 Belum terbuka</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
