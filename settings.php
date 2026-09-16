<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2) {
            $errors[] = 'Nama minimal 2 karakter.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
            $stmt->execute([$name, $user['id']]);
            setFlash('success', 'Profil berhasil diperbarui.');
            redirectTo('/settings.php');
        }
    }

    if ($action === 'update_theme') {
        $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
        $stmt = $pdo->prepare('UPDATE users SET theme = ? WHERE id = ?');
        $stmt->execute([$theme, $user['id']]);
        setFlash('success', 'Tema berhasil diperbarui.');
        redirectTo('/settings.php');
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Pengaturan';
$activeNav = 'pengaturan';
include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head"><h2>Informasi Akun</h2></div>
    <form method="POST" class="settings-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="update_profile">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <label for="name">Nama</label>
        <input type="text" id="name" name="name" value="<?= e($user['name']) ?>">

        <label for="email">Email</label>
        <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>

        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </form>
</section>

<section class="panel">
    <div class="panel__head"><h2>Tampilan</h2></div>
    <form method="POST" class="settings-form settings-form--inline">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="update_theme">

        <label for="theme">Tema</label>
        <select id="theme" name="theme" onchange="this.form.submit()">
            <option value="light" <?= $user['theme'] === 'light' ? 'selected' : '' ?>>Light Mode</option>
            <option value="dark" <?= $user['theme'] === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
        </select>
    </form>
</section>

<section class="panel">
    <div class="panel__head"><h2>Password &amp; Notifikasi</h2></div>
    <div class="empty-state">
        <p>Ubah password dan preferensi notifikasi akan tersedia di fase berikutnya.</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
