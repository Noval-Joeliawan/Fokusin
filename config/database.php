<?php
/**
 * Koneksi database (PDO + MySQL/XAMPP).
 * Ubah kredensial di bawah ini sesuai environment lokal Anda.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'fokusin_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // default XAMPP: kosong

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan bocorkan kredensial/detail server ke pengguna.
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Maaf, terjadi gangguan pada server. Silakan coba lagi nanti.');
        }
    }

    return $pdo;
}
