<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
$errors = [];

/* -----------------------------------------------------------
   Actions (create / update / delete / toggle status)
   Semua query dibatasi user_id = $userId — user tidak pernah
   bisa menyentuh data milik user lain.
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/tasks.php');
    }

    if ($action === 'save') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['tinggi', 'sedang', 'rendah'], true) ? $_POST['priority'] : 'sedang';
        $status = in_array($_POST['status'] ?? '', ['belum_selesai', 'sedang_dikerjakan', 'selesai'], true) ? $_POST['status'] : 'belum_selesai';
        $deadlineRaw = trim($_POST['deadline'] ?? '');
        // Input <datetime-local> mengirim format "2026-09-20T10:00" (pemisah "T", bukan spasi).
        $deadline = null;
        if ($deadlineRaw !== '') {
            $normalized = str_replace('T', ' ', $deadlineRaw) . ':00';
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $normalized);
            if ($d) {
                $deadline = $d->format('Y-m-d H:i:s');
            } else {
                $errors[] = 'Format deadline tidak valid.';
            }
        }

        if ($title === '' || mb_strlen($title) > 150) {
            $errors[] = 'Judul tugas wajib diisi (maks. 150 karakter).';
        }

        if (empty($errors)) {
            $subjectId = findOrCreateSubject($pdo, $userId, $subjectName);

            if ($taskId > 0) {
                // Pastikan task ini benar-benar milik user yang login.
                $check = $pdo->prepare('SELECT status, completion_rewarded FROM tasks WHERE id = ? AND user_id = ?');
                $check->execute([$taskId, $userId]);
                $existing = $check->fetch();

                if (!$existing) {
                    setFlash('error', 'Tugas tidak ditemukan.');
                    redirectTo('/tasks.php');
                }

                $completedAt = $status === 'selesai' ? 'NOW()' : 'NULL';
                $stmt = $pdo->prepare(
                    "UPDATE tasks SET subject_id = ?, title = ?, description = ?, priority = ?, status = ?,
                        deadline = ?, completed_at = " . $completedAt . "
                     WHERE id = ? AND user_id = ?"
                );
                $stmt->execute([$subjectId, $title, $description, $priority, $status, $deadline, $taskId, $userId]);

                if ($existing['status'] !== 'selesai' && $status === 'selesai' && (int) $existing['completion_rewarded'] === 0) {
                    $rewardStmt = $pdo->prepare('UPDATE tasks SET completion_rewarded = 1 WHERE id = ? AND user_id = ? AND completion_rewarded = 0');
                    $rewardStmt->execute([$taskId, $userId]);
                    if ($rewardStmt->rowCount() === 1) {
                        recordTaskCompletion($pdo, $userId);
                    }
                }

                setFlash('success', 'Tugas berhasil diperbarui.');
            } else {
                $completedAt = $status === 'selesai' ? 'NOW()' : 'NULL';
                $stmt = $pdo->prepare(
                    "INSERT INTO tasks (user_id, subject_id, title, description, priority, status, deadline, completed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, " . $completedAt . ")"
                );
                $stmt->execute([$userId, $subjectId, $title, $description, $priority, $status, $deadline]);
                $newTaskId = (int) $pdo->lastInsertId();

                if ($status === 'selesai') {
                    $rewardStmt = $pdo->prepare('UPDATE tasks SET completion_rewarded = 1 WHERE id = ? AND user_id = ? AND completion_rewarded = 0');
                    $rewardStmt->execute([$newTaskId, $userId]);
                    if ($rewardStmt->rowCount() === 1) {
                        recordTaskCompletion($pdo, $userId);
                    }
                }

                setFlash('success', 'Tugas berhasil ditambahkan.');
            }
            redirectTo('/tasks.php');
        }
    }

    if ($action === 'delete') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        setFlash('success', 'Tugas berhasil dihapus.');
        redirectTo('/tasks.php');
    }

    if ($action === 'toggle_status') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT status, completion_rewarded FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $newStatus = $existing['status'] === 'selesai' ? 'belum_selesai' : 'selesai';
            $completedAt = $newStatus === 'selesai' ? 'NOW()' : 'NULL';
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = $completedAt WHERE id = ? AND user_id = ?");
            $stmt->execute([$newStatus, $taskId, $userId]);

            if ($newStatus === 'selesai') {
                if ((int) $existing['completion_rewarded'] === 0) {
                    $rewardStmt = $pdo->prepare('UPDATE tasks SET completion_rewarded = 1 WHERE id = ? AND user_id = ? AND completion_rewarded = 0');
                    $rewardStmt->execute([$taskId, $userId]);
                    if ($rewardStmt->rowCount() === 1) {
                        recordTaskCompletion($pdo, $userId);
                    }
                }
                setFlash('success', 'Tugas selesai! 🎉');
            } else {
                setFlash('success', 'Tugas ditandai belum selesai.');
            }
        }
        redirectTo('/tasks.php' . (isset($_GET['redirect_qs']) ? '?' . $_GET['redirect_qs'] : ''));
    }
}

/* -----------------------------------------------------------
   Filter, search, sort (semua via GET supaya bisa di-bookmark)
----------------------------------------------------------- */
$q = trim($_GET['q'] ?? '');
$filterPriority = in_array($_GET['priority'] ?? '', ['tinggi', 'sedang', 'rendah'], true) ? $_GET['priority'] : '';
$filterStatus = in_array($_GET['status'] ?? '', ['belum_selesai', 'sedang_dikerjakan', 'selesai'], true) ? $_GET['status'] : '';
$filterSubject = (int) ($_GET['subject_id'] ?? 0);
$sort = in_array($_GET['sort'] ?? '', ['deadline_asc', 'created_desc', 'priority_desc'], true) ? $_GET['sort'] : 'deadline_asc';

