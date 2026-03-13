<?php
/**
 * FinTrack - Halaman Dashboard
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;
use FinTrack\Finance\Transaction;
use FinTrack\Finance\Budget;
use FinTrack\Finance\SavingsGoal;

// Auth guard
$auth = new AuthService();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$userId      = $auth->getCurrentUserId();

// Periode filter (default bulan ini)
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

// Ambil data
$txModel   = new Transaction();
$budgetModel = new Budget();
$savingsModel = new SavingsGoal();

$summary         = $txModel->getSummary($userId, $month, $year);
$expenseByCategory = $txModel->getExpenseByCategory($userId, $month, $year);
$monthlyTrend    = $txModel->getMonthlyTrend($userId);
$recentTx        = $txModel->getRecent($userId, 8);
$budgets         = $budgetModel->getBudgetWithRealization($userId, $month, $year);
$savingsGoals    = $savingsModel->getByUser($userId, 'active');

// Hitung persentase anggaran terpakai
$totalBudget = $currentUser['monthly_budget'] ?? 0;
$budgetUsage = $totalBudget > 0 ? min(100, round(($summary['total_expense'] / $totalBudget) * 100, 1)) : 0;

// Data chart tren (untuk Chart.js)
$trendLabels   = array_column($monthlyTrend, 'label');
$trendIncome   = array_column($monthlyTrend, 'income');
$trendExpense  = array_column($monthlyTrend, 'expense');

// Data chart donut kategori
$catLabels = array_column($expenseByCategory, 'name');
$catValues = array_column($expenseByCategory, 'total');
$catColors = array_column($expenseByCategory, 'color');

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/assets/partials/header.php';
?>

<!-- Page Header -->
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Halo, <?= e($currentUser['name']) ?>! 👋 Ini ringkasan keuangan Anda.
        </p>
    </div>

    <!-- Filter Periode -->
    <form method="GET" class="flex items-center gap-2">
        <select name="month" class="input-dark" style="width: auto; padding: 0.5rem 0.75rem;" onchange="this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= getNamaBulan($m) ?></option>
            <?php endfor; ?>
        </select>
        <select name="year" class="input-dark" style="width: auto; padding: 0.5rem 0.75rem;" onchange="this.form.submit()">
            <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<?= flashMessage('success') ?>
<?= flashMessage('error') ?>

<!-- ============ STAT CARDS ============ -->
<div class="grid-stats mb-6">

    <!-- Total Pemasukan -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <p style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Total Pemasukan</p>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(34,197,94,0.12);">
                <i data-lucide="trending-up" class="w-4 h-4 text-emerald-400"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-emerald-400"><?= formatRupiah($summary['total_income']) ?></p>
        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
            <?= getNamaBulan($month) ?> <?= $year ?>
        </p>
    </div>

    <!-- Total Pengeluaran -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <p style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Total Pengeluaran</p>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(239,68,68,0.12);">
                <i data-lucide="trending-down" class="w-4 h-4 text-red-400"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-red-400"><?= formatRupiah($summary['total_expense']) ?></p>
        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
            <?= $summary['transaction_count'] ?> transaksi
        </p>
    </div>

    <!-- Saldo Bersih -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <p style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Saldo Bersih</p>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(99,102,241,0.12);">
                <i data-lucide="wallet" class="w-4 h-4" style="color: #818cf8;"></i>
            </div>
        </div>
        <p class="text-2xl font-bold <?= $summary['net_balance'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
            <?= formatRupiah($summary['net_balance']) ?>
        </p>
        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
            Tingkat tabungan: <?= $summary['savings_rate'] ?>%
        </p>
    </div>

    <!-- Penggunaan Anggaran -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <p style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">Anggaran Bulanan</p>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.12);">
                <i data-lucide="target" class="w-4 h-4 text-amber-400"></i>
            </div>
        </div>
        <p class="text-2xl font-bold <?= $budgetUsage >= 100 ? 'text-red-400' : ($budgetUsage >= 80 ? 'text-amber-400' : 'text-white') ?>">
            <?= $budgetUsage ?>%
        </p>
        <div class="progress-bar mt-2">
            <div class="progress-fill <?= $budgetUsage >= 100 ? 'bg-red-500' : ($budgetUsage >= 80 ? 'bg-amber-500' : 'bg-emerald-500') ?>"
                 style="width: <?= min(100, $budgetUsage) ?>%"></div>
        </div>
        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">
            <?= formatRupiah($totalBudget) ?> limit
        </p>
    </div>

</div>

<!-- ============ CHARTS ROW ============ -->
<div class="grid-chart mb-6">

    <!-- Line Chart Tren -->
    <div class="card p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="font-semibold">Tren Keuangan</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted);">6 bulan terakhir</p>
            </div>
            <div class="flex items-center gap-4 text-xs" style="color: var(--text-muted);">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span> Pemasukan
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm bg-red-500 inline-block"></span> Pengeluaran
                </span>
            </div>
        </div>
        <div style="position:relative; height:260px;"><canvas id="trendChart"></canvas></div>
    </div>

    <!-- Donut Chart Kategori -->
    <div class="card p-5">
        <div class="mb-5">
            <h3 class="font-semibold">Pengeluaran per Kategori</h3>
            <p style="font-size: 0.8rem; color: var(--text-muted);"><?= getNamaBulan($month) ?> <?= $year ?></p>
        </div>

        <?php if (!empty($expenseByCategory)): ?>
            <div style="position:relative; height:200px;"><canvas id="categoryChart"></canvas></div>
            <div class="mt-4 space-y-2">
                <?php foreach (array_slice($expenseByCategory, 0, 4) as $cat): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: <?= e($cat['color']) ?>;"></span>
                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?= e($cat['name']) ?></span>
                    </div>
                    <span style="font-size: 0.8rem; font-weight: 600;"><?= $cat['percentage'] ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-48 text-center">
                <i data-lucide="pie-chart" class="w-10 h-10 mb-3" style="color: var(--text-muted); opacity: 0.3;"></i>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Belum ada pengeluaran</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ============ BOTTOM ROW ============ -->
<div class="grid-bottom">

    <!-- Transaksi Terbaru -->
    <div class="card p-5">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold">Transaksi Terbaru</h3>
            <a href="transactions.php" class="btn-ghost" style="font-size: 0.75rem;">
                Lihat Semua <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>

        <?php if (!empty($recentTx)): ?>
        <div class="space-y-3">
            <?php foreach ($recentTx as $tx): ?>
            <div class="flex items-center gap-4 p-3 rounded-xl" style="background: rgba(255,255,255,0.02);">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background: <?= e($tx['category_color']) ?>1a;">
                    <i data-lucide="<?= e($tx['category_icon']) ?>" class="w-4 h-4"
                       style="color: <?= e($tx['category_color']) ?>;"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p style="font-size: 0.85rem; font-weight: 500;" class="truncate"><?= e($tx['description']) ?></p>
                    <p style="font-size: 0.75rem; color: var(--text-muted);">
                        <?= e($tx['category_name']) ?> · <?= formatTanggal($tx['transaction_date']) ?>
                    </p>
                </div>
                <span class="font-bold text-sm flex-shrink-0 <?= $tx['type'] === 'income' ? 'text-emerald-400' : 'text-red-400' ?>">
                    <?= $tx['type'] === 'income' ? '+' : '-' ?><?= formatRupiah($tx['amount']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-40 text-center">
                <i data-lucide="inbox" class="w-10 h-10 mb-3" style="color: var(--text-muted); opacity: 0.3;"></i>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Belum ada transaksi</p>
                <a href="transactions.php" class="btn-primary mt-3" style="padding: 0.5rem 1rem; font-size: 0.8rem;">Tambah Transaksi</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tujuan Tabungan -->
    <div class="card p-5">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold">Tujuan Tabungan</h3>
            <a href="savings.php" class="btn-ghost" style="font-size: 0.75rem;">
                Kelola <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>

        <?php if (!empty($savingsGoals)): ?>
        <div class="space-y-4">
            <?php foreach (array_slice($savingsGoals, 0, 3) as $goal): ?>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs"
                              style="background: <?= e($goal['color']) ?>1a; color: <?= e($goal['color']) ?>;">
                            <i data-lucide="<?= e($goal['icon']) ?>" class="w-3 h-3"></i>
                        </span>
                        <span style="font-size: 0.82rem; font-weight: 500;"><?= e($goal['title']) ?></span>
                    </div>
                    <span style="font-size: 0.8rem; font-weight: 700; color: <?= e($goal['color']) ?>;">
                        <?= $goal['percentage'] ?>%
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= min(100, $goal['percentage']) ?>%; background: <?= e($goal['color']) ?>;"></div>
                </div>
                <div class="flex justify-between mt-1">
                    <span style="font-size: 0.7rem; color: var(--text-muted);"><?= formatRupiah($goal['current_amount']) ?></span>
                    <span style="font-size: 0.7rem; color: var(--text-muted);"><?= formatRupiah($goal['target_amount']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-32 text-center">
                <i data-lucide="piggy-bank" class="w-8 h-8 mb-2" style="color: var(--text-muted); opacity: 0.3;"></i>
                <p style="font-size: 0.82rem; color: var(--text-muted);">Belum ada tujuan tabungan</p>
                <a href="savings.php" class="btn-primary mt-3" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">Buat Tujuan</a>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
// JSON encode data untuk Chart.js
$trendLabelsJson  = json_encode($trendLabels);
$trendIncomeJson  = json_encode($trendIncome);
$trendExpenseJson = json_encode($trendExpense);
$catLabelsJson    = json_encode($catLabels);
$catValuesJson    = json_encode($catValues);
$catColorsJson    = json_encode($catColors);

$extraScripts = <<<JS
<script>
// ============================================================
// Chart.js — Tren Keuangan (Line Chart)
// External Library: Chart.js
// ============================================================
const trendCtx = document.getElementById('trendChart');
if (trendCtx) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: {$trendLabelsJson},
            datasets: [
                {
                    label: 'Pemasukan',
                    data: {$trendIncomeJson},
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34,197,94,0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#22c55e',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4,
                },
                {
                    label: 'Pengeluaran',
                    data: {$trendExpenseJson},
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.06)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#ef4444',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1a24',
                    borderColor: 'rgba(255,255,255,0.08)',
                    borderWidth: 1,
                    titleColor: '#f0f0f5',
                    bodyColor: '#9ca3af',
                    padding: 12,
                    callbacks: {
                        label: ctx => ' ' + ctx.dataset.label + ': Rp ' +
                               ctx.parsed.y.toLocaleString('id-ID')
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#6b7280', font: { size: 11 } }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: {
                        color: '#6b7280',
                        font: { size: 11 },
                        callback: v => 'Rp ' + (v/1e6).toFixed(1) + 'jt'
                    }
                }
            }
        }
    });
}

// ============================================================
// Chart.js — Kategori (Doughnut Chart)
// ============================================================
const catCtx = document.getElementById('categoryChart');
if (catCtx && {$catValuesJson}.length > 0) {
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: {$catLabelsJson},
            datasets: [{
                data: {$catValuesJson},
                backgroundColor: {$catColorsJson}.map(c => c + 'cc'),
                borderColor: {$catColorsJson},
                borderWidth: 2,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1a24',
                    borderColor: 'rgba(255,255,255,0.08)',
                    borderWidth: 1,
                    titleColor: '#f0f0f5',
                    bodyColor: '#9ca3af',
                    padding: 10,
                    callbacks: {
                        label: ctx => ' ' + ctx.label + ': Rp ' +
                               ctx.parsed.toLocaleString('id-ID')
                    }
                }
            }
        }
    });
}
</script>
JS;

require_once __DIR__ . '/assets/partials/footer.php';
?>