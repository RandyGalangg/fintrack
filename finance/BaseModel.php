<?php
/**
 * FinTrack - Sistem Manajemen Keuangan Pribadi
 *
 * @package    FinTrack\Finance
 * @author     FinTrack Team
 * @version    1.0.0
 */

declare(strict_types=1);

namespace FinTrack\Finance;

use FinTrack\Config\Database;

/**
 * Interface Auditable
 *
 * Kontrak yang harus dipenuhi oleh model yang mendukung audit log.
 * Menerapkan prinsip Interface Segregation.
 *
 * @package FinTrack\Finance
 */
interface Auditable
{
    /**
     * Mencatat aktivitas ke audit log.
     *
     * @param string   $action   Aksi yang dilakukan (create/update/delete)
     * @param int|null $entityId ID entitas yang terpengaruh
     * @param array    $oldData  Data sebelum perubahan
     * @param array    $newData  Data setelah perubahan
     * @return void
     */
    public function logActivity(string $action, ?int $entityId, array $oldData, array $newData): void;
}

/**
 * Interface Exportable
 *
 * Kontrak untuk model yang dapat diekspor ke berbagai format.
 *
 * @package FinTrack\Finance
 */
interface Exportable
{
    /**
     * Mengekspor data ke format CSV.
     *
     * @param array $data Data yang akan diekspor
     * @return string Path file CSV yang dihasilkan
     */
    public function exportToCsv(array $data): string;
}

/**
 * Abstract Class BaseModel
 *
 * Kelas dasar untuk semua model dalam aplikasi FinTrack.
 * Menyediakan operasi CRUD dasar dan koneksi database.
 * Kelas turunan wajib mendefinisikan properti $table dan $fillable.
 *
 * @package FinTrack\Finance
 * @abstract
 */
abstract class BaseModel implements Auditable
{
    /** @var \PDO Koneksi database */
    protected \PDO $db;

    /** @var string Nama tabel di database */
    protected string $table;

    /** @var string Primary key tabel */
    protected string $primaryKey = 'id';

    /** @var array Kolom yang boleh diisi (mass assignment protection) */
    protected array $fillable = [];

    /** @var int|null ID pengguna yang sedang login */
    protected ?int $currentUserId = null;

    /**
     * Constructor — menginisialisasi koneksi database.
     */
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Menemukan record berdasarkan ID.
     *
     * @param int $id ID record
     * @return array|null Data record atau null jika tidak ditemukan
     */
    public function findById(int $id): ?array
    {
        $sql  = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Mengambil semua record dari tabel.
     *
     * @param string $orderBy Kolom dan arah pengurutan
     * @return array Array dari semua record
     */
    public function findAll(string $orderBy = 'id DESC'): array
    {
        $sql  = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Menyimpan record baru ke database.
     *
     * @param array $data Data yang akan disimpan
     * @return int ID record yang baru dibuat
     * @throws \InvalidArgumentException Jika field tidak valid
     */
    public function create(array $data): int
    {
        // Filter hanya field yang diizinkan
        $filtered = $this->filterFillable($data);

        $columns      = implode(', ', array_keys($filtered));
        $placeholders = implode(', ', array_fill(0, count($filtered), '?'));

        $sql  = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($filtered));

        $newId = (int)$this->db->lastInsertId();
        $this->logActivity('create', $newId, [], $filtered);

        return $newId;
    }

    /**
     * Memperbarui record yang sudah ada.
     *
     * @param int   $id   ID record yang akan diperbarui
     * @param array $data Data baru
     * @return bool True jika berhasil
     */
    public function update(int $id, array $data): bool
    {
        $filtered = $this->filterFillable($data);
        $oldData  = $this->findById($id) ?? [];

        $setParts = array_map(fn($col) => "{$col} = ?", array_keys($filtered));
        $setClause = implode(', ', $setParts);

        $sql  = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $params = [...array_values($filtered), $id];
        $result = $stmt->execute($params);

        if ($result) {
            $this->logActivity('update', $id, $oldData, $filtered);
        }

        return $result;
    }

    /**
     * Menghapus record dari database.
     *
     * @param int $id ID record yang akan dihapus
     * @return bool True jika berhasil
     */
    public function delete(int $id): bool
    {
        $oldData = $this->findById($id) ?? [];

        $sql  = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$id]);

        if ($result) {
            $this->logActivity('delete', $id, $oldData, []);
        }

        return $result;
    }

    /**
     * Menghitung total record dalam tabel.
     *
     * @param string $where Kondisi WHERE opsional
     * @param array  $params Parameter binding
     * @return int Total record
     */
    public function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Memfilter data berdasarkan kolom yang diizinkan (fillable).
     *
     * @param array $data Data input
     * @return array Data yang sudah difilter
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Mencatat aktivitas ke tabel audit_logs.
     * Implementasi dari interface Auditable.
     *
     * @param string   $action   Aksi yang dilakukan
     * @param int|null $entityId ID entitas
     * @param array    $oldData  Data lama
     * @param array    $newData  Data baru
     * @return void
     */
    public function logActivity(string $action, ?int $entityId, array $oldData, array $newData): void
    {
        try {
            $sql = "INSERT INTO audit_logs (user_id, action, entity, entity_id, old_data, new_data, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $this->currentUserId,
                $action,
                $this->table,
                $entityId,
                !empty($oldData) ? json_encode($oldData) : null,
                !empty($newData) ? json_encode($newData) : null,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);
        } catch (\Exception $e) {
            // Audit log tidak boleh menghentikan proses utama
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    /**
     * Memformat angka ke format mata uang Rupiah.
     *
     * @param float $amount Jumlah uang
     * @return string Format Rupiah (contoh: Rp 1.500.000)
     */
    public static function formatCurrency(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * Menghitung persentase dengan aman (menghindari pembagian nol).
     *
     * @param float $part  Bagian
     * @param float $total Total keseluruhan
     * @return float Persentase (0-100)
     */
    public static function calculatePercentage(float $part, float $total): float
    {
        if ($total <= 0) return 0.0;
        return round(($part / $total) * 100, 2);
    }
}
