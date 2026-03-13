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
 * Class Transaction
 *
 * Model untuk mengelola data transaksi keuangan.
 * Mewarisi BaseModel dan menerapkan interface Exportable.
 *
 * @package FinTrack\Finance
 * @extends BaseModel
 * @implements Exportable
 */
class Transaction extends BaseModel implements Exportable
{
    /** @var string Nama tabel database */
    protected string $table = 'transactions';

    /** @var array Kolom yang dapat diisi */
    protected array $fillable = [
        'user_id', 'category_id', 'type', 'amount',
        'description', 'note', 'transaction_date', 'receipt_file'
    ];

    // ============================================================
    // Konstanta Tipe Transaksi
    // ============================================================
    const TYPE_INCOME  = 'income';
    const TYPE_EXPENSE = 'expense';

    /**
     * Mendapatkan semua transaksi milik pengguna tertentu
     * dengan filter opsional.
     *
     * @param int    $userId  ID pengguna
     * @param array  $filters Array filter: month, year, type, category_id
     * @return array Array transaksi beserta nama kategori
     */
    public function getByUser(int $userId, array $filters = []): array
    {
        $params     = [$userId];
        $conditions = ['t.user_id = ?'];

        // Filter berdasarkan bulan dan tahun
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $conditions[] = 'MONTH(t.transaction_date) = ?';
            $conditions[] = 'YEAR(t.transaction_date) = ?';
            $params[]     = (int)$filters['month'];
            $params[]     = (int)$filters['year'];
        }

        // Filter berdasarkan tipe (income/expense)
        if (!empty($filters['type'])) {
            $conditions[] = 't.type = ?';
            $params[]     = $filters['type'];
        }

        // Filter berdasarkan kategori
        if (!empty($filters['category_id'])) {
            $conditions[] = 't.category_id = ?';
            $params[]     = (int)$filters['category_id'];
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT
                    t.*,
                    c.name  AS category_name,
                    c.icon  AS category_icon,
                    c.color AS category_color
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.id
                WHERE {$whereClause}
                ORDER BY t.transaction_date DESC, t.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Menghitung ringkasan keuangan untuk periode tertentu.
     * Menggunakan array untuk menyimpan hasil kalkulasi.
     *
     * @param int $userId ID pengguna
     * @param int $month  Bulan (1-12)
     * @param int $year   Tahun
     * @return array Array ringkasan: total_income, total_expense, net_balance, savings_rate
     */
    public function getSummary(int $userId, int $month, int $year): array
    {
        $sql = "SELECT
                    type,
                    SUM(amount) AS total
                FROM transactions
                WHERE user_id = ?
                  AND MONTH(transaction_date) = ?
                  AND YEAR(transaction_date)  = ?
                GROUP BY type";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $month, $year]);
        $rows = $stmt->fetchAll();

        // Inisialisasi array ringkasan
        $summary = [
            'total_income'  => 0.0,
            'total_expense' => 0.0,
            'net_balance'   => 0.0,
            'savings_rate'  => 0.0,
            'transaction_count' => 0,
        ];

        // Iterasi hasil query menggunakan foreach
        foreach ($rows as $row) {
            if ($row['type'] === self::TYPE_INCOME) {
                $summary['total_income'] = (float)$row['total'];
            } elseif ($row['type'] === self::TYPE_EXPENSE) {
                $summary['total_expense'] = (float)$row['total'];
            }
        }

        $summary['net_balance']  = $summary['total_income'] - $summary['total_expense'];
        $summary['savings_rate'] = self::calculatePercentage(
            $summary['net_balance'],
            $summary['total_income']
        );
        $summary['transaction_count'] = $this->count(
            'user_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?',
            [$userId, $month, $year]
        );

        return $summary;
    }

    /**
     * Mendapatkan data pengeluaran per kategori untuk chart.
     * Mengembalikan array multidimensi untuk keperluan visualisasi.
     *
     * @param int $userId ID pengguna
     * @param int $month  Bulan
     * @param int $year   Tahun
     * @return array Array [category_name, total, color, percentage]
     */
    public function getExpenseByCategory(int $userId, int $month, int $year): array
    {
        $sql = "SELECT
                    c.name  AS category_name,
                    c.color AS category_color,
                    SUM(t.amount) AS total
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ?
                  AND t.type = 'expense'
                  AND MONTH(t.transaction_date) = ?
                  AND YEAR(t.transaction_date)  = ?
                GROUP BY c.id, c.name, c.color
                ORDER BY total DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $month, $year]);
        $rows = $stmt->fetchAll();

