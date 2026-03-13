<?php
/**
 * FinTrack - Auth Service
 *
 * @package    FinTrack\Auth
 * @author     FinTrack Team
 * @version    1.0.0
 *
 * Kompatibel dengan PHP 7.4+
 */

declare(strict_types=1);

namespace FinTrack\Auth;

use FinTrack\Config\Database;

/**
 * Interface AuthInterface
 *
 * @package FinTrack\Auth
 */
interface AuthInterface
{
    public function login(string $email, string $password);
    public function logout(): void;
    public function register(array $data): int;
}

/**
 * Class User
 *
 * Model pengguna.
 *
 * @package FinTrack\Auth
 */
class User
{
    /** @var \PDO */
    private $db;

    /** @var string */
    private $table = 'users';

    /** @var array Kolom aman tanpa password */
    private $safeColumns = ['id', 'name', 'email', 'avatar', 'role', 'monthly_budget', 'is_active', 'created_at'];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Mencari pengguna berdasarkan email.
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email)
    {
        $sql  = "SELECT * FROM {$this->table} WHERE email = ? AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([strtolower(trim($email))]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Mencari pengguna berdasarkan ID (tanpa password).
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id)
    {
        $cols = implode(', ', $this->safeColumns);
        $sql  = "SELECT {$cols} FROM {$this->table} WHERE id = ? AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Membuat pengguna baru.
     *
     * @param array $data
     * @return int
     * @throws \RuntimeException
     */
    public function create(array $data): int
    {
        if ($this->findByEmail($data['email'])) {
            throw new \RuntimeException('Email sudah digunakan oleh akun lain');
        }

        $sql = "INSERT INTO users (name, email, password, role, monthly_budget)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            htmlspecialchars(trim($data['name'])),
            strtolower(trim($data['email'])),
            password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => SALT_ROUNDS]),
            $data['role']           ?? 'user',
            $data['monthly_budget'] ?? 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Memperbarui profil pengguna.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function updateProfile(int $id, array $data): bool
    {
        $allowed = ['name', 'monthly_budget', 'avatar'];
        $updates = [];
        $params  = [];

        foreach ($allowed as $col) {
            if (isset($data[$col])) {
                $updates[] = "{$col} = ?";
                $params[]  = $data[$col];
            }
        }

        if (empty($updates)) return false;

        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Mengubah password pengguna.
     *
     * @param int    $id
     * @param string $newPassword
     * @return bool
     */
    public function changePassword(int $id, string $newPassword): bool
    {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => SALT_ROUNDS]);
        $sql    = "UPDATE {$this->table} SET password = ? WHERE id = ?";
        $stmt   = $this->db->prepare($sql);
        return $stmt->execute([$hashed, $id]);
    }
}

/**
 * Class AuthService
 *
 * Layanan autentikasi.
 * Mengimplementasikan AuthInterface.
 *
 * @package FinTrack\Auth
 */
class AuthService implements AuthInterface
{
    /** @var User */
    private $userModel;

    /** @var array */
    private $errors = [];

    public function __construct()
    {
        $this->userModel = new User();
        $this->initSession();
    }

    /**
     * Menginisialisasi session.
     */
    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('fintrack_session');
            session_set_cookie_params(7200, '/', '', false, true);
            session_start();
        }
    }

    /**
     * Login pengguna.
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password)
    {
        $this->errors = [];

        if (empty($email) || empty($password)) {
            $this->errors[] = 'Email dan password wajib diisi';
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Format email tidak valid';
            return null;
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->errors[] = 'Email atau password salah';
            return null;
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_at'] = time();

        session_regenerate_id(true);

        unset($user['password']);
        return $user;
    }

    /**
     * Logout pengguna.
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Registrasi pengguna baru.
     *
     * @param array $data
     * @return int
     * @throws \InvalidArgumentException
     */
    public function register(array $data): int
    {
        if (empty($data['name']) || strlen($data['name']) < 3) {
            throw new \InvalidArgumentException('Nama minimal 3 karakter');
        }

        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Format email tidak valid');
        }

        if (strlen($data['password'] ?? '') < 8) {
            throw new \InvalidArgumentException('Password minimal 8 karakter');
        }

        if ($data['password'] !== ($data['password_confirm'] ?? '')) {
            throw new \InvalidArgumentException('Konfirmasi password tidak cocok');
        }

        return $this->userModel->create($data);
    }

    /**
     * Cek apakah pengguna sudah login.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Mendapatkan ID pengguna aktif.
     *
     * @return int|null
     */
    public function getCurrentUserId()
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Mendapatkan data pengguna aktif.
     *
     * @return array|null
     */
    public function getCurrentUser()
    {
        $id = $this->getCurrentUserId();
        return $id ? $this->userModel->findById($id) : null;
    }

    /**
     * Redirect ke login jika belum login.
     *
     * @param string $loginUrl
     */
    public function requireLogin(string $loginUrl = "index.php"): void
    {
        // Tambahkan no-cache agar browser tidak simpan halaman setelah logout
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        if (!$this->isLoggedIn()) {
            header("Location: {$loginUrl}");
            exit;
        }
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Memvalidasi kekuatan password. Skor 0-4.
     *
     * @param string $password
     * @return int
     */
    public static function passwordStrength(string $password): int
    {
        $score = 0;
        if (strlen($password) >= 8)                    $score++;
        if (preg_match('/[A-Z]/', $password))           $score++;
        if (preg_match('/[0-9]/', $password))           $score++;
        if (preg_match('/[^A-Za-z0-9]/', $password))   $score++;
        return $score;
    }
}