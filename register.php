<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirectTo('/dashboard.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi form tidak valid. Silakan coba lagi.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Nama minimal 2 karakter.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if (empty($errors)) {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email ini sudah terdaftar. Silakan masuk.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "student")');
                $stmt->execute([$name, $email, $hash]);
                $userId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO user_progress (user_id) VALUES (?)');
                $stmt->execute([$userId]);

                $pdo->commit();

                $_SESSION['user_id'] = $userId;
                session_regenerate_id(true);
                setFlash('success', 'Selamat datang di Fokusin, ' . $name . '! 🎉');
                redirectTo('/dashboard.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Register failed: ' . $e->getMessage());
                $errors[] = 'Maaf, data belum dapat disimpan. Silakan coba lagi.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Daftar';
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
        <h1 class="auth-heading">Buat akun baru</h1>
        <p class="auth-sub">Mulai atur jadwal belajarmu hari ini.</p>

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

            <label for="name">Nama lengkap</label>
            <input type="text" id="name" name="name" value="<?= e($name) ?>" required autofocus>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8">

            <label for="password_confirm">Konfirmasi password</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

            <button type="submit" class="btn btn-primary btn-block">Daftar</button>
        </form>

        <p class="auth-switch">Sudah punya akun? <a href="<?= BASE_URL ?>/login.php">Masuk di sini</a></p>
    </div>
</div>
</body>
</html>
