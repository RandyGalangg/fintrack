<?php
/**
 * FinTrack - Sistem Manajemen Keuangan Pribadi
 *
 * @package    FinTrack
 * @author     FinTrack Team
 * @version    1.0.0
 *
 * Bootstrap file — memuat konfigurasi dan autoloader kelas.
 */

declare(strict_types=1);

// ============================================================
// Error Reporting (matikan di production)
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');

// ============================================================
// Load Konfigurasi
// ============================================================
require_once __DIR__ . '/config/Database.php';

// ============================================================
// PSR-4 Autoloader
// ============================================================
spl_autoload_register(function (string $class): void {
    $classFileMap = [
        'FinTrack\\Finance\\Budget'       => __DIR__ . '/finance/Category.php',
        'FinTrack\\Finance\\SavingsGoal'  => __DIR__ . '/finance/Category.php',
        'FinTrack\\Finance\\Category'     => __DIR__ . '/finance/Category.php',
        'FinTrack\\Finance\\Income'       => __DIR__ . '/finance/Transaction.php',
        'FinTrack\\Finance\\Expense'      => __DIR__ . '/finance/Transaction.php',
        'FinTrack\\Finance\\Transaction'  => __DIR__ . '/finance/Transaction.php',
        'FinTrack\\Finance\\BaseModel'    => __DIR__ . '/finance/BaseModel.php',
        'FinTrack\\Auth\\AuthService'     => __DIR__ . '/auth/AuthService.php',
        'FinTrack\\Auth\\User'            => __DIR__ . '/auth/AuthService.php',
        'FinTrack\\Config\\Database'      => __DIR__ . '/config/Database.php',
    ];

    if (isset($classFileMap[$class])) {
        require_once $classFileMap[$class];
        return;
    }

    $namespaceMap = [
        'FinTrack\\Config\\' => __DIR__ . '/config/',
        'FinTrack\\Auth\\'   => __DIR__ . '/auth/',
        'FinTrack\\Finance\\' => __DIR__ . '/finance/',
    ];

    foreach ($namespaceMap as $namespace => $dir) {
        if (strpos($class, $namespace) === 0) {
            $relative = substr($class, strlen($namespace));
            $file     = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// ============================================================
// Helper Functions Global
// ============================================================

/**
 * Membersihkan dan meng-escape output HTML untuk mencegah XSS.
 *
 * @param mixed $value Nilai yang akan dibersihkan
 * @return string Nilai yang sudah aman
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Memformat angka ke format mata uang Rupiah.
 *
 * @param float  $amount  Jumlah uang
 * @param bool   $symbol  Tampilkan simbol Rp
 * @return string Format Rupiah
 */
function formatRupiah(float $amount, bool $symbol = true): string
{
    $formatted = number_format($amount, 0, ',', '.');
    return $symbol ? "Rp {$formatted}" : $formatted;
}

/**
 * Memformat tanggal ke format Indonesia.
 *
 * @param string $date Tanggal dalam format Y-m-d
 * @return string Tanggal dalam format Indonesia (dd M YYYY)
 */
function formatTanggal(string $date): string
{
    $bulan = [
        1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'Mei', 6=>'Jun',
        7=>'Jul', 8=>'Agu', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Des'
    ];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $bulan[(int)$d->format('m')] . ' ' . $d->format('Y');
}

/**
 * Menampilkan flash message dari session.
 *
 * @param string $type Tipe pesan ('success'/'error'/'warning'/'info')
 * @return string HTML flash message atau string kosong
 */
function flashMessage(string $type = 'success'): string
{
    if (!isset($_SESSION["flash_{$type}"])) return '';

    $msg = $_SESSION["flash_{$type}"];
    unset($_SESSION["flash_{$type}"]);

    $colors = [
        'success' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400',
        'error'   => 'bg-red-500/10 border-red-500/30 text-red-400',
        'warning' => 'bg-amber-500/10 border-amber-500/30 text-amber-400',
        'info'    => 'bg-blue-500/10 border-blue-500/30 text-blue-400',
    ];

    $icons = [
        'success' => 'check-circle',
        'error'   => 'x-circle',
        'warning' => 'alert-triangle',
        'info'    => 'info',
    ];

    $colorClass = $colors[$type] ?? $colors['info'];
    $icon       = $icons[$type] ?? 'info';

    return "<div class=\"flex items-center gap-3 p-4 rounded-xl border {$colorClass} mb-4\" role=\"alert\">
                <i data-lucide=\"{$icon}\" class=\"w-5 h-5 flex-shrink-0\"></i>
                <span>" . e($msg) . "</span>
            </div>";
}

/**
 * Menyimpan flash message ke session.
 *
 * @param string $message Pesan
 * @param string $type    Tipe ('success'/'error'/'warning'/'info')
 * @return void
 */
function setFlash(string $message, string $type = 'success'): void
{
    $_SESSION["flash_{$type}"] = $message;
}

/**
 * Redirect ke URL tertentu.
 *
 * @param string $url URL tujuan
 * @return void
 */
function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}

/**
 * Mendapatkan nama bulan dalam Bahasa Indonesia.
 *
 * @param int $month Nomor bulan (1-12)
 * @return string Nama bulan
 */
function getNamaBulan(int $month): string
{
    $bulan = [
        1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
        5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
        9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
    ];
    return $bulan[$month] ?? '';
}

/**
 * Membuat CSRF token untuk keamanan form.
 *
 * @return string CSRF token
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Memverifikasi CSRF token dari form.
 *
 * @param string $token Token dari form
 * @return bool True jika valid
 */
function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}
