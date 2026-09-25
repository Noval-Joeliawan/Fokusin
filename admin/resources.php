<?php
require_once __DIR__ . '/_bootstrap.php';

$uploadsDir = __DIR__ . '/../uploads/resources';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}
$errors = [];

function adminResourcePhysicalPath(string $filePath): ?string
{
    global $uploadsDir;
    $base = realpath($uploadsDir);
    if (!$base) return null;
    $full = realpath($uploadsDir . '/' . basename($filePath));
    if (!$full || !is_file($full)) return null;
    return strpos($full, $base) === 0 ? $full : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/admin/resources.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
        $stmt->execute([$resourceId]);
        $resource = $stmt->fetch();
        if (!$resource) {
            setFlash('error', 'Resource tidak ditemukan.');
            redirectTo('/admin/resources.php');
        }
        $stmt = $pdo->prepare('DELETE FROM resources WHERE id = ?');
        $stmt->execute([$resourceId]);
        if ($resource['resource_type'] === 'file' && !empty($resource['file_path'])) {
            $physical = adminResourcePhysicalPath($resource['file_path']);
            if ($physical) @unlink($physical);
        }
        setFlash('success', 'Resource berhasil dihapus.');
        redirectTo('/admin/resources.php');
    }

    if ($action === 'save') {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $resourceType = ($_POST['resource_type'] ?? 'link') === 'file' ? 'file' : 'link';
        $externalUrl = trim($_POST['external_url'] ?? '');

        if ($title === '' || mb_strlen($title) > 150) $errors[] = 'Judul resource wajib diisi (maks. 150 karakter).';
        if (mb_strlen($category) > 60) $errors[] = 'Kategori maksimal 60 karakter.';
        if (mb_strlen($description) > 5000) $errors[] = 'Deskripsi terlalu panjang.';

        $existing = null;
        if ($resourceId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
            $stmt->execute([$resourceId]);
            $existing = $stmt->fetch();
            if (!$existing) $errors[] = 'Resource yang diedit tidak ditemukan.';
        }

        $newFile = null;
        if ($resourceType === 'link') {
            $validatedUrl = validateExternalUrl($externalUrl);
            if (!$validatedUrl) $errors[] = 'URL eksternal tidak valid. Gunakan http:// atau https://.';
            $externalUrl = $validatedUrl ?: '';
        } else {
            // File wajib diisi untuk resource baru atau saat mengubah tipe dari
            // link -> file. Saat mengedit resource yang SUDAH bertipe file, file
            // baru bersifat opsional (ganti file lama jika diisi) -- tapi jika
            // admin memang melampirkan file baru, itu HARUS tetap diproses,
            // bukan diabaikan begitu saja.
            $mustHaveFile = !$existing || $existing['resource_type'] !== 'file';
            $fileAttached = isset($_FILES['resource_file']) && (int) $_FILES['resource_file']['error'] !== UPLOAD_ERR_NO_FILE;

            if (!$mustHaveFile && !$fileAttached) {
                // Edit metadata-only pada resource file yang sudah ada: file lama dipertahankan.
            } elseif (!$fileAttached || (int) $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload wajib dipilih.';
            } else {
                $file = $_FILES['resource_file'];
                $maxBytes = 10 * 1024 * 1024;
                if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) $errors[] = 'Ukuran file harus lebih dari 0 dan maksimal 10 MB.';
                    if (!is_uploaded_file($file['tmp_name'])) $errors[] = 'Upload file tidak valid.';
                    if (empty($errors)) {
                        $allowed = [
                            'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'txt' => 'text/plain', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'
                        ];
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) $errors[] = 'Tipe file tidak diizinkan.';
                        if (empty($errors)) {
                            $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
                            if (!move_uploaded_file($file['tmp_name'], $uploadsDir . '/' . $safeName)) {
                                $errors[] = 'File gagal disimpan di server.';
                            } else {
                                $newFile = [
                                    'path' => $safeName,
                                    'name' => sanitizeDisplayFilename($file['name']),
                                    'type' => $mime,
                                    'size_kb' => (int) ceil($file['size'] / 1024),
                                ];
                            }
                        }
                    }
                }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                if ($resourceId > 0) {
                    if ($resourceType === 'link') {
                        $stmt = $pdo->prepare('UPDATE resources SET title = ?, description = ?, category = ?, resource_type = ?, external_url = ?, file_name = NULL, file_path = NULL, file_type = NULL, file_size_kb = NULL WHERE id = ?');
                        $stmt->execute([$title, $description, $category, $resourceType, $externalUrl, $resourceId]);
                    } elseif ($newFile) {
                        $stmt = $pdo->prepare('UPDATE resources SET title = ?, description = ?, category = ?, resource_type = ?, external_url = NULL, file_name = ?, file_path = ?, file_type = ?, file_size_kb = ? WHERE id = ?');
                        $stmt->execute([$title, $description, $category, $resourceType, $newFile['name'], $newFile['path'], $newFile['type'], $newFile['size_kb'], $resourceId]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE resources SET title = ?, description = ?, category = ? WHERE id = ?');
                        $stmt->execute([$title, $description, $category, $resourceId]);
                    }
                } else {
                    if ($resourceType === 'link') {
                        $stmt = $pdo->prepare('INSERT INTO resources (user_id, title, description, category, resource_type, external_url) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->execute([(int) $adminUser['id'], $title, $description, $category, $resourceType, $externalUrl]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO resources (user_id, title, description, category, resource_type, file_name, file_path, file_type, file_size_kb) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([(int) $adminUser['id'], $title, $description, $category, $resourceType, $newFile['name'], $newFile['path'], $newFile['type'], $newFile['size_kb']]);
                    }
                }
                $pdo->commit();

                if ($existing && $existing['resource_type'] === 'file' && !empty($existing['file_path'])) {
                    $shouldDeleteOld = $resourceType === 'link' || ($newFile && $newFile['path'] !== $existing['file_path']);
                    if ($shouldDeleteOld) {
                        $oldPhysical = adminResourcePhysicalPath($existing['file_path']);
                        if ($oldPhysical) @unlink($oldPhysical);
                    }
                }
                setFlash('success', $resourceId > 0 ? 'Resource berhasil diperbarui.' : 'Resource berhasil ditambahkan.');
                redirectTo('/admin/resources.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($newFile) {
                    $newPhysical = adminResourcePhysicalPath($newFile['path']);
                    if ($newPhysical) @unlink($newPhysical);
                }
                error_log('Admin resource save failed: ' . $e->getMessage());
                setFlash('error', 'Resource gagal disimpan. Silakan coba lagi.');
                redirectTo('/admin/resources.php');
            }
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$q = trim($_GET['q'] ?? '');
$type = in_array($_GET['type'] ?? '', ['file', 'link'], true) ? $_GET['type'] : '';
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(r.title LIKE ? OR r.description LIKE ? OR r.category LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like); }
if ($type !== '') { $where[] = 'r.resource_type = ?'; $params[] = $type; }
$stmt = $pdo->prepare('SELECT r.*, u.name AS owner_name FROM resources r JOIN users u ON u.id = r.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.uploaded_at DESC');
$stmt->execute($params);
$resources = $stmt->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM resources WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

