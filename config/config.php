<?php
/**
 * Konfigurasi umum aplikasi Fokusin.
 * File ini di-include di paling atas setiap halaman.
 */

// Jangan tampilkan error PHP mentah ke pengguna di production.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Header keamanan dasar untuk setiap response (Fase 7 hardening).
// Tidak menambahkan CSP di sini karena banyak halaman memakai <script>
// inline -- CSP yang ketat akan mematahkan itu tanpa audit nonce/hash
// penuh, yang di luar cakupan perbaikan minimal Fase 7 ini.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

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
