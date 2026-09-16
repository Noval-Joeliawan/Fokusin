<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirectTo('/dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi form tidak valid. Silakan coba lagi.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email dan password wajib diisi.';
    }

    if (empty($errors)) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, name, password_hash, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Pesan generik agar tidak membocorkan email mana yang terdaftar.
            $errors[] = 'Email atau password salah.';
        } elseif ((int) $user['is_active'] === 0) {
            $errors[] = 'Akun ini sedang dinonaktifkan. Hubungi admin.';
        } else {
            $_SESSION['user_id'] = (int) $user['id'];
            session_regenerate_id(true);
            ensureUserProgressRow((int) $user['id']);
            setFlash('success', 'Selamat datang kembali, ' . $user['name'] . '!');
            redirectTo('/dashboard.php');
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Masuk';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Fokusin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-brand">
        <span class="auth-logo">Fokusin</span>
        <p class="auth-tagline">Fokus belajar. Atur tugas. Raih tujuan.</p>
    </div>

    <div class="auth-card">
        <h1 class="auth-heading">Masuk ke akunmu</h1>
        <p class="auth-sub">Lanjutkan progres belajarmu.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>

        <p class="auth-switch">Belum punya akun? <a href="<?= BASE_URL ?>/register.php">Daftar di sini</a></p>
    </div>
</div>
</body>
</html>
