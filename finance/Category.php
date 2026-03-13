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

/**
 * Class Category
 *
 * Model untuk mengelola kategori transaksi.
 * Mewarisi BaseModel untuk operasi CRUD standar.
 *
 * @package FinTrack\Finance
 * @extends BaseModel
 */
class Category extends BaseModel
{
    /** @var string Nama tabel database */
    protected string $table = 'categories';

    /** @var array Kolom yang dapat diisi */
    protected array $fillable = ['user_id', 'name', 'type', 'icon', 'color'];

    /**
     * Array ikon yang tersedia untuk kategori.
     * Menggunakan array statis sebagai konstanta data.
     *
     * @var array<string, string>
     */
    public static array $availableIcons = [
        'briefcase'   => 'Gaji/Kerja',
        'laptop'      => 'Freelance',
        'trending-up' => 'Investasi',
        'gift'        => 'Hadiah',
        'utensils'    => 'Makan & Minum',
        'car'         => 'Transportasi',
        'shopping-bag'=> 'Belanja',
        'file-text'   => 'Tagihan',
        'film'        => 'Hiburan',
        'heart'       => 'Kesehatan',
        'home'        => 'Rumah',
        'book'        => 'Pendidikan',
        'piggy-bank'  => 'Tabungan',
        'zap'         => 'Utilitas',
        'tag'         => 'Lainnya',
    ];

    /**
     * Mendapatkan semua kategori milik pengguna, dikelompokkan berdasarkan tipe.
     *
     * @param int    $userId ID pengguna
     * @param string $type   Tipe kategori ('income'/'expense'/'all')
     * @return array Array kategori
     */
    public function getByUser(int $userId, string $type = 'all'): array
    {
        $params = [$userId];
        $sql    = "SELECT * FROM categories WHERE user_id = ?";

        if ($type !== 'all') {
            $sql    .= " AND type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Mendapatkan kategori dikelompokkan berdasarkan tipe.
     * Menggunakan array multidimensi untuk pengelompokan.
     *
     * @param int $userId ID pengguna
     * @return array ['income' => [...], 'expense' => [...]]
     */
    public function getGroupedByType(int $userId): array
    {
        $categories = $this->getByUser($userId);

        // Kelompokkan menggunakan array_reduce
        return array_reduce($categories, function ($carry, $item) {
            $carry[$item['type']][] = $item;
            return $carry;
        }, ['income' => [], 'expense' => []]);
    }

    /**
     * Mengecek apakah kategori memiliki transaksi terkait.
     *
     * @param int $categoryId ID kategori
     * @return bool True jika ada transaksi
     */
    public function hasTransactions(int $categoryId): bool
    {
        $sql  = "SELECT COUNT(*) FROM transactions WHERE category_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$categoryId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

/**
 * Class Budget
 *
 * Model untuk mengelola anggaran per kategori per bulan.
 * Mewarisi BaseModel dan menambahkan logika kalkulasi anggaran.
 *
 * @package FinTrack\Finance
 * @extends BaseModel
 */
class Budget extends BaseModel
{
    /** @var string Nama tabel database */
    protected string $table = 'budgets';

    /** @var array Kolom yang dapat diisi */
    protected array $fillable = ['user_id', 'category_id', 'amount', 'month', 'year'];

    /**
     * Mendapatkan anggaran dengan status realisasi untuk bulan tertentu.
     * Menggunakan JOIN dan subquery untuk data lengkap.
     *
     * @param int $userId ID pengguna
     * @param int $month  Bulan
     * @param int $year   Tahun
     * @return array Array anggaran beserta realisasi pengeluaran
     */
    public function getBudgetWithRealization(int $userId, int $month, int $year): array
    {
        $sql = "SELECT
                    b.id,
                    b.amount         AS budget_amount,
                    b.month,
                    b.year,
                    c.name           AS category_name,
                    c.icon           AS category_icon,
                    c.color          AS category_color,
                    COALESCE(
                        (SELECT SUM(t.amount)
                         FROM transactions t
                         WHERE t.category_id = b.category_id
                           AND t.user_id     = b.user_id
                           AND t.type        = 'expense'
                           AND MONTH(t.transaction_date) = b.month
                           AND YEAR(t.transaction_date)  = b.year
                        ), 0
                    ) AS spent_amount
                FROM budgets b
                INNER JOIN categories c ON b.category_id = c.id
                WHERE b.user_id = ? AND b.month = ? AND b.year = ?
                ORDER BY c.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $month, $year]);
        $rows = $stmt->fetchAll();

        // Hitung persentase dan status untuk setiap baris
        return array_map(function ($row) {
            $percentage = self::calculatePercentage(
                (float)$row['spent_amount'],
                (float)$row['budget_amount']
            );

            // Percabangan if-else untuk menentukan status
            if ($percentage >= 100) {
                $status = 'danger';
            } elseif ($percentage >= 80) {
                $status = 'warning';
            } else {
                $status = 'safe';
            }

            return array_merge($row, [
                'percentage'  => $percentage,
                'status'      => $status,
                'remaining'   => (float)$row['budget_amount'] - (float)$row['spent_amount'],
                'formatted_budget' => self::formatCurrency((float)$row['budget_amount']),
                'formatted_spent'  => self::formatCurrency((float)$row['spent_amount']),
            ]);
        }, $rows);
    }

    /**
     * Menyimpan atau memperbarui anggaran (upsert).
     *
     * @param int   $userId     ID pengguna
     * @param int   $categoryId ID kategori
     * @param float $amount     Jumlah anggaran
     * @param int   $month      Bulan
     * @param int   $year       Tahun
     * @return bool True jika berhasil
     */
    public function upsert(int $userId, int $categoryId, float $amount, int $month, int $year): bool
    {
        $sql = "INSERT INTO budgets (user_id, category_id, amount, month, year)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = NOW()";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $categoryId, $amount, $month, $year]);
    }