adminPageStart('Resource', 'resources');
if (!empty($errors)):
?>
<div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="panel admin-panel">
    <div class="panel__head"><div><h2>Kelola Resource</h2><p class="panel__sub">Admin dapat menambah, mengedit, dan menghapus resource global.</p></div><a class="btn btn-primary" href="<?= e(adminUrl('resources.php', ['new' => 1])) ?>">+ Tambah Resource</a></div>
    <form method="GET" class="admin-filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari resource...">
        <select name="type"><option value="">Semua tipe</option><option value="link" <?= $type === 'link' ? 'selected' : '' ?>>Tautan</option><option value="file" <?= $type === 'file' ? 'selected' : '' ?>>File</option></select>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>
    <?php if (!$resources): ?>
        <div class="empty-state"><span class="empty-state__icon">📂</span><p>Belum ada resource.</p></div>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Judul</th><th>Tipe</th><th>Kategori</th><th>Pemilik</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach ($resources as $r): ?>
            <tr>
                <td><strong><?= e($r['title']) ?></strong><div class="admin-muted"><?= e(mb_strimwidth((string) $r['description'], 0, 80, '…')) ?></div></td>
                <td><?= $r['resource_type'] === 'file' ? '📄 File' : '🔗 Link' ?></td>
                <td><?= e($r['category'] ?: '-') ?></td>
                <td><?= e($r['owner_name']) ?></td>
                <td><?= e(date('d M Y', strtotime($r['uploaded_at']))) ?></td>
                <td><div class="admin-actions"><a class="btn btn-secondary" href="<?= e(adminUrl('resources.php', ['edit' => $r['id']])) ?>">Edit</a><form method="POST" onsubmit="return confirm('Hapus resource ini?');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="resource_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-danger" type="submit">Hapus</button></form></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<?php if ($editing || isset($_GET['new'])): ?>
<section class="panel admin-panel">
    <div class="panel__head"><div><h2><?= $editing ? 'Edit Resource' : 'Tambah Resource' ?></h2></div><a class="panel__link" href="<?= e(adminUrl('resources.php')) ?>">Tutup</a></div>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="resource_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <label>Judul<input type="text" name="title" required maxlength="150" value="<?= e($editing['title'] ?? '') ?>"></label>
        <label>Kategori<input type="text" name="category" maxlength="60" value="<?= e($editing['category'] ?? '') ?>" list="resourceCategories"><datalist id="resourceCategories"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></label>
        <label>Deskripsi<textarea name="description" rows="4" maxlength="5000"><?= e($editing['description'] ?? '') ?></textarea></label>
        <label>Tipe<select name="resource_type" id="adminResourceType"><option value="link" <?= ($editing['resource_type'] ?? 'link') === 'link' ? 'selected' : '' ?>>Tautan Eksternal</option><option value="file" <?= ($editing['resource_type'] ?? '') === 'file' ? 'selected' : '' ?>>Upload File</option></select></label>
        <label id="adminExternalField">URL<input type="url" name="external_url" value="<?= e($editing['external_url'] ?? '') ?>" placeholder="https://..."></label>
        <label id="adminFileField">File<input type="file" name="resource_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp"><small class="admin-muted">Maks. 10 MB. Saat edit tipe file, pilih file baru untuk mengganti file lama.</small></label>
        <button class="btn btn-primary" type="submit">Simpan Resource</button>
    </form>
</section>
<script>
(function(){const type=document.getElementById('adminResourceType');const ext=document.getElementById('adminExternalField');const file=document.getElementById('adminFileField');function sync(){const isFile=type.value==='file';file.style.display=isFile?'flex':'none';ext.style.display=isFile?'none':'flex';}type.addEventListener('change',sync);sync();})();
</script>
<?php endif; ?>
<?php adminPageEnd(); ?>
