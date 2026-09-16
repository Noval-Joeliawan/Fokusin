<?php
/**
 * Konfigurasi umum aplikasi Fokusin.
 * File ini di-include di paling atas setiap halaman.
 */

// Jangan tampilkan error PHP mentah ke pengguna di production.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('APP_NAME', 'Fokusin');
define('APP_TAGLINE', 'Fokus belajar. Atur tugas. Raih tujuan.');
define('BASE_URL', '/fokusin'); // sesuaikan jika folder project berbeda

// Session cookie yang lebih aman untuk development di XAMPP (http://localhost)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