    /**
     * Menghitung total anggaran bulan tertentu.
     *
     * @param int $userId ID pengguna
     * @param int $month  Bulan
     * @param int $year   Tahun
     * @return float Total anggaran
     */
    public function getTotalBudget(int $userId, int $month, int $year): float
    {
        $sql  = "SELECT COALESCE(SUM(amount), 0) FROM budgets
                 WHERE user_id=? AND month=? AND year=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $month, $year]);
        return (float)$stmt->fetchColumn();
    }
}

/**
 * Class SavingsGoal
 *
 * Model untuk mengelola tujuan tabungan.
 *
 * @package FinTrack\Finance
 * @extends BaseModel
 */
class SavingsGoal extends BaseModel
{
    /** @var string Nama tabel database */
    protected string $table = 'savings_goals';

    /** @var array Kolom yang dapat diisi */
    protected array $fillable = [
        'user_id', 'title', 'target_amount', 'current_amount',
        'deadline', 'icon', 'color', 'status'
    ];

    /**
     * Mendapatkan semua tujuan tabungan pengguna.
     *
     * @param int    $userId ID pengguna
     * @param string $status Status tabungan ('active'/'completed'/'all')
     * @return array Array tujuan tabungan dengan info progres
     */
    public function getByUser(int $userId, string $status = 'active'): array
    {
        $params     = [$userId];
        $conditions = ['user_id = ?'];

        if ($status !== 'all') {
            $conditions[] = 'status = ?';
            $params[]     = $status;
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $goals = $stmt->fetchAll();

        // Enrichment data dengan kalkulasi
        foreach ($goals as &$goal) {
            $goal['percentage']  = self::calculatePercentage(
                (float)$goal['current_amount'],
                (float)$goal['target_amount']
            );
            $goal['remaining']   = (float)$goal['target_amount'] - (float)$goal['current_amount'];
            $goal['is_completed'] = $goal['percentage'] >= 100;

            // Hitung hari tersisa jika ada deadline
            if ($goal['deadline']) {
                $deadline          = new \DateTime($goal['deadline']);
                $today             = new \DateTime();
                $diff              = $today->diff($deadline);
                $goal['days_left'] = $deadline >= $today ? (int)$diff->days : -1;
            } else {
                $goal['days_left'] = null;
            }
        }
        unset($goal);

        return $goals;
    }

    /**
     * Menambah dana ke tujuan tabungan.
     *
     * @param int   $goalId    ID tujuan tabungan
     * @param float $addAmount Jumlah yang ditambahkan
     * @return bool True jika berhasil
     */
    public function addFund(int $goalId, float $addAmount): bool
    {
        $goal = $this->findById($goalId);
        if (!$goal) return false;

        $newAmount = (float)$goal['current_amount'] + $addAmount;
        $newStatus = $newAmount >= (float)$goal['target_amount'] ? 'completed' : 'active';

        return $this->update($goalId, [
            'current_amount' => $newAmount,
            'status'         => $newStatus,
        ]);
    }
}