        // Hitung total keseluruhan untuk persentase
        $grandTotal = array_sum(array_column($rows, 'total'));

        // Map hasil dengan persentase menggunakan array_map
        return array_map(function ($row) use ($grandTotal) {
            return [
                'name'       => $row['category_name'],
                'color'      => $row['category_color'],
                'total'      => (float)$row['total'],
                'percentage' => self::calculatePercentage((float)$row['total'], (float)$grandTotal),
                'formatted'  => self::formatCurrency((float)$row['total']),
            ];
        }, $rows);
    }

    /**
     * Mendapatkan data tren keuangan 6 bulan terakhir.
     * Menggunakan array untuk menyimpan data per bulan.
     *
     * @param int $userId ID pengguna
     * @return array Array tren bulanan [month_label, income, expense, balance]
     */
    public function getMonthlyTrend(int $userId): array
    {
        $sql = "SELECT
                    YEAR(transaction_date)  AS year,
                    MONTH(transaction_date) AS month,
                    type,
                    SUM(amount) AS total
                FROM transactions
                WHERE user_id = ?
                  AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY YEAR(transaction_date), MONTH(transaction_date), type
                ORDER BY year ASC, month ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        // Inisialisasi array tren dengan 6 bulan terakhir
        $trend     = [];
        $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        // Buat struktur data per bulan menggunakan do-while untuk 6 bulan
        $i = 5;
        do {
            $date  = new \DateTime("first day of -{$i} months");
            $key   = $date->format('Y-m');
            $trend[$key] = [
                'label'   => $monthNames[(int)$date->format('m') - 1] . ' ' . $date->format('Y'),
                'income'  => 0.0,
                'expense' => 0.0,
                'balance' => 0.0,
            ];
            $i--;
        } while ($i >= 0);

        // Isi data dari query
        foreach ($rows as $row) {
            $key = sprintf('%04d-%02d', $row['year'], $row['month']);
            if (isset($trend[$key])) {
                if ($row['type'] === self::TYPE_INCOME) {
                    $trend[$key]['income'] = (float)$row['total'];
                } else {
                    $trend[$key]['expense'] = (float)$row['total'];
                }
                $trend[$key]['balance'] = $trend[$key]['income'] - $trend[$key]['expense'];
            }
        }

        return array_values($trend);
    }

    /**
     * Mengekspor transaksi ke file CSV.
     * Implementasi dari interface Exportable.
     *
     * @param array $data Array transaksi yang akan diekspor
     * @return string Path file CSV
     */
    public function exportToCsv(array $data): string
    {
        $filename  = 'transaksi_' . date('Ymd_His') . '.csv';
        $filepath  = EXPORT_DIR . $filename;

        // Headers CSV (kolom array)
        $headers = ['ID', 'Tanggal', 'Tipe', 'Kategori', 'Deskripsi', 'Jumlah', 'Catatan'];

        $file = fopen($filepath, 'w');

        // Tulis BOM untuk Excel kompatibilitas UTF-8
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Tulis header
        fputcsv($file, $headers, ';');

        // Iterasi data menggunakan for loop
        for ($i = 0; $i < count($data); $i++) {
            $row = $data[$i];
            fputcsv($file, [
                $row['id'],
                $row['transaction_date'],
                ucfirst($row['type']),
                $row['category_name'] ?? '-',
                $row['description'],
                $row['amount'],
                $row['note'] ?? '',
            ], ';');
        }

        fclose($file);
        return $filepath;
    }

    /**
     * Mendapatkan transaksi terbaru dengan limit tertentu.
     *
     * @param int $userId ID pengguna
     * @param int $limit  Jumlah transaksi yang diambil
     * @return array Array transaksi terbaru
     */
    public function getRecent(int $userId, int $limit = 10): array
    {
        $sql = "SELECT t.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
                FROM transactions t
                INNER JOIN categories c ON t.category_id = c.id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}

