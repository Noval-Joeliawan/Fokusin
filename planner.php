<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
$errors = [];

/* -----------------------------------------------------------
   Actions
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/planner.php');
    }

    if ($action === 'save') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $subjectName = trim($_POST['subject_name'] ?? '');
        $planDate = trim($_POST['plan_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($subjectName === '') {
            $errors[] = 'Judul/mapel wajib diisi.';
        }
        $dateObject = null;
        if ($planDate === '') {
            $errors[] = 'Tanggal wajib diisi.';
        } else {
            $dateObject = DateTime::createFromFormat('!Y-m-d', $planDate);
            $dateErrors = DateTime::getLastErrors();
            $hasDateErrors = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
            if (!$dateObject || $hasDateErrors || $dateObject->format('Y-m-d') !== $planDate) {
                $errors[] = 'Tanggal tidak valid.';
            }
        }

        $startObject = $startTime !== '' ? DateTime::createFromFormat('!H:i', $startTime) : false;
        $endObject = $endTime !== '' ? DateTime::createFromFormat('!H:i', $endTime) : false;
        $startErrors = DateTime::getLastErrors();
        $endErrors = DateTime::getLastErrors();
        $startInvalid = $startTime !== '' && (!$startObject || (is_array($startErrors) && ($startErrors['warning_count'] > 0 || $startErrors['error_count'] > 0)) || $startObject->format('H:i') !== $startTime);
        $endInvalid = $endTime !== '' && (!$endObject || (is_array($endErrors) && ($endErrors['warning_count'] > 0 || $endErrors['error_count'] > 0)) || $endObject->format('H:i') !== $endTime);

        if ($startTime === '' || $endTime === '') {
            $errors[] = 'Jam mulai dan jam selesai wajib diisi.';
        } elseif ($startInvalid || $endInvalid) {
            $errors[] = 'Format jam tidak valid.';
        } elseif ($endTime <= $startTime) {
            $errors[] = 'Jam selesai harus setelah jam mulai.';
        }

        if (empty($errors)) {
            $subjectId = findOrCreateSubject($pdo, $userId, $subjectName);

            if ($planId > 0) {
                $check = $pdo->prepare('SELECT id FROM study_plans WHERE id = ? AND user_id = ?');
                $check->execute([$planId, $userId]);
                if (!$check->fetch()) {
                    setFlash('error', 'Jadwal tidak ditemukan.');
                    redirectTo('/planner.php');
                }

                $stmt = $pdo->prepare(
                    'UPDATE study_plans SET subject_id = ?, plan_date = ?, start_time = ?, end_time = ?, notes = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([$subjectId, $planDate, $startTime, $endTime, $notes, $planId, $userId]);
                setFlash('success', 'Jadwal berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO study_plans (user_id, subject_id, plan_date, start_time, end_time, notes)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $subjectId, $planDate, $startTime, $endTime, $notes]);
                setFlash('success', 'Jadwal berhasil ditambahkan.');
            }
            redirectTo('/planner.php?view=' . ($_POST['return_view'] ?? 'list'));
        }
    }

    if ($action === 'delete') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM study_plans WHERE id = ? AND user_id = ?');
        $stmt->execute([$planId, $userId]);
        setFlash('success', 'Jadwal berhasil dihapus.');
        redirectTo('/planner.php?view=' . ($_POST['return_view'] ?? 'list'));
    }
}

/* -----------------------------------------------------------
   View state
----------------------------------------------------------- */
$view = ($_GET['view'] ?? 'list') === 'calendar' ? 'calendar' : 'list';
$subjects = getUserSubjects($pdo, $userId);
$csrfToken = generateCsrfToken();

