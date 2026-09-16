<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM user_progress WHERE user_id = ?');
$stmt->execute([$user['id']]);
$progress = $stmt->fetch() ?: ['level' => 1, 'current_streak' => 0, 'total_study_minutes' => 0, 'tasks_completed' => 0, 'quizzes_completed' => 0];

$pageTitle = 'Profil';
$activeNav = 'profil';
include __DIR__ . '/includes/header.php';
?>

<section class="panel profile-panel">
    <div class="profile-panel__avatar" aria-hidden="true"><?= e(mb_substr($user['name'], 0, 1)) ?></div>
    <h1 class="profile-panel__name"><?= e($user['name']) ?></h1>
    <p class="profile-panel__email"><?= e($user['email']) ?></p>

    <div class="stat-row">
        <div class="stat-block">
            <span class="stat-block__label">Level</span>
            <span class="stat-block__value"><?= (int) $progress['level'] ?></span>
        </div>
        <div class="stat-block stat-block--accent">
            <span class="stat-block__label">🔥 Streak</span>
            <span class="stat-block__value"><?= (int) $progress['current_streak'] ?> Hari</span>
        </div>
        <div class="stat-block">
            <span class="stat-block__label">Total Belajar</span>
            <span class="stat-block__value"><?= e(formatMinutesToHours((int) $progress['total_study_minutes'])) ?></span>
        </div>
        <div class="stat-block">
            <span class="stat-block__label">Tugas Selesai</span>
            <span class="stat-block__value"><?= (int) $progress['tasks_completed'] ?></span>
        </div>
    </div>

    <div class="empty-state">
        <p>Pengeditan profil & lencana pencapaian akan hadir di fase berikutnya.</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
