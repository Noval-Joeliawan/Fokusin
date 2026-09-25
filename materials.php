<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];

$materialId = (int) ($_GET['id'] ?? 0);

/* -----------------------------------------------------------
   Tandai materi selesai — idempotent lewat UNIQUE(user_id, material_id).
   Jika baris sudah ada, INSERT akan gagal (duplicate) dan kita TIDAK
   memberi XP lagi.
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_complete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/materials.php?id=' . (int) ($_POST['material_id'] ?? 0));
    }

    $targetId = (int) ($_POST['material_id'] ?? 0);
    $check = $pdo->prepare('SELECT id FROM materials WHERE id = ?');
    $check->execute([$targetId]);
    if (!$check->fetch()) {
        setFlash('error', 'Materi tidak ditemukan.');
        redirectTo('/materials.php');
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO material_completions (user_id, material_id) VALUES (?, ?)');
        $stmt->execute([$userId, $targetId]);
        recordMaterialCompletion($pdo, $userId);
        setFlash('success', 'Materi ditandai sebagai selesai. 🎉');
    } catch (PDOException $e) {
        // Kode 23000 = duplicate entry -> sudah pernah ditandai selesai sebelumnya, tidak diberi XP lagi.
        if ($e->getCode() === '23000') {
            setFlash('success', 'Materi ini sudah kamu tandai selesai sebelumnya.');
        } else {
            error_log('Mark material complete failed: ' . $e->getMessage());
            setFlash('error', 'Terjadi kesalahan. Silakan coba lagi.');
        }
    }
    redirectTo('/materials.php?id=' . $targetId);
}

$pageTitle = 'Materi';
$activeNav = 'materi';

/* =============================================================
   DETAIL VIEW
============================================================= */
if ($materialId > 0) {
    $stmt = $pdo->prepare(
        "SELECT m.*, s.name AS subject_name FROM materials m
         LEFT JOIN subjects s ON s.id = m.subject_id
         WHERE m.id = ?"
    );
    $stmt->execute([$materialId]);
    $material = $stmt->fetch();

    if (!$material) {
        setFlash('error', 'Materi tidak ditemukan.');
        redirectTo('/materials.php');
    }

    $stmt = $pdo->prepare('SELECT id FROM material_completions WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
    $isCompleted = (bool) $stmt->fetch();

    $csrfToken = generateCsrfToken();
    include __DIR__ . '/includes/header.php';
    ?>

    <section class="panel">
        <a href="<?= BASE_URL ?>/materials.php" class="panel__back">← Kembali ke Materi</a>

        <h1 class="material-detail__title"><?= e($material['title']) ?></h1>
        <span class="material-detail__subject"><?= e($material['subject_name'] ?? 'Umum') ?></span>

        <?php if ($isCompleted): ?>
            <span class="badge badge-completed">✓ Sudah dipelajari</span>
        <?php endif; ?>

        <div class="material-detail__body">
            <?php foreach (explode("\n\n", $material['content'] ?? '') as $paragraph): ?>
                <?php if (trim($paragraph) !== ''): ?>
                    <p><?= nl2br(e($paragraph)) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($material['external_url'])): ?>
            <a href="<?= e($material['external_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                Buka Sumber Belajar ↗
            </a>
        <?php endif; ?>

        <form method="POST" style="margin-top:16px;">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="mark_complete">
            <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
            <button type="submit" class="btn btn-primary" <?= $isCompleted ? 'disabled' : '' ?>>
                <?= $isCompleted ? '✓ Sudah Dipelajari' : '✓ Tandai Sudah Dipelajari' ?>
            </button>
        </form>
    </section>

    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* =============================================================
   LIST VIEW
============================================================= */
$q = trim($_GET['q'] ?? '');
$filterSubject = (int) ($_GET['subject_id'] ?? 0);
$filterCategory = trim($_GET['category'] ?? '');

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(m.title LIKE ? OR m.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($filterSubject > 0) {
    $where[] = 'm.subject_id = ?';
    $params[] = $filterSubject;
}
if ($filterCategory !== '') {
    $where[] = 'm.category = ?';
    $params[] = $filterCategory;
}

$sql = "SELECT m.*, s.name AS subject_name,
               (mc.id IS NOT NULL) AS is_completed
        FROM materials m
        LEFT JOIN subjects s ON s.id = m.subject_id
        LEFT JOIN material_completions mc ON mc.material_id = m.id AND mc.user_id = ?
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$userId], $params));
$materials = $stmt->fetchAll();

$stmt = $pdo->query(
    "SELECT DISTINCT s.id, s.name FROM subjects s JOIN materials m ON m.subject_id = s.id ORDER BY s.name"
);
$subjectOptions = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT category FROM materials WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categoryOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head"><h2>Materi Belajar <?= count($materials) > 0 ? '(' . count($materials) . ')' : '' ?></h2></div>

    <form method="GET" class="task-toolbar">
        <input type="search" name="q" placeholder="Cari materi..." value="<?= e($q) ?>" class="task-toolbar__search">

        <select name="subject_id" onchange="this.form.submit()">
            <option value="0">Semua Mapel</option>
            <?php foreach ($subjectOptions as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSubject === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="category" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            <?php foreach ($categoryOptions as $c): ?>
                <option value="<?= e($c) ?>" <?= $filterCategory === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-secondary">Cari</button>
    </form>

    <?php if (empty($materials)): ?>
        <div class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">📚</span>
            <p>Belum ada materi yang tersedia.</p>
        </div>
    <?php else: ?>
        <div class="materials-grid">
            <?php foreach ($materials as $m): ?>
                <div class="material-card">
                    <span class="material-card__subject"><?= e($m['subject_name'] ?? 'Umum') ?><?= $m['category'] ? ' · ' . e($m['category']) : '' ?></span>
                    <h3 class="material-card__title"><?= e($m['title']) ?></h3>
                    <p class="material-card__desc"><?= e(truncateText($m['description'] ?? '', 100)) ?></p>

                    <div class="material-card__footer">
                        <?php if ($m['is_completed']): ?>
                            <span class="badge badge-completed">✓ Sudah dipelajari</span>
                        <?php else: ?>
                            <span class="badge badge-pending">Belum dipelajari</span>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/materials.php?id=<?= $m['id'] ?>" class="btn btn-secondary">Buka Materi</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