$where = ['t.user_id = ?'];
$params = [$userId];

if ($q !== '') {
    $where[] = '(t.title LIKE ? OR s.name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($filterPriority !== '') {
    $where[] = 't.priority = ?';
    $params[] = $filterPriority;
}
if ($filterStatus !== '') {
    $where[] = 't.status = ?';
    $params[] = $filterStatus;
}
if ($filterSubject > 0) {
    $where[] = 't.subject_id = ?';
    $params[] = $filterSubject;
}

$orderBy = match ($sort) {
    'created_desc'  => 't.created_at DESC',
    'priority_desc' => "FIELD(t.priority, 'tinggi', 'sedang', 'rendah') ASC",
    default         => 't.deadline IS NULL, t.deadline ASC',
};

$sql = "SELECT t.*, s.name AS subject_name FROM tasks t
        LEFT JOIN subjects s ON s.id = t.subject_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$subjects = getUserSubjects($pdo, $userId);

$overdueCount = 0;
foreach ($tasks as $t) {
    if (isTaskOverdue($t['deadline'], $t['status'])) {
        $overdueCount++;
    }
}

$queryStringForToggle = $_SERVER['QUERY_STRING'] ?? '';
$csrfToken = generateCsrfToken();
$pageTitle = 'Tugas';
$activeNav = 'tugas';
$openFormOnLoad = isset($_GET['new']) || !empty($errors);
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <h2>Tugas <?= count($tasks) > 0 ? '(' . count($tasks) . ')' : '' ?></h2>
        <button type="button" class="btn btn-primary" id="btnAddTask">+ Tambah Tugas</button>
    </div>

    <?php if ($overdueCount > 0): ?>
        <p class="task-overdue-banner">⚠️ <?= $overdueCount ?> tugas sudah melewati deadline.</p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="GET" class="task-toolbar">
        <input type="search" name="q" placeholder="Cari tugas..." value="<?= e($q) ?>" class="task-toolbar__search">

        <select name="priority" onchange="this.form.submit()">
            <option value="">Semua Prioritas</option>
            <option value="tinggi" <?= $filterPriority === 'tinggi' ? 'selected' : '' ?>>Tinggi</option>
            <option value="sedang" <?= $filterPriority === 'sedang' ? 'selected' : '' ?>>Sedang</option>
            <option value="rendah" <?= $filterPriority === 'rendah' ? 'selected' : '' ?>>Rendah</option>
        </select>

        <select name="status" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="belum_selesai" <?= $filterStatus === 'belum_selesai' ? 'selected' : '' ?>>Belum selesai</option>
            <option value="sedang_dikerjakan" <?= $filterStatus === 'sedang_dikerjakan' ? 'selected' : '' ?>>Sedang dikerjakan</option>
            <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
        </select>

        <select name="subject_id" onchange="this.form.submit()">
            <option value="0">Semua Mapel</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSubject === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="sort" onchange="this.form.submit()">
            <option value="deadline_asc" <?= $sort === 'deadline_asc' ? 'selected' : '' ?>>Deadline terdekat</option>
            <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>Terbaru dibuat</option>
            <option value="priority_desc" <?= $sort === 'priority_desc' ? 'selected' : '' ?>>Prioritas tertinggi</option>
        </select>

        <button type="submit" class="btn btn-secondary">Cari</button>
    </form>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">✅</span>
            <p>Belum ada tugas.</p>
            <p class="empty-state__sub">Yuk tambahkan tugas pertamamu!</p>
            <button type="button" class="btn btn-primary" id="btnAddTaskEmpty">+ Tambah Tugas</button>
        </div>
    <?php else: ?>
        <ul class="task-list task-list--full">
            <?php foreach ($tasks as $task):
                $overdue = isTaskOverdue($task['deadline'], $task['status']);
                $taskJson = e(json_encode([
                    'id' => $task['id'],
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'subject_name' => $task['subject_name'],
                    'priority' => $task['priority'],
                    'status' => $task['status'],
                    'deadline' => $task['deadline'] ? substr($task['deadline'], 0, 16) : '',
                ]));
            ?>
                <li class="task-row <?= $overdue ? 'task-row--overdue' : '' ?>">
                    <form method="POST" class="task-row__check">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" class="task-check <?= $task['status'] === 'selesai' ? 'is-checked' : '' ?>" aria-label="Tandai selesai">
                            <?= $task['status'] === 'selesai' ? '✓' : '' ?>
                        </button>
                    </form>

                    <div class="task-row__main">
                        <span class="task-row__title <?= $task['status'] === 'selesai' ? 'is-done' : '' ?>"><?= e($task['title']) ?></span>
                        <span class="task-row__subject"><?= e($task['subject_name'] ?? 'Umum') ?> · <?= e(statusLabel($task['status'])) ?></span>
                    </div>

                    <div class="task-row__meta">
                        <span class="badge badge-priority-<?= e($task['priority']) ?>"><?= e(priorityLabel($task['priority'])) ?></span>
                        <?php if ($overdue): ?>
                            <span class="badge badge-overdue">Deadline sudah lewat</span>
                        <?php elseif ($task['deadline']): ?>
                            <span class="task-row__deadline"><?= e(formatDeadline($task['deadline'])) ?></span>
                        <?php endif; ?>

                        <button type="button" class="icon-btn btn-edit-task" data-task="<?= $taskJson ?>" aria-label="Edit tugas">✏️</button>

                        <form method="POST" onsubmit="return confirm('Hapus tugas ini?');" class="task-row__delete-form">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <button type="submit" class="icon-btn" aria-label="Hapus tugas">🗑️</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<!-- Modal tambah/edit tugas -->
