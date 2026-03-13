<?php
/**
 * FinTrack - Halaman Laporan Keuangan
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;
use FinTrack\Finance\Transaction;

$auth = new AuthService();
$auth->requireLogin();
$userId = $auth->getCurrentUserId();

$txModel = new Transaction();

$year  = (int)($_GET['year'] ?? date('Y'));

// Ambil data untuk semua bulan dalam tahun yang dipilih
$yearlyData = [];
$monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

// Gunakan for loop untuk iterasi 12 bulan
for ($m = 1; $m <= 12; $m++) {
    $summary        = $txModel->getSummary($userId, $m, $year);
    $yearlyData[$m] = [
        'label'   => $monthNames[$m - 1],
        'income'  => $summary['total_income'],
        'expense' => $summary['total_expense'],
        'balance' => $summary['net_balance'],
    ];
}

// Statistik tahunan menggunakan array_column dan array functions
$totalIncomeYear  = array_sum(array_column($yearlyData, 'income'));
$totalExpenseYear = array_sum(array_column($yearlyData, 'expense'));
$netBalanceYear   = $totalIncomeYear - $totalExpenseYear;
$bestMonth        = array_keys($yearlyData, max($yearlyData, fn($a, $b) => $a['balance'] - $b['balance']));

// Data untuk chart
$labels       = array_column($yearlyData, 'label');
$incomeData   = array_column($yearlyData, 'income');
$expenseData  = array_column($yearlyData, 'expense');
$balanceData  = array_column($yearlyData, 'balance');

// Kategori pengeluaran tahun ini (tanpa filter bulan)
$stmt = $txModel->getExpenseByCategory($userId, (int)date('n'), $year);

$pageTitle  = 'Laporan';
$activePage = 'reports';
require_once __DIR__ . '/assets/partials/header.php';
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold">Laporan Keuangan</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Ringkasan tahunan <?= $year ?></p>
    </div>
    <form method="GET">
        <select name="year" class="input-dark" style="width: auto;" onchange="this.form.submit()">
            <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- Annual Stats -->
<div class="grid-stats mb-6">
    <div class="stat-card">
        <p class="text-xs font-medium mb-2" style="color: var(--text-muted);">Total Pemasukan <?= $year ?></p>
        <p class="text-2xl font-bold text-emerald-400"><?= formatRupiah($totalIncomeYear) ?></p>
        <p class="text-xs mt-1" style="color: var(--text-muted);">Rata-rata <?= formatRupiah($totalIncomeYear / 12) ?>/bulan</p>
    </div>
    <div class="stat-card">
        <p class="text-xs font-medium mb-2" style="color: var(--text-muted);">Total Pengeluaran <?= $year ?></p>
        <p class="text-2xl font-bold text-red-400"><?= formatRupiah($totalExpenseYear) ?></p>
        <p class="text-xs mt-1" style="color: var(--text-muted);">Rata-rata <?= formatRupiah($totalExpenseYear / 12) ?>/bulan</p>
    </div>
    <div class="stat-card">
        <p class="text-xs font-medium mb-2" style="color: var(--text-muted);">Net Saldo <?= $year ?></p>
        <p class="text-2xl font-bold <?= $netBalanceYear >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
            <?= formatRupiah($netBalanceYear) ?>
        </p>
        <p class="text-xs mt-1" style="color: var(--text-muted);">
            Tingkat tabungan: <?= $totalIncomeYear > 0 ? round(($netBalanceYear / $totalIncomeYear) * 100, 1) : 0 ?>%
        </p>
    </div>
</div>

<!-- Bar Chart Tahunan -->
<div class="card p-6 mb-6">
    <h2 class="font-semibold mb-5">Pemasukan vs Pengeluaran — <?= $year ?></h2>
    <div style="position:relative; height:280px;"><canvas id="annualChart"></canvas></div>
</div>

<!-- Balance Trend -->
<div class="card p-6 mb-6">
    <h2 class="font-semibold mb-5">Tren Saldo Bersih — <?= $year ?></h2>
    <div style="position:relative; height:200px;"><canvas id="balanceChart"></canvas></div>
</div>

<!-- Monthly Detail Table -->
<div class="card overflow-hidden">
    <div class="p-5 border-b" style="border-color: var(--border);">
        <h2 class="font-semibold">Detail Bulanan</h2>
    </div>
    <div class="table-wrap">
        <table class="w-full">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th class="text-left p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Bulan</th>
                    <th class="text-right p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Pemasukan</th>
                    <th class="text-right p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Pengeluaran</th>
                    <th class="text-right p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Saldo</th>
                    <th class="text-right p-4 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Rasio Tabungan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($yearlyData as $m => $data): ?>
                <?php
                    $savingRate = $data['income'] > 0
                        ? round(($data['balance'] / $data['income']) * 100, 1)
                        : 0;
                    $isCurrent = $m === (int)date('n') && $year === (int)date('Y');
                ?>
                <tr style="border-bottom: 1px solid var(--border);"
                    class="<?= $isCurrent ? 'bg-white/[0.03]' : 'hover:bg-white/[0.01]' ?> transition-colors">
                    <td class="p-4">
                        <span class="text-sm font-medium"><?= getNamaBulan($m) ?> <?= $year ?></span>
                        <?php if ($isCurrent): ?>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400">Sekarang</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-right text-sm font-medium text-emerald-400">
                        <?= $data['income'] > 0 ? formatRupiah($data['income']) : '-' ?>
                    </td>
                    <td class="p-4 text-right text-sm font-medium text-red-400">
                        <?= $data['expense'] > 0 ? formatRupiah($data['expense']) : '-' ?>
                    </td>
                    <td class="p-4 text-right text-sm font-bold <?= $data['balance'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                        <?= ($data['income'] > 0 || $data['expense'] > 0) ? formatRupiah($data['balance']) : '-' ?>
                    </td>
                    <td class="p-4 text-right">
                        <?php if ($data['income'] > 0): ?>
                            <span class="text-sm <?= $savingRate >= 20 ? 'text-emerald-400' : ($savingRate >= 0 ? 'text-amber-400' : 'text-red-400') ?>">
                                <?= $savingRate ?>%
                            </span>
                        <?php else: ?>
                            <span class="text-sm" style="color: var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid var(--border); background: rgba(255,255,255,0.02);">
                    <td class="p-4 text-sm font-bold">TOTAL</td>
                    <td class="p-4 text-right text-sm font-bold text-emerald-400"><?= formatRupiah($totalIncomeYear) ?></td>
                    <td class="p-4 text-right text-sm font-bold text-red-400"><?= formatRupiah($totalExpenseYear) ?></td>
                    <td class="p-4 text-right text-sm font-bold <?= $netBalanceYear >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= formatRupiah($netBalanceYear) ?></td>
                    <td class="p-4 text-right text-sm font-bold">
                        <?= $totalIncomeYear > 0 ? round(($netBalanceYear / $totalIncomeYear) * 100, 1) : 0 ?>%
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php
$labelsJson  = json_encode(array_values($labels));
$incJson     = json_encode(array_values($incomeData));
$expJson     = json_encode(array_values($expenseData));
$balJson     = json_encode(array_values($balanceData));

$extraScripts = <<<JS
<script>
// Bar Chart
new Chart(document.getElementById('annualChart'), {
    type: 'bar',
    data: {
        labels: {$labelsJson},
        datasets: [
            {
                label: 'Pemasukan',
                data: {$incJson},
                backgroundColor: 'rgba(34,197,94,0.7)',
                borderColor: '#22c55e',
                borderWidth: 1,
                borderRadius: 6,
            },
            {
                label: 'Pengeluaran',
                data: {$expJson},
                backgroundColor: 'rgba(239,68,68,0.7)',
                borderColor: '#ef4444',
                borderWidth: 1,
                borderRadius: 6,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#9ca3af', font: { size: 12 } } },
            tooltip: {
                backgroundColor: '#1a1a24',
                borderColor: 'rgba(255,255,255,0.08)',
                borderWidth: 1,
                callbacks: { label: ctx => ' ' + ctx.dataset.label + ': Rp ' + ctx.parsed.y.toLocaleString('id-ID') }
            }
        },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b7280' } },
            y: {
                grid: { color: 'rgba(255,255,255,0.04)' },
                ticks: { color: '#6b7280', callback: v => 'Rp ' + (v/1e6).toFixed(1) + 'jt' }
            }
        }
    }
});

// Balance Line Chart
new Chart(document.getElementById('balanceChart'), {
    type: 'line',
    data: {
        labels: {$labelsJson},
        datasets: [{
            label: 'Saldo Bersih',
            data: {$balJson},
            borderColor: '#818cf8',
            backgroundColor: ctx => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 200);
                gradient.addColorStop(0, 'rgba(129,140,248,0.2)');
                gradient.addColorStop(1, 'rgba(129,140,248,0)');
                return gradient;
            },
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#818cf8',
            pointRadius: 5,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1a1a24',
                borderColor: 'rgba(255,255,255,0.08)',
                borderWidth: 1,
                callbacks: { label: ctx => ' Saldo: Rp ' + ctx.parsed.y.toLocaleString('id-ID') }
            }
        },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b7280' } },
            y: {
                grid: { color: 'rgba(255,255,255,0.04)' },
                ticks: { color: '#6b7280', callback: v => 'Rp ' + (v/1e6).toFixed(1) + 'jt' }
            }
        }
    }
});
</script>
JS;

require_once __DIR__ . '/assets/partials/footer.php';
?>