<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
$errors = [];

/* -----------------------------------------------------------
   Actions (create / update / delete)
   Semua query dibatasi user_id = $userId.
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/notes.php');
    }

    if ($action === 'save') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $isImportant = isset($_POST['is_important']) ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 150) {
            $errors[] = 'Judul catatan wajib diisi (maks. 150 karakter).';
        }
        if ($content === '') {
            $errors[] = 'Isi catatan wajib diisi.';
        }

        if (empty($errors)) {
            $subjectId = findOrCreateSubject($pdo, $userId, $subjectName);

            if ($noteId > 0) {
                $check = $pdo->prepare('SELECT id FROM notes WHERE id = ? AND user_id = ?');
                $check->execute([$noteId, $userId]);
                if (!$check->fetch()) {
                    setFlash('error', 'Catatan tidak ditemukan.');
                    redirectTo('/notes.php');
                }

                $stmt = $pdo->prepare(
                    'UPDATE notes SET subject_id = ?, title = ?, content = ?, is_important = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([$subjectId, $title, $content, $isImportant, $noteId, $userId]);
                setFlash('success', 'Catatan berhasil disimpan.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO notes (user_id, subject_id, title, content, is_important)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $subjectId, $title, $content, $isImportant]);
                checkAndUnlockAchievements($pdo, $userId);
                setFlash('success', 'Catatan berhasil disimpan.');
            }
            redirectTo('/notes.php');
        }
    }

    if ($action === 'delete') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([$noteId, $userId]);
        setFlash('success', 'Catatan berhasil dihapus.');
        redirectTo('/notes.php');
    }

    if ($action === 'toggle_important') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE notes SET is_important = NOT is_important WHERE id = ? AND user_id = ?');
        $stmt->execute([$noteId, $userId]);
        redirectTo('/notes.php' . (isset($_GET['redirect_qs']) ? '?' . $_GET['redirect_qs'] : ''));
    }
}

/* -----------------------------------------------------------
   Filter, search, sort
----------------------------------------------------------- */
$q = trim($_GET['q'] ?? '');
$filterSubject = (int) ($_GET['subject_id'] ?? 0);
$sort = in_array($_GET['sort'] ?? '', ['updated_desc', 'important_first', 'title_asc'], true) ? $_GET['sort'] : 'updated_desc';

$where = ['n.user_id = ?'];
$params = [$userId];

if ($q !== '') {
    $where[] = '(n.title LIKE ? OR n.content LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($filterSubject > 0) {
    $where[] = 'n.subject_id = ?';
    $params[] = $filterSubject;
}

$orderBy = match ($sort) {
    'important_first' => 'n.is_important DESC, n.updated_at DESC',
    'title_asc'        => 'n.title ASC',
    default            => 'n.updated_at DESC',
};

$sql = "SELECT n.*, s.name AS subject_name FROM notes n
        LEFT JOIN subjects s ON s.id = n.subject_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