if ($view === 'list') {
    $stmt = $pdo->prepare(
        "SELECT sp.*, s.name AS subject_name FROM study_plans sp
         LEFT JOIN subjects s ON s.id = sp.subject_id
         WHERE sp.user_id = ?
         ORDER BY sp.plan_date ASC, sp.start_time ASC"
    );
    $stmt->execute([$userId]);
    $allPlans = $stmt->fetchAll();

    $grouped = [];
    foreach ($allPlans as $plan) {
        $grouped[$plan['plan_date']][] = $plan;
    }
} else {
    $monthParam = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
        $monthParam = date('Y-m');
    }
    $monthStart = DateTime::createFromFormat('!Y-m-d', $monthParam . '-01');
    $monthEnd = (clone $monthStart)->modify('last day of this month');

    $stmt = $pdo->prepare(
        "SELECT sp.*, s.name AS subject_name FROM study_plans sp
         LEFT JOIN subjects s ON s.id = sp.subject_id
         WHERE sp.user_id = ? AND sp.plan_date BETWEEN ? AND ?
         ORDER BY sp.plan_date ASC, sp.start_time ASC"
    );
    $stmt->execute([$userId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
    $monthPlans = $stmt->fetchAll();

    $plansByDate = [];
    foreach ($monthPlans as $plan) {
        $plansByDate[$plan['plan_date']][] = $plan;
    }

    $prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
    $nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');
}

$pageTitle = 'Planner';
$activeNav = 'planner';
$openFormOnLoad = isset($_GET['new']) || !empty($errors);
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <h2>Study Planner</h2>
        <button type="button" class="btn btn-primary" id="btnAddPlan">+ Tambah Jadwal</button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="view-toggle">
        <a href="<?= BASE_URL ?>/planner.php?view=list" class="view-toggle__btn <?= $view === 'list' ? 'is-active' : '' ?>">List View</a>
        <a href="<?= BASE_URL ?>/planner.php?view=calendar" class="view-toggle__btn <?= $view === 'calendar' ? 'is-active' : '' ?>">Calendar View</a>
    </div>

    <?php if ($view === 'list'): ?>
        <?php if (empty($allPlans)): ?>
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">📅</span>
                <p>Belum ada jadwal belajar.</p>
                <p class="empty-state__sub">Susun rencana belajar mingguanmu.</p>
                <button type="button" class="btn btn-primary" id="btnAddPlanEmpty">+ Tambah Jadwal</button>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $date => $plans):
                $dt = new DateTime($date);
                $today = new DateTime('today');
                $label = $dt->format('Y-m-d') === $today->format('Y-m-d') ? 'Hari Ini'
                    : ($dt->format('Y-m-d') === $today->modify('+1 day')->format('Y-m-d') ? 'Besok' : $dt->format('l, d M Y'));
            ?>
                <div class="planner-day-group">
                    <h3 class="planner-day-group__label"><?= e($label) ?></h3>
                    <ul class="planner-list">
                        <?php foreach ($plans as $plan):
                            $planJson = e(json_encode([
                                'id' => $plan['id'],
                                'subject_name' => $plan['subject_name'],
                                'plan_date' => $plan['plan_date'],
                                'start_time' => substr($plan['start_time'], 0, 5),
                                'end_time' => substr($plan['end_time'], 0, 5),
                                'notes' => $plan['notes'],
                            ]));
                        ?>
                            <li class="planner-row">
                                <div class="planner-row__time">
                                    <?= e(substr($plan['start_time'], 0, 5)) ?> - <?= e(substr($plan['end_time'], 0, 5)) ?>
                                </div>
                                <div class="planner-row__main">
                                    <span class="planner-row__subject"><?= e($plan['subject_name'] ?? 'Sesi belajar') ?></span>
                                    <?php if ($plan['notes']): ?>
                                        <span class="planner-row__notes"><?= e($plan['notes']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="planner-row__actions">
                                    <button type="button" class="icon-btn btn-edit-plan" data-plan="<?= $planJson ?>" aria-label="Edit jadwal">✏️</button>
                                    <form method="POST" onsubmit="return confirm('Hapus jadwal ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                        <input type="hidden" name="return_view" value="list">
                                        <button type="submit" class="icon-btn" aria-label="Hapus jadwal">🗑️</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: /* calendar view */ ?>
        <div class="calendar-nav">
            <a href="<?= BASE_URL ?>/planner.php?view=calendar&month=<?= $prevMonth ?>" class="btn btn-secondary">‹ Bulan Lalu</a>
            <span class="calendar-nav__title"><?= e($monthStart->format('F Y')) ?></span>
            <a href="<?= BASE_URL ?>/planner.php?view=calendar&month=<?= $nextMonth ?>" class="btn btn-secondary">Bulan Depan ›</a>
        </div>

        <div class="calendar-grid">
            <?php foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $dayName): ?>
                <div class="calendar-grid__weekday"><?= $dayName ?></div>
            <?php endforeach; ?>

            <?php
            $firstWeekday = (int) $monthStart->format('N'); // 1 = Senin
            for ($i = 1; $i < $firstWeekday; $i++) {
                echo '<div class="calendar-cell calendar-cell--empty"></div>';
            }

            $daysInMonth = (int) $monthStart->format('t');
            $todayStr = (new DateTime('today'))->format('Y-m-d');

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $cellDate = $monthStart->format('Y-m') . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                $isToday = $cellDate === $todayStr;
                $dayPlans = $plansByDate[$cellDate] ?? [];
                echo '<div class="calendar-cell' . ($isToday ? ' calendar-cell--today' : '') . '">';
                echo '<span class="calendar-cell__date">' . $d . '</span>';
                if (!empty($dayPlans)) {
                    echo '<span class="calendar-cell__dot" title="' . count($dayPlans) . ' jadwal"></span>';
                    foreach (array_slice($dayPlans, 0, 2) as $p) {
                        echo '<span class="calendar-cell__item">' . e(substr($p['subject_name'] ?? 'Belajar', 0, 12)) . '</span>';
                    }
                    if (count($dayPlans) > 2) {
                        echo '<span class="calendar-cell__more">+' . (count($dayPlans) - 2) . ' lagi</span>';
                    }
                }
                echo '</div>';
            }
            ?>
        </div>
    <?php endif; ?>
