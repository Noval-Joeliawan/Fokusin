<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
$errors = [];

$uploadsDir = __DIR__ . '/uploads/resources';
$action = $_GET['action'] ?? '';

/* -----------------------------------------------------------
   DOWNLOAD — divalidasi lewat DB, path file tidak pernah
   dipercaya langsung dari input. Path traversal dicegah dengan
   basename() + realpath() + memastikan hasilnya benar-benar di
   dalam folder uploads/resources.
----------------------------------------------------------- */
if ($action === 'download') {
    $resourceId = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ? AND resource_type = 'file'");
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch();

    if (!$resource || !$resource['file_path']) {
        setFlash('error', 'Resource tidak ditemukan.');
        redirectTo('/resources.php');
    }

    $safeBasename = basename($resource['file_path']);
    $fullPath = realpath($uploadsDir . '/' . $safeBasename);
    $uploadsRealPath = realpath($uploadsDir);

    if (!$fullPath || !$uploadsRealPath || strpos($fullPath, $uploadsRealPath) !== 0 || !is_file($fullPath)) {
        error_log("Resource download path check failed for resource id={$resourceId}");
        setFlash('error', 'File tidak ditemukan.');
        redirectTo('/resources.php');
    }

    $downloadName = sanitizeDisplayFilename($resource['file_name'] ?? $safeBasename);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');
    readfile($fullPath);
    exit;
}

