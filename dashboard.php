<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
ensureUserProgressRow($user['id']);

// --- Progress ringkas (XP, streak, dsb.) ---
$stmt = $pdo->prepare('SELECT * FROM user_progress WHERE user_id = ?');
$stmt->execute([$user['id']]);
$progress = $stmt->fetch() ?: [
    'xp' => 0, 'level' => 1, 'current_streak' => 0, 'total_study_minutes' => 0,
    'tasks_completed' => 0,
];

// --- Fokus belajar hari ini (total durasi sesi fokus yang selesai hari ini) ---
$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(duration_minutes), 0) AS total
     FROM study_sessions
     WHERE user_id = ? AND is_completed = 1 AND DATE(started_at) = CURDATE()'
);
$stmt->execute([$user['id']]);
$todayFocusMinutes = (int) $stmt->fetchColumn();

// --- Tugas selesai minggu ini ---
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks
     WHERE user_id = ? AND status = 'selesai' AND YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)"
);
$stmt->execute([$user['id']]);
$tasksCompletedThisWeek = (int) $stmt->fetchColumn();

// --- Target mingguan aktif (minggu berjalan) ---
$stmt = $pdo->prepare(
    "SELECT * FROM study_targets
     WHERE user_id = ? AND week_start_date = DATE(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY))
     LIMIT 1"
);
$stmt->execute([$user['id']]);
$weeklyTarget = $stmt->fetch();

$weeklyTargetPercent = 0;
if ($weeklyTarget && $weeklyTarget['target_hours'] > 0) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(duration_minutes), 0) FROM study_sessions
         WHERE user_id = ? AND is_completed = 1 AND YEARWEEK(started_at, 1) = YEARWEEK(CURDATE(), 1)"
    );
    $stmt->execute([$user['id']]);
    $weekMinutes = (int) $stmt->fetchColumn();
    $weeklyTargetPercent = min(100, (int) round(($weekMinutes / 60) / $weeklyTarget['target_hours'] * 100));
}

// --- Tugas hari ini + tugas terlewat ---
$stmt = $pdo->prepare(
    "SELECT t.*, s.name AS subject_name FROM tasks t
     LEFT JOIN subjects s ON s.id = t.subject_id
     WHERE t.user_id = ? AND t.status != 'selesai'
       AND (DATE(t.deadline) <= CURDATE() OR t.deadline IS NULL)
     ORDER BY t.deadline IS NULL, t.deadline ASC
     LIMIT 5"
);
$stmt->execute([$user['id']]);
$todayTasks = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'selesai' AND deadline < CURDATE()"
);
$stmt->execute([$user['id']]);
$overdueCount = (int) $stmt->fetchColumn();

// Total tugas yang masih perlu dikerjakan hari ini/lebih awal (untuk badge di panel, terpisah dari LIMIT 5 di atas).
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tasks t WHERE t.user_id = ? AND t.status != 'selesai'
       AND (DATE(t.deadline) <= CURDATE() OR t.deadline IS NULL)"
);
$stmt->execute([$user['id']]);
$todayTasksTotal = (int) $stmt->fetchColumn();

// --- Jadwal belajar berikutnya ---
$stmt = $pdo->prepare(
    "SELECT sp.*, s.name AS subject_name FROM study_plans sp
     LEFT JOIN subjects s ON s.id = sp.subject_id
     WHERE sp.user_id = ?
       AND (sp.plan_date > CURDATE() OR (sp.plan_date = CURDATE() AND sp.start_time >= CURTIME()))
     ORDER BY sp.plan_date ASC, sp.start_time ASC
     LIMIT 1"
);
$stmt->execute([$user['id']]);
$nextPlan = $stmt->fetch();

// --- Fase 3: Catatan terbaru ---
$stmt = $pdo->prepare('SELECT id, title FROM notes WHERE user_id = ? ORDER BY updated_at DESC LIMIT 3');
$stmt->execute([$user['id']]);
$recentNotes = $stmt->fetchAll();

// --- Fase 3: Materi dipelajari ---
$stmt = $pdo->prepare('SELECT COUNT(*) FROM material_completions WHERE user_id = ?');
$stmt->execute([$user['id']]);
$materialsCompletedCount = (int) $stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM materials');
$materialsTotalCount = (int) $stmt->fetchColumn();

// --- Fase 3: Kuis terakhir ---
$stmt = $pdo->prepare(
    'SELECT qa.score, q.title FROM quiz_attempts qa
     JOIN quizzes q ON q.id = qa.quiz_id
     WHERE qa.user_id = ?
     ORDER BY qa.finished_at DESC LIMIT 1'
);
$stmt->execute([$user['id']]);
$lastQuizAttempt = $stmt->fetch();

$pageTitle = 'Beranda';
$activeNav = 'beranda';
include __DIR__ . '/includes/header.php';
?>

<section class="hero-greeting">
    <h1><?= e(greetingByTime()) ?>, <?= e($user['name']) ?> 👋</h1>

    <?php if ($overdueCount > 0): ?>
        <p class="hero-greeting__note hero-greeting__note--warning">
            ⚠️ Kamu memiliki <?= $overdueCount ?> tugas yang sudah melewati deadline.
        </p>
    <?php elseif ((int) $progress['current_streak'] > 0): ?>
        <p class="hero-greeting__note">
            🔥 Mantap! Kamu sudah belajar <?= (int) $progress['current_streak'] ?> hari berturut-turut.
        </p>
    <?php else: ?>
        <p class="hero-greeting__note">🎉 Tidak ada tugas yang tertunda. Yuk mulai sesi fokus!</p>
    <?php endif; ?>
</section>

