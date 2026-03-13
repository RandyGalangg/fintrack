<?php
/**
 * FinTrack - Sistem Manajemen Keuangan Pribadi
 *
 * @package    FinTrack\Config
 * @author     FinTrack Team
 * @version    1.0.0
 * @license    MIT
 *
 * File konfigurasi koneksi database dan pengaturan global aplikasi.
 */

declare(strict_types=1);

namespace FinTrack\Config;

// ============================================================
// Konstanta Konfigurasi Database
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'fintrack_db');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// Konstanta Konfigurasi Aplikasi
// ============================================================
define('APP_NAME',    'FinTrack');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/fintrack');
define('APP_PATH',    dirname(__DIR__));

// ============================================================
// Konstanta Keamanan & Session
// ============================================================
define('SESSION_NAME',     'fintrack_session');
define('SESSION_LIFETIME', 7200);        // 2 jam
define('SALT_ROUNDS',      12);
define('JWT_SECRET',       'fintrack_secret_key_2024_change_in_production');

// ============================================================
// Konfigurasi Upload File
// ============================================================
define('UPLOAD_DIR',      APP_PATH . '/uploads/');
define('EXPORT_DIR',      APP_PATH . '/exports/');
define('MAX_FILE_SIZE',   5 * 1024 * 1024);  // 5MB
define('ALLOWED_TYPES',   ['image/jpeg', 'image/png', 'application/pdf']);

// ============================================================
// Konfigurasi Mata Uang
// ============================================================
define('CURRENCY_SYMBOL', 'Rp');
define('CURRENCY_CODE',   'IDR');
define('CURRENCY_LOCALE', 'id_ID');

/**
 * Class Database
 *
 * Singleton class untuk mengelola koneksi PDO ke MySQL.
 * Menerapkan pola Singleton untuk memastikan hanya satu
 * koneksi database yang aktif setiap saat.
 *
 * @package FinTrack\Config
 */
class Database
{
    /** @var Database|null Instance tunggal class ini */
    private static ?Database $instance = null;

    /** @var \PDO Objek koneksi PDO */
    private \PDO $pdo;

    /**
     * Constructor private untuk mencegah instantiasi langsung.
     * Membuat koneksi PDO ke MySQL dengan konfigurasi yang telah ditentukan.
     *
     * @throws \PDOException Jika koneksi database gagal
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        $this->pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
    }

    /**
     * Mendapatkan instance tunggal Database (Singleton).
     *
     * @return Database Instance Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Mendapatkan objek koneksi PDO.
     *
     * @return \PDO Objek PDO
     */
    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Menjalankan query dengan prepared statement.
     *
     * @param string $sql    Query SQL
     * @param array  $params Parameter untuk binding
     * @return \PDOStatement Statement yang telah dieksekusi
     * @throws \PDOException
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Mencegah cloning instance (Singleton pattern).
     */
    private function __clone() {}

    /**
     * Mencegah unserialization instance (Singleton pattern).
     */
    public function __wakeup() {}
}
