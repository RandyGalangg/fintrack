<?php
/**
 * FinTrack - Halaman Manajemen Transaksi
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;
use FinTrack\Finance\Transaction;
use FinTrack\Finance\Income;
use FinTrack\Finance\Expense;
use FinTrack\Finance\Category;

$auth = new AuthService();
$auth->requireLogin();
$userId = $auth->getCurrentUserId();

$txModel     = new Transaction();
$incomeModel = new Income();
$expenseModel = new Expense();
$catModel    = new Category();

$error   = '';
$success = '';

// ============================================================
// Proses Actions (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid';
    } else {
        $action = $_POST['action'] ?? '';

        // ---- Tambah Transaksi ----
        if ($action === 'add') {
            try {
                $type = $_POST['type'] ?? 'expense';
                $data = [
                    'user_id'          => $userId,
                    'category_id'      => (int)$_POST['category_id'],
                    'amount'           => (float)str_replace(['Rp','.',',',' '], ['','','.','' ], $_POST['amount'] ?? '0'),
                    'description'      => trim($_POST['description'] ?? ''),
                    'note'             => trim($_POST['note'] ?? ''),
                    'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
                    'type'             => $type,
                ];

                // Polimorfisme: gunakan subclass sesuai tipe
                if ($type === 'income') {
                    $id = $incomeModel->create($data);
                } else {
                    // Cek anggaran sebelum simpan
                    $budgetCheck = $expenseModel->checkBudget(
                        $userId, $data['category_id'], $data['amount'],
                        (int)date('n', strtotime($data['transaction_date'])),
                        (int)date('Y', strtotime($data['transaction_date']))
                    );
                    $id = $expenseModel->create($data);

                    if ($budgetCheck['exceeded']) {
                        setFlash('Transaksi tersimpan, namun pengeluaran melebihi anggaran kategori ini!', 'warning');
                    }
                }

                if (!isset($_SESSION['flash_warning'])) {
                    setFlash('Transaksi berhasil ditambahkan!', 'success');
                }
                redirect('transactions.php?' . http_build_query($_GET));
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        // ---- Hapus Transaksi ----
        if ($action === 'delete') {
            $id  = (int)($_POST['id'] ?? 0);
            $row = $txModel->findById($id);

            if ($row && (int)$row['user_id'] === $userId) {
                $txModel->delete($id);
                setFlash('Transaksi berhasil dihapus', 'success');
            } else {
                setFlash('Transaksi tidak ditemukan', 'error');
            }
            redirect('transactions.php?' . http_build_query($_GET));
        }

        // ---- Ekspor CSV ----
        if ($action === 'export_csv') {
            $allTx = $txModel->getByUser($userId, [
                'month' => $_POST['export_month'] ?? '',
                'year'  => $_POST['export_year']  ?? '',
            ]);
            $csvPath = $txModel->exportToCsv($allTx);

            // Force download
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . basename($csvPath) . '"');
            header('Content-Length: ' . filesize($csvPath));
            readfile($csvPath);

            // Hapus file sementara setelah dikirim
            unlink($csvPath);
            exit;
        }
    }
}

// ============================================================
// Filter & Data
// ============================================================
$filters = [
    'month'       => $_GET['month']       ?? '',
    'year'        => $_GET['year']        ?? date('Y'),
    'type'        => $_GET['type']        ?? '',
    'category_id' => $_GET['category_id'] ?? '',
];

$transactions = $txModel->getByUser($userId, $filters);
$categories   = $catModel->getByUser($userId);

// Kelompokkan kategori per tipe (array multidimensi)
$catByType = ['income' => [], 'expense' => []];
foreach ($categories as $cat) {
    $catByType[$cat['type']][] = $cat;
}

$pageTitle  = 'Transaksi';
$activePage = 'transactions';
require_once __DIR__ . '/assets/partials/header.php';
?>

<!-- Page Header -->
<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold">Transaksi</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            <?= count($transactions) ?> transaksi ditemukan
        </p>
    </div>
    <div class="flex gap-2">
        <button onclick="openModal('exportModal')" class="btn-ghost">
            <i data-lucide="download" class="w-4 h-4"></i> Ekspor CSV
        </button>
        <button onclick="openModal('txModal')" class="btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Transaksi
        </button>
    </div>
</div>

<?= flashMessage('success') ?>
<?= flashMessage('error') ?>
<?= flashMessage('warning') ?>

<?php if ($error): ?>
<div class="flex items-center gap-3 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 mb-5">
    <i data-lucide="x-circle" class="w-5 h-5"></i>
    <span class="text-sm"><?= e($error) ?></span>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card p-4 mb-5">
    <i data-lucide="filter" class="w-4 h-4" style="color: var(--text-muted);"></i>
    <form method="GET" class="flex items-center gap-3 flex-wrap flex-1">
        <select name="month" class="input-dark" style="width: auto;">
            <option value="">Semua Bulan</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= (string)$m === $filters['month'] ? 'selected' : '' ?>><?= getNamaBulan($m) ?></option>
            <?php endfor; ?>
        </select>

        <select name="year" class="input-dark" style="width: auto;">
            <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= (string)$y === $filters['year'] ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <select name="type" class="input-dark" style="width: auto;">
            <option value="">Semua Tipe</option>
            <option value="income"  <?= $filters['type'] === 'income'  ? 'selected' : '' ?>>Pemasukan</option>
            <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
        </select>

        <select name="category_id" class="input-dark" style="width: auto;">
            <option value="">Semua Kategori</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (string)$cat['id'] === $filters['category_id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-primary" style="padding: 0.5rem 1rem;">Filter</button>
        <a href="transactions.php" class="btn-ghost">Reset</a>
    </form>
</div>

<!-- Transactions Table -->
<div class="card overflow-hidden"><div class="table-wrap">
    <?php if (!empty($transactions)): ?>
    <div>
        <table class="w-full">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th class="text-left p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Tanggal</th>
                    <th class="text-left p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Deskripsi</th>
                    <th class="text-left p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Kategori</th>
                    <th class="text-left p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Tipe</th>
                    <th class="text-right p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Jumlah</th>
                    <th class="text-center p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr style="border-bottom: 1px solid var(--border);" class="hover:bg-white/[0.02] transition-colors">
                    <td class="p-4 text-sm" style="color: var(--text-muted);">
                        <?= formatTanggal($tx['transaction_date']) ?>
                    </td>
                    <td class="p-4">
                        <p class="text-sm font-medium"><?= e($tx['description']) ?></p>
                        <?php if ($tx['note']): ?>
                            <p class="text-xs mt-0.5" style="color: var(--text-muted);"><?= e($tx['note']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg flex items-center justify-center"
                                  style="background: <?= e($tx['category_color']) ?>1a;">
                                <i data-lucide="<?= e($tx['category_icon']) ?>" class="w-3 h-3"
                                   style="color: <?= e($tx['category_color']) ?>;"></i>
                            </span>
                            <span class="text-sm"><?= e($tx['category_name']) ?></span>
                        </div>
                    </td>
                    <td class="p-4">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                     <?= $tx['type'] === 'income' ? 'badge-income' : 'badge-expense' ?>">
                            <?= $tx['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran' ?>
                        </span>
                    </td>
                    <td class="p-4 text-right font-bold text-sm <?= $tx['type'] === 'income' ? 'text-emerald-400' : 'text-red-400' ?>">
                        <?= $tx['type'] === 'income' ? '+' : '-' ?><?= formatRupiah($tx['amount']) ?>
                    </td>
                    <td class="p-4 text-center">
                        <form method="POST" onsubmit="return confirm('Hapus transaksi ini?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                            <button type="submit" class="btn-danger" style="padding: 0.35rem 0.75rem;">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <i data-lucide="inbox" class="w-14 h-14 mb-4" style="color: var(--text-muted); opacity: 0.25;"></i>
            <p style="font-weight: 600; margin-bottom: 0.5rem;">Belum ada transaksi</p>
            <p style="font-size: 0.875rem; color: var(--text-muted);">Klik tombol di atas untuk menambahkan transaksi baru.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- Modal: Tambah Transaksi -->
<!-- ============================================================ -->
<div class="modal-overlay" id="txModal">
    <div class="modal-box">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-bold">Tambah Transaksi</h2>
            <button onclick="closeModal('txModal')" style="color: var(--text-muted);">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add">

            <!-- Tipe Transaksi -->
            <div class="flex bg-white/5 rounded-xl p-1 mb-5">
                <label class="flex-1">
                    <input type="radio" name="type" value="expense" class="sr-only" checked onchange="updateCategories(this.value)">
                    <span class="block py-2 text-center text-sm font-semibold rounded-lg cursor-pointer transition-all type-btn type-expense bg-red-500 text-white">
                        Pengeluaran
                    </span>
                </label>
                <label class="flex-1">
                    <input type="radio" name="type" value="income" class="sr-only" onchange="updateCategories(this.value)">
                    <span class="block py-2 text-center text-sm font-semibold rounded-lg cursor-pointer transition-all type-btn type-income" style="color: var(--text-muted);">
                        Pemasukan
                    </span>
                </label>
            </div>

            <div class="space-y-4">
                <!-- Jumlah -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Jumlah (Rp)</label>
                    <input type="number" name="amount" required min="1" step="any"
                           class="input-dark" placeholder="0">
                </div>

                <!-- Deskripsi -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Deskripsi</label>
                    <input type="text" name="description" required
                           class="input-dark" placeholder="Contoh: Makan siang">
                </div>

                <!-- Kategori -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Kategori</label>
                    <select name="category_id" id="categorySelect" required class="input-dark">
                        <option value="">-- Pilih Kategori --</option>
                        <optgroup label="Pengeluaran" id="expenseOptGroup">
                            <?php foreach ($catByType['expense'] as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Pemasukan" id="incomeOptGroup" style="display:none;">
                            <?php foreach ($catByType['income'] as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <!-- Tanggal -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Tanggal</label>
                    <input type="date" name="transaction_date" required
                           class="input-dark" value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Catatan -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Catatan (Opsional)</label>
                    <textarea name="note" class="input-dark" rows="2"
                              placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeModal('txModal')" class="btn-ghost flex-1" style="justify-content: center;">Batal</button>
                <button type="submit" class="btn-primary flex-1">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Export CSV -->
<div class="modal-overlay" id="exportModal">
    <div class="modal-box" style="max-width: 380px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold">Ekspor ke CSV</h2>
            <button onclick="closeModal('exportModal')" style="color: var(--text-muted);">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="export_csv">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Bulan</label>
                    <select name="export_month" class="input-dark">
                        <option value="">Semua Bulan</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === (int)date('n') ? 'selected' : '' ?>><?= getNamaBulan($m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Tahun</label>
                    <select name="export_year" class="input-dark">
                        <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $y === (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-5">
                <i data-lucide="download" class="w-4 h-4 inline mr-2"></i>Unduh CSV
            </button>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function updateCategories(type) {
    const expGroup = document.getElementById('expenseOptGroup');
    const incGroup = document.getElementById('incomeOptGroup');
    const select   = document.getElementById('categorySelect');

    if (type === 'income') {
        expGroup.style.display = 'none';
        incGroup.style.display = '';
    } else {
        expGroup.style.display = '';
        incGroup.style.display = 'none';
    }
    select.value = '';

    // Update button styles
    document.querySelectorAll('.type-btn').forEach(btn => {
        btn.className = 'block py-2 text-center text-sm font-semibold rounded-lg cursor-pointer transition-all type-btn';
        btn.style.color = 'var(--text-muted)';
    });

    const activeBtn = document.querySelector('.type-' + type);
    if (activeBtn) {
        activeBtn.classList.add(type === 'income' ? 'bg-emerald-500' : 'bg-red-500', 'text-white');
        activeBtn.style.color = '';
    }
}
</script>
JS;

require_once __DIR__ . '/assets/partials/footer.php';
?>