<section class="stat-row">
    <div class="stat-block">
        <span class="stat-block__label">Fokus belajar hari ini</span>
        <span class="stat-block__value"><?= e(formatMinutesToHours($todayFocusMinutes)) ?></span>
    </div>
    <div class="stat-block stat-block--accent">
        <span class="stat-block__label">🔥 Streak</span>
        <span class="stat-block__value"><?= (int) $progress['current_streak'] ?> Hari</span>
    </div>
    <div class="stat-block">
        <span class="stat-block__label">✅ Tugas selesai</span>
        <span class="stat-block__value"><?= $tasksCompletedThisWeek ?></span>
    </div>
    <div class="stat-block">
        <span class="stat-block__label">🎯 Target minggu ini</span>
        <span class="stat-block__value"><?= $weeklyTarget ? $weeklyTargetPercent . '%' : '-' ?></span>
    </div>
</section>

<section class="dash-grid">
    <div class="panel">
        <div class="panel__head">
            <h2>Tugas Hari Ini <?= $todayTasksTotal > 0 ? '(' . $todayTasksTotal . ')' : '' ?></h2>
            <a href="<?= BASE_URL ?>/tasks.php" class="panel__link">Lihat semua</a>
        </div>

        <?php if (empty($todayTasks)): ?>
            <div class="empty-state">
                <p>Belum ada tugas mendesak.</p>
                <p class="empty-state__sub">Yuk tambahkan tugas pertamamu!</p>
                <a href="<?= BASE_URL ?>/tasks.php?new=1" class="btn btn-primary">+ Tambah Tugas</a>
            </div>
        <?php else: ?>
            <ul class="task-list">
                <?php foreach ($todayTasks as $task): ?>
                    <li class="task-row">
                        <div class="task-row__main">
                            <span class="task-row__title"><?= e($task['title']) ?></span>
                            <span class="task-row__subject"><?= e($task['subject_name'] ?? 'Umum') ?></span>
                        </div>
                        <div class="task-row__meta">
                            <span class="badge badge-priority-<?= e($task['priority']) ?>">
                                <?= e(priorityLabel($task['priority'])) ?>
                            </span>
                            <span class="task-row__deadline"><?= e(formatDeadline($task['deadline'])) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel__head">
            <h2>Jadwal Berikutnya</h2>
            <a href="<?= BASE_URL ?>/planner.php" class="panel__link">Lihat planner</a>
        </div>

        <?php if (!$nextPlan): ?>
            <div class="empty-state">
                <p>Belum ada jadwal belajar.</p>
                <p class="empty-state__sub">Susun rencana belajar mingguanmu.</p>
                <a href="<?= BASE_URL ?>/planner.php?new=1" class="btn btn-secondary">+ Tambah Jadwal</a>
            </div>
        <?php else: ?>
            <div class="schedule-card">
                <span class="schedule-card__day"><?= e((new DateTime($nextPlan['plan_date']))->format('l, d M')) ?></span>
                <span class="schedule-card__time"><?= e(substr($nextPlan['start_time'], 0, 5)) ?></span>
                <span class="schedule-card__subject"><?= e($nextPlan['subject_name'] ?? 'Sesi belajar') ?></span>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="dash-grid dash-grid--three">
    <div class="panel">
        <div class="panel__head">
            <h2>Catatan Terbaru</h2>
            <a href="<?= BASE_URL ?>/notes.php" class="panel__link">Lihat Semua</a>
        </div>
        <?php if (empty($recentNotes)): ?>
            <div class="empty-state empty-state--compact">
                <p class="empty-state__sub">Belum ada catatan.</p>
            </div>
        <?php else: ?>
            <ul class="mini-list">
                <?php foreach ($recentNotes as $note): ?>
                    <li><a href="<?= BASE_URL ?>/notes.php"><?= e($note['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel__head">
            <h2>Materi Dipelajari</h2>
            <a href="<?= BASE_URL ?>/materials.php" class="panel__link">Lanjut Belajar</a>
        </div>
        <p class="mini-stat"><?= $materialsCompletedCount ?> / <?= $materialsTotalCount ?> materi</p>
        <?php if ($materialsTotalCount > 0): ?>
            <div class="progress-bar progress-bar--sm">
                <div class="progress-bar__fill" style="width: <?= min(100, (int) round($materialsCompletedCount / $materialsTotalCount * 100)) ?>%"></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel__head">
            <h2>Kuis Terakhir</h2>
            <a href="<?= BASE_URL ?>/quiz.php?action=history" class="panel__link">Lihat Riwayat</a>
        </div>
        <?php if (!$lastQuizAttempt): ?>
            <div class="empty-state empty-state--compact">
                <p class="empty-state__sub">Belum ada kuis yang dikerjakan.</p>
            </div>
        <?php else: ?>
            <p class="mini-list__title"><?= e($lastQuizAttempt['title']) ?></p>
            <p class="mini-stat">Skor <?= e(rtrim(rtrim((string) $lastQuizAttempt['score'], '0'), '.')) ?: '0' ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel__head">
        <h2>Quick Actions</h2>
    </div>
    <div class="quick-actions">
        <a href="<?= BASE_URL ?>/tasks.php?new=1" class="quick-action">
            <span class="quick-action__icon">✅</span>
            <span>Tambah Tugas</span>
        </a>
        <a href="<?= BASE_URL ?>/focus.php" class="quick-action quick-action--accent">
            <span class="quick-action__icon">⏱️</span>
            <span>Mulai Fokus</span>
        </a>
        <a href="<?= BASE_URL ?>/notes.php?new=1" class="quick-action">
            <span class="quick-action__icon">📝</span>
            <span>Tambah Catatan</span>
        </a>
        <a href="<?= BASE_URL ?>/materials.php" class="quick-action">
            <span class="quick-action__icon">📚</span>
            <span>Lihat Materi</span>
        </a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