$subjects = getUserSubjects($pdo, $userId);
$csrfToken = generateCsrfToken();
$pageTitle = 'Catatan';
$activeNav = 'catatan';
$openFormOnLoad = isset($_GET['new']) || !empty($errors);
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <h2>Catatan <?= count($notes) > 0 ? '(' . count($notes) . ')' : '' ?></h2>
        <button type="button" class="btn btn-primary" id="btnAddNote">+ Tambah Catatan</button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="GET" class="task-toolbar">
        <input type="search" name="q" placeholder="Cari catatan..." value="<?= e($q) ?>" class="task-toolbar__search">

        <select name="subject_id" onchange="this.form.submit()">
            <option value="0">Semua Mapel</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSubject === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="sort" onchange="this.form.submit()">
            <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Terbaru diperbarui</option>
            <option value="important_first" <?= $sort === 'important_first' ? 'selected' : '' ?>>Penting dulu</option>
            <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Judul A-Z</option>
        </select>

        <button type="submit" class="btn btn-secondary">Cari</button>
    </form>

    <?php if (empty($notes)): ?>
        <div class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">📝</span>
            <p>Belum ada catatan.</p>
            <p class="empty-state__sub">Mulai buat catatan pertamamu untuk menyimpan hal penting dari proses belajar.</p>
            <button type="button" class="btn btn-primary" id="btnAddNoteEmpty">+ Tambah Catatan</button>
        </div>
    <?php else: ?>
        <div class="notes-grid">
            <?php foreach ($notes as $note):
                $noteJson = e(json_encode([
                    'id' => $note['id'],
                    'title' => $note['title'],
                    'subject_name' => $note['subject_name'],
                    'content' => $note['content'],
                    'is_important' => (int) $note['is_important'],
                ]));
            ?>
                <div class="note-card <?= $note['is_important'] ? 'note-card--important' : '' ?>">
                    <div class="note-card__head">
                        <span class="note-card__subject"><?= e($note['subject_name'] ?? 'Umum') ?></span>
                        <form method="POST" class="note-card__star-form">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="action" value="toggle_important">
                            <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                            <button type="submit" class="note-card__star" aria-label="Tandai penting">
                                <?= $note['is_important'] ? '⭐' : '☆' ?>
                            </button>
                        </form>
                    </div>

                    <h3 class="note-card__title"><?= e($note['title']) ?></h3>
                    <p class="note-card__preview">"<?= e(truncateText($note['content'], 110)) ?>"</p>

                    <?php if ($note['is_important']): ?>
                        <span class="note-card__badge">⭐ Penting</span>
                    <?php endif; ?>

                    <div class="note-card__footer">
                        <span class="note-card__date">Diperbarui <?= e((new DateTime($note['updated_at']))->format('d M Y')) ?></span>
                        <div class="note-card__actions">
                            <button type="button" class="icon-btn btn-edit-note" data-note="<?= $noteJson ?>" aria-label="Edit catatan">✏️</button>
                            <form method="POST" onsubmit="return confirm('Hapus catatan ini?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                <button type="submit" class="icon-btn" aria-label="Hapus catatan">🗑️</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Modal tambah/edit catatan -->
<div class="modal-overlay" id="noteModalOverlay">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="noteModalTitle">
        <div class="modal__head">
            <h2 id="noteModalTitle">Tambah Catatan</h2>
            <button type="button" class="icon-btn" id="btnCloseNoteModal" aria-label="Tutup">✕</button>
        </div>

        <form method="POST" class="modal-form" id="noteForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="note_id" id="n_note_id" value="">

            <label for="n_title">Judul Catatan</label>
            <input type="text" id="n_title" name="title" required maxlength="150" placeholder="Contoh: Persamaan Linear">

            <label for="n_subject_name">Mapel</label>
            <input type="text" id="n_subject_name" name="subject_name" placeholder="Contoh: Matematika" list="subjectSuggestions">
            <datalist id="subjectSuggestions">
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= e($s['name']) ?>">
                <?php endforeach; ?>
            </datalist>

            <label for="n_content">Isi Catatan</label>
            <textarea id="n_content" name="content" rows="6" required placeholder="Tulis catatanmu di sini..."></textarea>

            <label class="checkbox-label">
                <input type="checkbox" id="n_is_important" name="is_important" value="1">
                Tandai sebagai penting
            </label>

            <button type="submit" class="btn btn-primary btn-block">Simpan</button>
        </form>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('noteModalOverlay');
    var modalTitle = document.getElementById('noteModalTitle');

    function openModal(note) {
        document.getElementById('noteForm').reset();
        document.getElementById('n_note_id').value = '';
        modalTitle.textContent = 'Tambah Catatan';

        if (note) {
            modalTitle.textContent = 'Edit Catatan';
            document.getElementById('n_note_id').value = note.id;
            document.getElementById('n_title').value = note.title;
            document.getElementById('n_subject_name').value = note.subject_name || '';
            document.getElementById('n_content').value = note.content || '';
            document.getElementById('n_is_important').checked = note.is_important === 1;
        }
        overlay.classList.add('is-visible');
    }
    function closeModal() { overlay.classList.remove('is-visible'); }

    document.getElementById('btnAddNote').addEventListener('click', function () { openModal(null); });
    var btnEmpty = document.getElementById('btnAddNoteEmpty');
    if (btnEmpty) btnEmpty.addEventListener('click', function () { openModal(null); });

    document.getElementById('btnCloseNoteModal').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

    document.querySelectorAll('.btn-edit-note').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(JSON.parse(this.getAttribute('data-note'))); });
    });

    <?php if ($openFormOnLoad): ?>
    openModal(null);
    <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
