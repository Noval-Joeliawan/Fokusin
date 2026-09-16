<?php
/**
 * Fungsi-fungsi terkait autentikasi & otorisasi.
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT id, name, email, role, avatar_path, theme FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    if ($user === null) {
        // Akun sudah tidak aktif / dihapus tapi sesi masih ada.
        session_destroy();
    }

    return $user;
}

/**
 * Wajibkan pengguna login. Panggil di awal halaman yang dilindungi.
 */
function requireLogin(): void
{
    if (!isLoggedIn() || currentUser() === null) {
        setFlash('error', 'Silakan masuk untuk melanjutkan.');
        redirectTo('/login.php');
    }
}

/**
 * Wajibkan pengguna login sekaligus berperan sebagai admin.
 */
function requireAdmin(): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        die('Akses ditolak. Halaman ini hanya untuk admin.');
    }
}

/**
 * Pastikan user_progress punya baris untuk user ini (dibuat saat registrasi,
 * fungsi ini sebagai pengaman jika baris belum ada).
 */
function ensureUserProgressRow(int $userId): void
{
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_progress (user_id) VALUES (?)');
    $stmt->execute([$userId]);
}