</section>

<!-- Modal tambah/edit jadwal -->
<div class="modal-overlay" id="planModalOverlay">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="planModalTitle">
        <div class="modal__head">
            <h2 id="planModalTitle">Tambah Jadwal</h2>
            <button type="button" class="icon-btn" id="btnClosePlanModal" aria-label="Tutup">✕</button>
        </div>

        <form method="POST" class="modal-form" id="planForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="plan_id" id="p_plan_id" value="">
            <input type="hidden" name="return_view" value="<?= e($view) ?>">

            <label for="p_subject_name">Judul / Mapel</label>
            <input type="text" id="p_subject_name" name="subject_name" required placeholder="Contoh: JavaScript" list="subjectSuggestions">
            <datalist id="subjectSuggestions">
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= e($s['name']) ?>">
                <?php endforeach; ?>
            </datalist>

            <label for="p_plan_date">Tanggal</label>
            <input type="date" id="p_plan_date" name="plan_date" required>

            <div class="modal-form__row">
                <div>
                    <label for="p_start_time">Jam mulai</label>
                    <input type="time" id="p_start_time" name="start_time" required>
                </div>
                <div>
                    <label for="p_end_time">Jam selesai</label>
                    <input type="time" id="p_end_time" name="end_time" required>
                </div>
            </div>

            <label for="p_notes">Catatan (opsional)</label>
            <textarea id="p_notes" name="notes" rows="2" placeholder="Contoh: Belajar DOM"></textarea>

            <button type="submit" class="btn btn-primary btn-block">Simpan Jadwal</button>
        </form>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('planModalOverlay');
    var modalTitle = document.getElementById('planModalTitle');

    function openModal(plan) {
        document.getElementById('planForm').reset();
        document.getElementById('p_plan_id').value = '';
        modalTitle.textContent = 'Tambah Jadwal';

        if (plan) {
            modalTitle.textContent = 'Edit Jadwal';
            document.getElementById('p_plan_id').value = plan.id;
            document.getElementById('p_subject_name').value = plan.subject_name || '';
            document.getElementById('p_plan_date').value = plan.plan_date;
            document.getElementById('p_start_time').value = plan.start_time;
            document.getElementById('p_end_time').value = plan.end_time;
            document.getElementById('p_notes').value = plan.notes || '';
        } else {
            document.getElementById('p_plan_date').value = new Date().toISOString().slice(0, 10);
        }
        overlay.classList.add('is-visible');
    }
    function closeModal() { overlay.classList.remove('is-visible'); }

    document.getElementById('btnAddPlan').addEventListener('click', function () { openModal(null); });
    var btnEmpty = document.getElementById('btnAddPlanEmpty');
    if (btnEmpty) btnEmpty.addEventListener('click', function () { openModal(null); });

    document.getElementById('btnClosePlanModal').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

    document.querySelectorAll('.btn-edit-plan').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(JSON.parse(this.getAttribute('data-plan'))); });
    });

    <?php if ($openFormOnLoad): ?>
    openModal(null);
    <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