<div class="modal-overlay" id="taskModalOverlay">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle">
        <div class="modal__head">
            <h2 id="taskModalTitle">Tambah Tugas</h2>
            <button type="button" class="icon-btn" id="btnCloseTaskModal" aria-label="Tutup">✕</button>
        </div>

        <form method="POST" class="modal-form" id="taskForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="task_id" id="f_task_id" value="">

            <label for="f_title">Judul tugas</label>
            <input type="text" id="f_title" name="title" required maxlength="150" placeholder="Contoh: Belajar JavaScript">

            <label for="f_subject_name">Mapel</label>
            <input type="text" id="f_subject_name" name="subject_name" placeholder="Contoh: RPL" list="subjectSuggestions">
            <datalist id="subjectSuggestions">
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= e($s['name']) ?>">
                <?php endforeach; ?>
            </datalist>

            <label for="f_description">Deskripsi (opsional)</label>
            <textarea id="f_description" name="description" rows="3" placeholder="Detail tambahan..."></textarea>

            <div class="modal-form__row">
                <div>
                    <label for="f_priority">Prioritas</label>
                    <select id="f_priority" name="priority">
                        <option value="tinggi">Tinggi</option>
                        <option value="sedang" selected>Sedang</option>
                        <option value="rendah">Rendah</option>
                    </select>
                </div>
                <div>
                    <label for="f_status">Status</label>
                    <select id="f_status" name="status">
                        <option value="belum_selesai">Belum selesai</option>
                        <option value="sedang_dikerjakan">Sedang dikerjakan</option>
                        <option value="selesai">Selesai</option>
                    </select>
                </div>
            </div>

            <label for="f_deadline">Deadline (opsional)</label>
            <input type="datetime-local" id="f_deadline" name="deadline">

            <button type="submit" class="btn btn-primary btn-block">Simpan Tugas</button>
        </form>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('taskModalOverlay');
    var form = document.getElementById('taskForm');
    var modalTitle = document.getElementById('taskModalTitle');

    function openModal(task) {
        form.reset();
        document.getElementById('f_task_id').value = '';
        modalTitle.textContent = 'Tambah Tugas';

        if (task) {
            modalTitle.textContent = 'Edit Tugas';
            document.getElementById('f_task_id').value = task.id;
            document.getElementById('f_title').value = task.title;
            document.getElementById('f_subject_name').value = task.subject_name || '';
            document.getElementById('f_description').value = task.description || '';
            document.getElementById('f_priority').value = task.priority;
            document.getElementById('f_status').value = task.status;
            document.getElementById('f_deadline').value = task.deadline || '';
        }
        overlay.classList.add('is-visible');
    }

    function closeModal() {
        overlay.classList.remove('is-visible');
    }

    document.getElementById('btnAddTask').addEventListener('click', function () { openModal(null); });
    var btnEmpty = document.getElementById('btnAddTaskEmpty');
    if (btnEmpty) btnEmpty.addEventListener('click', function () { openModal(null); });

    document.getElementById('btnCloseTaskModal').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

    document.querySelectorAll('.btn-edit-task').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(JSON.parse(this.getAttribute('data-task')));
        });
    });

    <?php if ($openFormOnLoad): ?>
    openModal(null);
    <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
