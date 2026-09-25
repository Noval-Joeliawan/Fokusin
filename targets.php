<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
$errors = [];

function mondayOfWeek(string $date): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d) return (new DateTime('monday this week'))->format('Y-m-d');
    $d->modify('monday this week');
    return $d->format('Y-m-d');
}

$weekStart = mondayOfWeek(date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/targets.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $targetHours = (float) ($_POST['target_hours'] ?? 0);
        $targetTasks = (int) ($_POST['target_tasks'] ?? 0);
        $targetQuizzes = (int) ($_POST['target_quizzes'] ?? 0);
        $targetMaterials = (int) ($_POST['target_materials'] ?? 0);
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $subjectId = $subjectId > 0 ? $subjectId : null;

        if ($targetHours < 0 || $targetHours > 168) $errors[] = 'Target jam harus antara 0 dan 168 jam.';
        if ($targetTasks < 0 || $targetTasks > 500) $errors[] = 'Target tugas tidak valid.';
        if ($targetQuizzes < 0 || $targetQuizzes > 500) $errors[] = 'Target kuis tidak valid.';
        if ($targetMaterials < 0 || $targetMaterials > 500) $errors[] = 'Target materi tidak valid.';

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO study_targets
                (user_id, subject_id, week_start_date, target_hours, target_tasks, target_quizzes, target_materials)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), target_hours = VALUES(target_hours),
                    target_tasks = VALUES(target_tasks), target_quizzes = VALUES(target_quizzes), target_materials = VALUES(target_materials)');
            $stmt->execute([$userId, $subjectId, $weekStart, $targetHours, $targetTasks, $targetQuizzes, $targetMaterials]);
            setFlash('success', 'Target minggu ini berhasil disimpan.');
            redirectTo('/targets.php');
        }
    }
}

$stmt = $pdo->prepare('SELECT st.*, s.name AS subject_name FROM study_targets st LEFT JOIN subjects s ON s.id = st.subject_id WHERE st.user_id = ? AND st.week_start_date = ? LIMIT 1');
$stmt->execute([$userId, $weekStart]);
$target = $stmt->fetch();

$weekEnd = (new DateTime($weekStart))->modify('+6 days')->format('Y-m-d');
$stmt = $pdo->prepare("SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND is_completed = 1 AND DATE(started_at) BETWEEN ? AND ?");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$actualHours = (int) $stmt->fetchColumn() / 60;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'selesai' AND completed_at IS NOT NULL AND DATE(completed_at) BETWEEN ? AND ?");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$actualTasks = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND DATE(finished_at) BETWEEN ? AND ?');
$stmt->execute([$userId, $weekStart, $weekEnd]);
$actualQuizzes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM material_completions WHERE user_id = ? AND DATE(completed_at) BETWEEN ? AND ?');
$stmt->execute([$userId, $weekStart, $weekEnd]);
$actualMaterials = (int) $stmt->fetchColumn();

$metrics = [
    ['label' => 'Waktu belajar', 'actual' => $actualHours, 'target' => (float) ($target['target_hours'] ?? 0), 'unit' => 'jam', 'icon' => '⏱️'],
    ['label' => 'Tugas selesai', 'actual' => $actualTasks, 'target' => (int) ($target['target_tasks'] ?? 0), 'unit' => 'tugas', 'icon' => '✅'],
    ['label' => 'Kuis selesai', 'actual' => $actualQuizzes, 'target' => (int) ($target['target_quizzes'] ?? 0), 'unit' => 'kuis', 'icon' => '🧠'],
    ['label' => 'Materi dipelajari', 'actual' => $actualMaterials, 'target' => (int) ($target['target_materials'] ?? 0), 'unit' => 'materi', 'icon' => '📚'],
];

$subjects = getUserSubjects($pdo, $userId);
$csrfToken = generateCsrfToken();
$pageTitle = 'Target';
$activeNav = 'target';
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <div>
            <h2>Target Minggu Ini</h2>
            <p class="panel__sub"><?= e((new DateTime($weekStart))->format('d M Y')) ?> — <?= e((new DateTime($weekEnd))->format('d M Y')) ?></p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="target-grid">
        <?php foreach ($metrics as $metric): ?>
            <?php $percent = $metric['target'] > 0 ? min(100, (int) round(($metric['actual'] / $metric['target']) * 100)) : 0; ?>
            <div class="target-card">
                <div class="target-card__head"><span><?= $metric['icon'] ?> <?= e($metric['label']) ?></span><strong><?= e(rtrim(rtrim(number_format($metric['actual'], 1, '.', ''), '0'), '.')) ?>/<?= e(rtrim(rtrim(number_format($metric['target'], 1, '.', ''), '0'), '.')) ?></strong></div>
                <div class="progress-bar"><div class="progress-bar__fill" style="width: <?= $percent ?>%"></div></div>
                <span class="target-card__meta"><?= $percent ?>% tercapai<?= $metric['target'] > 0 ? ' · ' . e($metric['unit']) : ' · belum ditargetkan' ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="panel__head"><h2>Atur Target</h2></div>
    <form method="POST" class="target-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="save">
        <div class="modal-form__row">
            <div><label for="target_hours">Jam belajar</label><input id="target_hours" type="number" name="target_hours" min="0" max="168" step="0.5" value="<?= e($target['target_hours'] ?? '5') ?>"></div>
            <div><label for="target_tasks">Tugas selesai</label><input id="target_tasks" type="number" name="target_tasks" min="0" max="500" value="<?= e($target['target_tasks'] ?? '5') ?>"></div>
        </div>
        <div class="modal-form__row">
            <div><label for="target_quizzes">Kuis selesai</label><input id="target_quizzes" type="number" name="target_quizzes" min="0" max="500" value="<?= e($target['target_quizzes'] ?? '2') ?>"></div>
            <div><label for="target_materials">Materi dipelajari</label><input id="target_materials" type="number" name="target_materials" min="0" max="500" value="<?= e($target['target_materials'] ?? '3') ?>"></div>
        </div>
        <div><label for="subject_id">Mapel fokus <span class="form-hint">(opsional)</span></label><select id="subject_id" name="subject_id"><option value="0">Semua mapel</option><?php foreach ($subjects as $subject): ?><option value="<?= $subject['id'] ?>" <?= isset($target['subject_id']) && (int) $target['subject_id'] === (int) $subject['id'] ? 'selected' : '' ?>><?= e($subject['name']) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-primary" type="submit">Simpan Target</button>
    </form>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
