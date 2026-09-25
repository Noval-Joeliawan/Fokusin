<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
ensureUserProgressRow($userId);

$stmt = $pdo->prepare('SELECT * FROM user_progress WHERE user_id = ?');
$stmt->execute([$userId]);
$progress = $stmt->fetch();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE user_id = ?');
$stmt->execute([$userId]);
$notesCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM materials');
$stmt->execute();
$totalMaterials = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT AVG(score) FROM quiz_attempts WHERE user_id = ?');
$stmt->execute([$userId]);
$avgQuizScore = $stmt->fetchColumn();
$avgQuizScore = $avgQuizScore !== null ? round((float) $avgQuizScore) : null;

$stmt = $pdo->prepare('SELECT COUNT(DISTINCT quiz_id) FROM quiz_attempts WHERE user_id = ?');
$stmt->execute([$userId]);
$distinctQuizzesTaken = (int) $stmt->fetchColumn();

$xpForCurrentLevel = ((int) $progress['level'] - 1) * 500;
$xpForNextLevel = (int) $progress['level'] * 500;
$xpProgressPercent = $xpForNextLevel > $xpForCurrentLevel
    ? min(100, (int) round((((int) $progress['xp'] - $xpForCurrentLevel) / ($xpForNextLevel - $xpForCurrentLevel)) * 100))
    : 100;

$pageTitle = 'Progress';
$activeNav = 'progress';
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head"><h2>Level & XP</h2></div>
    <div class="progress-level">
        <span class="progress-level__badge">Level <?= (int) $progress['level'] ?></span>
        <div class="progress-bar">
            <div class="progress-bar__fill" style="width: <?= $xpProgressPercent ?>%"></div>
        </div>
        <span class="progress-level__xp"><?= (int) $progress['xp'] ?> XP total</span>
    </div>
</section>

<section class="stat-row">
    <div class="stat-block">
        <span class="stat-block__label">Total Belajar</span>
        <span class="stat-block__value"><?= e(formatMinutesToHours((int) $progress['total_study_minutes'])) ?></span>
    </div>
    <div class="stat-block stat-block--accent">
        <span class="stat-block__label">🔥 Streak</span>
        <span class="stat-block__value"><?= (int) $progress['current_streak'] ?> Hari</span>
    </div>
    <div class="stat-block">
        <span class="stat-block__label">Sesi Fokus</span>
        <span class="stat-block__value"><?= (int) $progress['focus_sessions_completed'] ?></span>
    </div>
    <div class="stat-block">
        <span class="stat-block__label">Tugas Selesai</span>
        <span class="stat-block__value"><?= (int) $progress['tasks_completed'] ?></span>
    </div>
</section>

<section class="stat-row">
    <div class="stat-block">
        <span class="stat-block__label">Materi Selesai</span>
        <span class="stat-block__value"><?= (int) $progress['materials_completed'] ?><?= $totalMaterials > 0 ? ' / ' . $totalMaterials : '' ?></span>
    </div>
    <div class="stat-block">
        <span class="stat-block__label">Kuis Selesai</span>
        <span class="stat-block__value"><?= (int) $progress['quizzes_completed'] ?></span>
    </div>
    <div class="stat-block">
        <span class="stat-block__label">Rata-rata Nilai Kuis</span>
        <span class="stat-block__value"><?= $avgQuizScore !== null ? $avgQuizScore . '%' : '-' ?></span>
    </div>
    <div class="stat-block">
        <span class="stat-block__label">Catatan</span>
        <span class="stat-block__value"><?= $notesCount ?></span>
    </div>
</section>

<section class="panel">
    <div class="panel__head"><h2>Streak Terpanjang</h2></div>
    <p class="progress-longest-streak">🏆 <?= (int) $progress['longest_streak'] ?> hari berturut-turut</p>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