/* -----------------------------------------------------------
   Actions (create / update / delete)
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/resources.php');
    }

    if ($postAction === 'save') {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $resourceType = ($_POST['resource_type'] ?? 'file') === 'link' ? 'link' : 'file';

        if ($title === '' || mb_strlen($title) > 150) {
            $errors[] = 'Judul resource wajib diisi (maks. 150 karakter).';
        }

        $isEdit = $resourceId > 0;
        $existing = null;
        if ($isEdit) {
            $check = $pdo->prepare('SELECT * FROM resources WHERE id = ? AND user_id = ?');
            $check->execute([$resourceId, $userId]);
            $existing = $check->fetch();
            if (!$existing) {
                setFlash('error', 'Kamu tidak memiliki akses ke data ini.');
                redirectTo('/resources.php');
            }
        }

        if ($resourceType === 'link') {
            $externalUrl = validateExternalUrl(trim($_POST['external_url'] ?? ''));
            if (!$externalUrl) {
                $errors[] = 'URL tidak valid. Gunakan tautan http:// atau https://.';
            }
        } elseif (!$isEdit) {
            // Tambah baru bertipe file -> file wajib ada dan valid.
            $fileCheck = validateUploadedResourceFile($_FILES['resource_file'] ?? []);
            if (!$fileCheck['ok']) {
                $errors[] = $fileCheck['error'];
            }
        }

        if (empty($errors)) {
            if ($isEdit) {
                // Hanya metadata yang bisa diedit (judul/deskripsi/kategori/URL).
                // File yang sudah diupload tidak diganti lewat form edit -- hapus & upload ulang jika perlu.
                if ($existing['resource_type'] === 'link') {
                    $stmt = $pdo->prepare(
                        'UPDATE resources SET title = ?, description = ?, category = ?, external_url = ? WHERE id = ? AND user_id = ?'
                    );
                    $stmt->execute([$title, $description, $category, $externalUrl, $resourceId, $userId]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE resources SET title = ?, description = ?, category = ? WHERE id = ? AND user_id = ?'
                    );
                    $stmt->execute([$title, $description, $category, $resourceId, $userId]);
                }
                setFlash('success', 'Resource berhasil diperbarui.');
            } else {
                if ($resourceType === 'link') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO resources (user_id, title, description, category, resource_type, external_url)
                         VALUES (?, ?, ?, ?, "link", ?)'
                    );
                    $stmt->execute([$userId, $title, $description, $category, $externalUrl]);
                } else {
                    $safeName = generateSafeStoredFilename($fileCheck['ext']);
                    $destination = $uploadsDir . '/' . $safeName;

                    if (!move_uploaded_file($_FILES['resource_file']['tmp_name'], $destination)) {
                        error_log('Failed to move uploaded resource file to ' . $destination);
                        setFlash('error', 'Terjadi kesalahan. Silakan coba lagi.');
                        redirectTo('/resources.php');
                    }
                    chmod($destination, 0644);

                    $originalName = sanitizeDisplayFilename($_FILES['resource_file']['name']);
                    $sizeKb = (int) ceil($_FILES['resource_file']['size'] / 1024);

                    $stmt = $pdo->prepare(
                        'INSERT INTO resources (user_id, title, description, category, resource_type, file_name, file_path, file_type, file_size_kb)
                         VALUES (?, ?, ?, ?, "file", ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $title, $description, $category, $originalName, $safeName, $fileCheck['ext'], $sizeKb]);
                }
                setFlash('success', 'Resource berhasil ditambahkan.');
            }
            redirectTo('/resources.php');
        }
    }

    if ($postAction === 'delete') {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ? AND user_id = ?');
        $stmt->execute([$resourceId, $userId]);
        $resource = $stmt->fetch();

        if ($resource) {
            $stmt = $pdo->prepare('DELETE FROM resources WHERE id = ? AND user_id = ?');
            $stmt->execute([$resourceId, $userId]);

            if ($resource['resource_type'] === 'file' && $resource['file_path']) {
                $physicalPath = realpath($uploadsDir . '/' . basename($resource['file_path']));
                $uploadsRealPath = realpath($uploadsDir);
                if ($physicalPath && $uploadsRealPath && strpos($physicalPath, $uploadsRealPath) === 0 && is_file($physicalPath)) {
                    @unlink($physicalPath);
                }
            }
            setFlash('success', 'Resource berhasil dihapus.');
        }
        redirectTo('/resources.php');
    }
}

/* -----------------------------------------------------------
   LIST — bisa dilihat semua pengguna (perpustakaan bersama),
   edit/hapus hanya untuk pemiliknya sendiri.
----------------------------------------------------------- */
$q = trim($_GET['q'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterType = in_array($_GET['type'] ?? '', ['file', 'link'], true) ? $_GET['type'] : '';

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(r.title LIKE ? OR r.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($filterCategory !== '') {
    $where[] = 'r.category = ?';
    $params[] = $filterCategory;
}
if ($filterType !== '') {
    $where[] = 'r.resource_type = ?';
    $params[] = $filterType;
}

$sql = "SELECT r.*, u.name AS owner_name FROM resources r
        JOIN users u ON u.id = r.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.uploaded_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

$categoryOptions = $pdo->query(
    "SELECT DISTINCT category FROM resources WHERE category IS NOT NULL AND category != '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

$csrfToken = generateCsrfToken();
$pageTitle = 'Resource';
$activeNav = 'resource';
$openFormOnLoad = isset($_GET['new']) || !empty($errors);
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <h2>Resource <?= count($resources) > 0 ? '(' . count($resources) . ')' : '' ?></h2>
        <button type="button" class="btn btn-primary" id="btnAddResource">+ Tambah Resource</button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="GET" class="task-toolbar">
        <input type="search" name="q" placeholder="Cari resource..." value="<?= e($q) ?>" class="task-toolbar__search">

        <select name="category" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            <?php foreach ($categoryOptions as $c): ?>
                <option value="<?= e($c) ?>" <?= $filterCategory === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="type" onchange="this.form.submit()">
            <option value="">Semua Tipe</option>
            <option value="file" <?= $filterType === 'file' ? 'selected' : '' ?>>File</option>
            <option value="link" <?= $filterType === 'link' ? 'selected' : '' ?>>Tautan</option>
        </select>

        <button type="submit" class="btn btn-secondary">Cari</button>
    </form>

    <?php if (empty($resources)): ?>
        <div class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">📂</span>
            <?php if ($q !== '' || $filterCategory !== '' || $filterType !== ''): ?>
                <p>Tidak ada resource yang cocok dengan pencarianmu.</p>
            <?php else: ?>
                <p>Belum ada resource yang tersedia.</p>
                <p class="empty-state__sub">Tambahkan tautan belajar atau upload file pertamamu.</p>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" id="btnAddResourceEmpty">+ Tambah Resource</button>
        </div>
    <?php else: ?>
        <div class="resources-grid">
            <?php foreach ($resources as $r):
                $resJson = e(json_encode([
                    'id' => $r['id'],
                    'title' => $r['title'],
                    'description' => $r['description'],
                    'category' => $r['category'],
                    'resource_type' => $r['resource_type'],
                    'external_url' => $r['external_url'],
                ]));
                $isOwner = (int) $r['user_id'] === $userId;
            ?>
                <div class="resource-card">
                    <div class="resource-card__head">
                        <span class="resource-card__icon" aria-hidden="true"><?= $r['resource_type'] === 'link' ? '🔗' : '📄' ?></span>
                        <?php if ($r['category']): ?>
                            <span class="resource-card__category"><?= e($r['category']) ?></span>
                        <?php endif; ?>
                    </div>

                    <h3 class="resource-card__title"><?= e($r['title']) ?></h3>
                    <?php if ($r['description']): ?>
                        <p class="resource-card__desc"><?= e($r['description']) ?></p>
                    <?php endif; ?>

                    <p class="resource-card__meta">
                        Ditambahkan oleh <?= e($r['owner_name']) ?>
                        <?php if ($r['resource_type'] === 'file' && $r['file_size_kb']): ?>
                            · <?= e(formatFileSizeKb((int) $r['file_size_kb'])) ?>
                        <?php endif; ?>
                    </p>

                    <div class="resource-card__footer">
                        <?php if ($r['resource_type'] === 'link'): ?>
                            <a href="<?= e($r['external_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Buka ↗</a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/resources.php?action=download&id=<?= $r['id'] ?>" class="btn btn-secondary">Unduh</a>
                        <?php endif; ?>

                        <?php if ($isOwner): ?>
                            <div class="resource-card__owner-actions">
                                <button type="button" class="icon-btn btn-edit-resource" data-resource="<?= $resJson ?>" aria-label="Edit resource">✏️</button>
                                <form method="POST" onsubmit="return confirm('Hapus resource ini?');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="resource_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="icon-btn" aria-label="Hapus resource">🗑️</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Modal tambah/edit resource -->
<div class="modal-overlay" id="resourceModalOverlay">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="resourceModalTitle">
        <div class="modal__head">
            <h2 id="resourceModalTitle">Tambah Resource</h2>
            <button type="button" class="icon-btn" id="btnCloseResourceModal" aria-label="Tutup">✕</button>
        </div>

        <form method="POST" class="modal-form" id="resourceForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="resource_id" id="r_resource_id" value="">

            <div class="resource-type-toggle" id="resourceTypeToggle">
                <label><input type="radio" name="resource_type" value="link" checked> Tautan Eksternal</label>
                <label><input type="radio" name="resource_type" value="file"> Upload File</label>
            </div>

            <label for="r_title">Judul</label>
            <input type="text" id="r_title" name="title" required maxlength="150" placeholder="Contoh: Dokumentasi MDN untuk DOM">

            <label for="r_category">Kategori</label>
            <input type="text" id="r_category" name="category" placeholder="Contoh: Artikel, Video, Dokumentasi" list="categorySuggestions">
            <datalist id="categorySuggestions">
                <?php foreach ($categoryOptions as $c): ?>
                    <option value="<?= e($c) ?>">
                <?php endforeach; ?>
            </datalist>

            <label for="r_description">Deskripsi (opsional)</label>
            <textarea id="r_description" name="description" rows="3" placeholder="Kenapa resource ini bermanfaat?"></textarea>

            <div id="r_link_field">
                <label for="r_external_url">URL</label>
                <input type="url" id="r_external_url" name="external_url" placeholder="https://...">
            </div>

            <div id="r_file_field" style="display:none;">
                <label for="r_file">File (PDF, DOC(X), PPT(X), XLS(X), TXT, JPG, PNG, WEBP — maks. 10MB)</label>
                <input type="file" id="r_file" name="resource_file"
                       accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp">
                <p class="form-hint" id="r_file_edit_note" style="display:none;">File yang sudah diupload tidak bisa diganti di sini — hapus resource ini dan upload ulang jika perlu file baru.</p>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Simpan</button>
        </form>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('resourceModalOverlay');
    var modalTitle = document.getElementById('resourceModalTitle');
    var linkField = document.getElementById('r_link_field');
    var fileField = document.getElementById('r_file_field');
    var typeToggle = document.getElementById('resourceTypeToggle');
    var fileEditNote = document.getElementById('r_file_edit_note');
    var fileInput = document.getElementById('r_file');

    function syncTypeFields() {
        var isLink = document.querySelector('input[name="resource_type"]:checked').value === 'link';
        linkField.style.display = isLink ? '' : 'none';
        fileField.style.display = isLink ? 'none' : '';
    }
    typeToggle.querySelectorAll('input[type="radio"]').forEach(function (r) {
        r.addEventListener('change', syncTypeFields);
    });

    function openModal(resource) {
        document.getElementById('resourceForm').reset();
        document.getElementById('r_resource_id').value = '';
        modalTitle.textContent = 'Tambah Resource';
        typeToggle.style.display = '';
        fileEditNote.style.display = 'none';
        fileInput.required = false;

        if (resource) {
            modalTitle.textContent = 'Edit Resource';
            document.getElementById('r_resource_id').value = resource.id;
            document.getElementById('r_title').value = resource.title;
            document.getElementById('r_category').value = resource.category || '';
            document.getElementById('r_description').value = resource.description || '';

            var radios = typeToggle.querySelectorAll('input[type="radio"]');
            radios.forEach(function (r) { r.checked = (r.value === resource.resource_type); r.disabled = true; });

            if (resource.resource_type === 'link') {
                document.getElementById('r_external_url').value = resource.external_url || '';
            } else {
                fileEditNote.style.display = 'block';
            }
        } else {
            typeToggle.querySelectorAll('input[type="radio"]').forEach(function (r) { r.disabled = false; });
        }
        syncTypeFields();
        overlay.classList.add('is-visible');
    }
    function closeModal() { overlay.classList.remove('is-visible'); }

    document.getElementById('btnAddResource').addEventListener('click', function () { openModal(null); });
    var btnEmpty = document.getElementById('btnAddResourceEmpty');
    if (btnEmpty) btnEmpty.addEventListener('click', function () { openModal(null); });

    document.getElementById('btnCloseResourceModal').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

    document.querySelectorAll('.btn-edit-resource').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(JSON.parse(this.getAttribute('data-resource'))); });
    });

    syncTypeFields();

    <?php if ($openFormOnLoad): ?>
    openModal(null);
    <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
