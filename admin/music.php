<?php
require_once __DIR__ . '/_bootstrap.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/admin/music.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int) ($_POST['music_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM focus_music WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Musik berhasil dihapus.');
        redirectTo('/admin/music.php');
    }
    if ($action === 'save') {
        $id = (int) ($_POST['music_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $sourceType = $_POST['source_type'] ?? 'external_link';
        $value = trim($_POST['url_or_path'] ?? '');
        if ($title === '' || mb_strlen($title) > 150) $errors[] = 'Judul wajib diisi (maks. 150 karakter).';
        if (mb_strlen($category) > 50) $errors[] = 'Kategori maksimal 50 karakter.';
        if (!in_array($sourceType, ['external_link', 'licensed_audio'], true)) $errors[] = 'Tipe sumber tidak valid.';
        if ($sourceType === 'external_link') {
            $value = validateExternalUrl($value) ?? '';
            if ($value === '') $errors[] = 'URL harus berupa tautan http:// atau https://.';
        } else {
            $value = str_replace('\\', '/', $value);
            if ($value === '' || strpos($value, '..') !== false || !preg_match('/\.(mp3|wav|ogg|m4a)$/i', $value)) $errors[] = 'Path audio lokal tidak valid.';
        }
        if (!$errors) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE focus_music SET title = ?, source_type = ?, url_or_path = ?, category = ? WHERE id = ?');
                    $stmt->execute([$title, $sourceType, $value, $category, $id]);
                    setFlash('success', 'Focus Music berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO focus_music (title, source_type, url_or_path, category, added_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $sourceType, $value, $category, (int) $adminUser['id']]);
                    setFlash('success', 'Focus Music berhasil ditambahkan.');
                }
                redirectTo('/admin/music.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') setFlash('error', 'Judul musik sudah digunakan.');
                else { error_log('Admin music save failed: ' . $e->getMessage()); setFlash('error', 'Musik gagal disimpan.'); }
                redirectTo('/admin/music.php');
            }
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM focus_music WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$q = trim($_GET['q'] ?? '');
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(title LIKE ? OR category LIKE ?)'; $like = '%' . $q . '%'; $params = [$like, $like]; }
$stmt = $pdo->prepare('SELECT fm.*, u.name AS added_by_name FROM focus_music fm LEFT JOIN users u ON u.id = fm.added_by WHERE ' . implode(' AND ', $where) . ' ORDER BY fm.category, fm.title');
$stmt->execute($params);
$music = $stmt->fetchAll();

adminPageStart('Focus Music', 'music');
if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="panel admin-panel">
    <div class="panel__head"><div><h2>Kelola Focus Music</h2><p class="panel__sub">Kelola tautan eksternal dan audio lokal yang berizin.</p></div><a class="btn btn-primary" href="<?= e(adminUrl('music.php', ['new' => 1])) ?>">+ Tambah Musik</a></div>
    <form method="GET" class="admin-filters"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari musik atau kategori..."><button type="submit" class="btn btn-secondary">Cari</button></form>
    <?php if (!$music): ?><div class="empty-state"><span class="empty-state__icon">🎧</span><p>Belum ada focus music.</p></div><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Judul</th><th>Kategori</th><th>Sumber</th><th>Ditambahkan oleh</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($music as $m): ?><tr><td><strong><?= e($m['title']) ?></strong></td><td><?= e($m['category'] ?: '-') ?></td><td><?= $m['source_type'] === 'external_link' ? '🔗 External' : '🎵 Licensed Audio' ?></td><td><?= e($m['added_by_name'] ?: 'Sistem') ?></td><td><div class="admin-actions"><a class="btn btn-secondary" href="<?= e(adminUrl('music.php', ['edit' => $m['id']])) ?>">Edit</a><form method="POST" onsubmit="return confirm('Hapus musik ini?');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="music_id" value="<?= (int) $m['id'] ?>"><button class="btn btn-danger" type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>

<?php if ($editing || isset($_GET['new'])): ?>
<section class="panel admin-panel"><div class="panel__head"><h2><?= $editing ? 'Edit Focus Music' : 'Tambah Focus Music' ?></h2><a class="panel__link" href="<?= e(adminUrl('music.php')) ?>">Tutup</a></div>
<form method="POST" class="admin-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="music_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
<label>Judul<input type="text" name="title" required maxlength="150" value="<?= e($editing['title'] ?? '') ?>"></label>
<label>Kategori<input type="text" name="category" maxlength="50" value="<?= e($editing['category'] ?? '') ?>"></label>
<label>Tipe Sumber<select name="source_type" id="adminMusicType"><option value="external_link" <?= ($editing['source_type'] ?? 'external_link') === 'external_link' ? 'selected' : '' ?>>Tautan Eksternal</option><option value="licensed_audio" <?= ($editing['source_type'] ?? '') === 'licensed_audio' ? 'selected' : '' ?>>Audio Lokal Berizin</option></select></label>
<label>URL / Path<input type="text" name="url_or_path" required value="<?= e($editing['url_or_path'] ?? '') ?>" placeholder="https://... / assets/audio/rain.mp3"></label>
<button class="btn btn-primary" type="submit">Simpan Musik</button></form></section>
<?php endif; ?>
<?php adminPageEnd(); ?>