/**
 * Class Income
 *
 * Subkelas Transaction khusus untuk pemasukan.
 * Mendemonstrasikan Inheritance dan method Overloading.
 *
 * @package FinTrack\Finance
 * @extends Transaction
 */
class Income extends Transaction
{
    /**
     * Membuat transaksi pemasukan baru.
     * Override method create dengan validasi khusus income.
     *
     * @param array $data Data transaksi pemasukan
     * @return int ID transaksi baru
     * @throws \InvalidArgumentException Jika amount tidak valid
     */
    public function create(array $data): int
    {
        // Validasi: amount harus positif
        if ((float)($data['amount'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Jumlah pemasukan harus lebih dari 0');
        }

        // Paksa tipe menjadi income
        $data['type'] = self::TYPE_INCOME;

        return parent::create($data);
    }

    /**
     * Menghitung total pemasukan dalam periode.
     *
     * @param int $userId ID pengguna
     * @param int $month  Bulan
     * @param int $year   Tahun
     * @return float Total pemasukan
     */
    public function getTotalIncome(int $userId, int $month, int $year): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM transactions
                WHERE user_id = ? AND type = 'income'
                  AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $month, $year]);
        return (float)$stmt->fetchColumn();
    }
}

/**
 * Class Expense
 *
 * Subkelas Transaction khusus untuk pengeluaran.
 * Mendemonstrasikan Polymorphism — perilaku berbeda dari kelas yang sama.
 *
 * @package FinTrack\Finance
 * @extends Transaction
 */
class Expense extends Transaction
{
    /**
     * Membuat transaksi pengeluaran baru.
     * Override method create dengan validasi dan cek anggaran.
     *
     * @param array $data Data transaksi pengeluaran
     * @return int ID transaksi baru
     * @throws \InvalidArgumentException Jika amount tidak valid
     */
    public function create(array $data): int
    {
        if ((float)($data['amount'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Jumlah pengeluaran harus lebih dari 0');
        }

        // Paksa tipe menjadi expense
        $data['type'] = self::TYPE_EXPENSE;

        return parent::create($data);
    }

    /**
     * Mengecek apakah pengeluaran melebihi anggaran kategori.
     *
     * @param int   $userId     ID pengguna
     * @param int   $categoryId ID kategori
     * @param float $newAmount  Jumlah pengeluaran baru
     * @param int   $month      Bulan
     * @param int   $year       Tahun
     * @return array ['exceeded' => bool, 'budget' => float, 'spent' => float, 'remaining' => float]
     */
    public function checkBudget(int $userId, int $categoryId, float $newAmount, int $month, int $year): array
    {
        // Ambil anggaran kategori
        $budgetSql  = "SELECT amount FROM budgets WHERE user_id=? AND category_id=? AND month=? AND year=?";
        $budgetStmt = $this->db->prepare($budgetSql);
        $budgetStmt->execute([$userId, $categoryId, $month, $year]);
        $budget = (float)($budgetStmt->fetchColumn() ?: 0);

        if ($budget <= 0) {
            return ['exceeded' => false, 'budget' => 0, 'spent' => 0, 'remaining' => 0];
        }

        // Ambil total pengeluaran kategori bulan ini
        $spentSql  = "SELECT COALESCE(SUM(amount), 0) FROM transactions
                      WHERE user_id=? AND category_id=? AND type='expense'
                        AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?";
        $spentStmt = $this->db->prepare($spentSql);
        $spentStmt->execute([$userId, $categoryId, $month, $year]);
        $spent = (float)$spentStmt->fetchColumn() + $newAmount;

        return [
            'exceeded'  => $spent > $budget,
            'budget'    => $budget,
            'spent'     => $spent,
            'remaining' => $budget - $spent,
        ];
    }
}
