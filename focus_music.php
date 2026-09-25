<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];
$errors = [];

/* -----------------------------------------------------------
   Actions. Hanya entri musik milik user sendiri (added_by =
   userId) yang bisa diedit/dihapus -- entri sistem (added_by
   NULL) atau milik user lain tidak bisa disentuh lewat sini.
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/focus_music.php');
    }

    if ($action === 'save') {
        $musicId = (int) ($_POST['music_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $sourceType = ($_POST['source_type'] ?? 'external_link') === 'licensed_audio' ? 'licensed_audio' : 'external_link';
        $urlOrPath = trim($_POST['url_or_path'] ?? '');

        if ($title === '' || mb_strlen($title) > 150) {
            $errors[] = 'Judul musik wajib diisi (maks. 150 karakter).';
        }

        $validatedValue = null;
        if ($sourceType === 'external_link') {
            $validatedValue = validateExternalUrl($urlOrPath);
            if (!$validatedValue) {
                $errors[] = 'URL musik tidak valid. Gunakan tautan http:// atau https://.';
            }
        } else {
            // Path lokal ke file audio yang sudah ada di server (tidak ada upload audio
            // lewat form ini -- lihat catatan hak cipta di bagian bawah halaman).
            $cleanPath = str_replace('\\', '/', $urlOrPath);
            if ($cleanPath === '' || strpos($cleanPath, '..') !== false || !preg_match('/\.(mp3|wav|ogg|m4a)$/i', $cleanPath)) {
                $errors[] = 'Path file audio tidak valid. Gunakan path relatif ke file .mp3/.wav/.ogg/.m4a tanpa "..".';
            } else {
                $validatedValue = $cleanPath;
            }
        }

        if (empty($errors)) {
            if ($musicId > 0) {
                $check = $pdo->prepare('SELECT id FROM focus_music WHERE id = ? AND added_by = ?');
                $check->execute([$musicId, $userId]);
                if (!$check->fetch()) {
                    setFlash('error', 'Kamu tidak memiliki akses ke data ini.');
                    redirectTo('/focus_music.php');
                }
                $stmt = $pdo->prepare(
                    'UPDATE focus_music SET title = ?, category = ?, source_type = ?, url_or_path = ? WHERE id = ? AND added_by = ?'
                );
                $stmt->execute([$title, $category, $sourceType, $validatedValue, $musicId, $userId]);
                setFlash('success', 'Musik berhasil diperbarui.');
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO focus_music (title, source_type, url_or_path, category, added_by) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$title, $sourceType, $validatedValue, $category, $userId]);
                    setFlash('success', 'Musik berhasil ditambahkan.');
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        setFlash('error', 'Musik dengan judul yang sama sudah ada.');
                    } else {
                        error_log('Add focus music failed: ' . $e->getMessage());
                        setFlash('error', 'Terjadi kesalahan. Silakan coba lagi.');
                    }
                    redirectTo('/focus_music.php');
                }
            }
            redirectTo('/focus_music.php');
        }
    }

    if ($action === 'delete') {
        $musicId = (int) ($_POST['music_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM focus_music WHERE id = ? AND added_by = ?');
        $stmt->execute([$musicId, $userId]);
        setFlash('success', 'Musik berhasil dihapus.');
        redirectTo('/focus_music.php');
    }
}

$stmt = $pdo->query('SELECT * FROM focus_music ORDER BY category, title');
$musicList = $stmt->fetchAll();

$csrfToken = generateCsrfToken();
$pageTitle = 'Kelola Musik Fokus';
$activeNav = 'fokus';
$openFormOnLoad = isset($_GET['new']) || !empty($errors);
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <div>
            <h2>Kelola Musik Fokus</h2>
            <p class="panel__sub">Musik yang kamu tambahkan di sini akan muncul untuk semua pengguna di Focus Mode.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btnAddMusic">+ Tambah Musik</button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (empty($musicList)): ?>
        <div class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">🎵</span>
            <p>Belum ada musik fokus.</p>
        </div>
    <?php else: ?>
        <ul class="music-list">
            <?php foreach ($musicList as $m):
                $isOwner = $m['added_by'] !== null && (int) $m['added_by'] === $userId;
                $musicJson = e(json_encode([
                    'id' => $m['id'],
                    'title' => $m['title'],
                    'category' => $m['category'],
                    'source_type' => $m['source_type'],
                    'url_or_path' => $m['url_or_path'],
                ]));
            ?>
                <li class="music-row">
                    <div class="music-row__main">
                        <span class="music-row__title">🎵 <?= e($m['title']) ?></span>
                        <span class="music-row__meta">
                            <?= e($m['category'] ?? 'Umum') ?> ·
                            <?= $m['source_type'] === 'external_link' ? 'Tautan eksternal' : 'Audio lokal' ?>
                            <?= $m['added_by'] === null ? '· Bawaan sistem' : '' ?>
                        </span>
                    </div>
                    <?php if ($isOwner): ?>
                        <div class="music-row__actions">
                            <button type="button" class="icon-btn btn-edit-music" data-music="<?= $musicJson ?>" aria-label="Edit musik">✏️</button>
                            <form method="POST" onsubmit="return confirm('Hapus musik ini?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="music_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="icon-btn" aria-label="Hapus musik">🗑️</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="form-hint" style="margin-top:16px;">
        🔒 Untuk audio lokal, isi path ke file yang sudah ada di server (misalnya <code>assets/audio/rain.mp3</code>) —
        formulir ini tidak mengunggah/mengunduh audio dari YouTube atau layanan musik apa pun.
    </p>
</section>

<!-- Modal tambah/edit musik -->
<div class="modal-overlay" id="musicModalOverlay">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="musicModalTitle">
        <div class="modal__head">
            <h2 id="musicModalTitle">Tambah Musik</h2>
            <button type="button" class="icon-btn" id="btnCloseMusicModal" aria-label="Tutup">✕</button>
        </div>

        <form method="POST" class="modal-form" id="musicForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="music_id" id="m_music_id" value="">

            <label for="m_title">Judul</label>
            <input type="text" id="m_title" name="title" required maxlength="150" placeholder="Contoh: Rain Ambience">

            <label for="m_category">Kategori</label>
            <input type="text" id="m_category" name="category" placeholder="Contoh: Rain, Cafe, Instrumental">

            <label>Tipe Sumber</label>
            <div class="resource-type-toggle" id="musicTypeToggle">
                <label><input type="radio" name="source_type" value="external_link" checked> Tautan Eksternal</label>
                <label><input type="radio" name="source_type" value="licensed_audio"> Audio Lokal (path)</label>
            </div>

            <label for="m_url_or_path" id="m_url_label">URL Musik</label>
            <input type="text" id="m_url_or_path" name="url_or_path" required placeholder="https://...">

            <button type="submit" class="btn btn-primary btn-block">Simpan</button>
        </form>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('musicModalOverlay');
    var modalTitle = document.getElementById('musicModalTitle');
    var typeToggle = document.getElementById('musicTypeToggle');
    var urlLabel = document.getElementById('m_url_label');
    var urlInput = document.getElementById('m_url_or_path');

    function syncLabel() {
        var isLink = document.querySelector('input[name="source_type"]:checked').value === 'external_link';
        urlLabel.textContent = isLink ? 'URL Musik' : 'Path File Audio';
        urlInput.placeholder = isLink ? 'https://...' : 'assets/audio/rain.mp3';
    }
    typeToggle.querySelectorAll('input[type="radio"]').forEach(function (r) {
        r.addEventListener('change', syncLabel);
    });

    function openModal(music) {
        document.getElementById('musicForm').reset();
        document.getElementById('m_music_id').value = '';
        modalTitle.textContent = 'Tambah Musik';

        if (music) {
            modalTitle.textContent = 'Edit Musik';
            document.getElementById('m_music_id').value = music.id;
            document.getElementById('m_title').value = music.title;
            document.getElementById('m_category').value = music.category || '';
            typeToggle.querySelectorAll('input[type="radio"]').forEach(function (r) {
                r.checked = (r.value === music.source_type);
            });
            urlInput.value = music.url_or_path || '';
        }
        syncLabel();
        overlay.classList.add('is-visible');
    }
    function closeModal() { overlay.classList.remove('is-visible'); }

    document.getElementById('btnAddMusic').addEventListener('click', function () { openModal(null); });
    document.getElementById('btnCloseMusicModal').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

    document.querySelectorAll('.btn-edit-music').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(JSON.parse(this.getAttribute('data-music'))); });
    });

    <?php if ($openFormOnLoad): ?>
    openModal(null);
    <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
