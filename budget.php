<?php
/**
 * FinTrack - Halaman Manajemen Anggaran
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;
use FinTrack\Finance\Budget;
use FinTrack\Finance\Category;

$auth = new AuthService();
$auth->requireLogin();
$userId = $auth->getCurrentUserId();

$budgetModel = new Budget();
$catModel    = new Category();

// Periode default: bulan ini
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

// Proses POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid', 'error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_budget') {
            // Loop melalui array budgets yang dikirim
            $budgets     = $_POST['budgets']     ?? [];
            $categoryIds = $_POST['category_ids'] ?? [];
            $saved       = 0;

            // Menggunakan for loop untuk iterasi array
            for ($i = 0; $i < count($categoryIds); $i++) {
                $catId  = (int)$categoryIds[$i];
                $amount = (float)($budgets[$i] ?? 0);

                if ($catId > 0 && $amount >= 0) {
                    $budgetModel->upsert($userId, $catId, $amount, $month, $year);
                    $saved++;
                }
            }

            setFlash("Berhasil menyimpan {$saved} anggaran!", 'success');
            redirect("budget.php?month={$month}&year={$year}");
        }
    }
}

// Data
$budgetData = $budgetModel->getBudgetWithRealization($userId, $month, $year);
$expCats    = $catModel->getByUser($userId, 'expense');
$totalBudget = $budgetModel->getTotalBudget($userId, $month, $year);

// Buat map budget per category_id menggunakan array_column
$budgetMap = [];
foreach ($budgetData as $b) {
    // Membuat array asosiatif dari data budget
    $budgetMap[$b['category_id'] ?? ''] = $b;
}

$pageTitle  = 'Anggaran';
$activePage = 'budget';
require_once __DIR__ . '/assets/partials/header.php';
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold">Anggaran</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Kelola anggaran <?= getNamaBulan($month) ?> <?= $year ?>
        </p>
    </div>

    <form method="GET" class="flex gap-2">
        <select name="month" class="input-dark" style="width: auto;" onchange="this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= getNamaBulan($m) ?></option>
            <?php endfor; ?>
        </select>
        <select name="year" class="input-dark" style="width: auto;" onchange="this.form.submit()">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<?= flashMessage('success') ?>
<?= flashMessage('error') ?>

<!-- Summary -->
<div class="grid-stats mb-6">
    <?php
    $totalSpent     = array_sum(array_column($budgetData, 'spent_amount'));
    $totalRemaining = $totalBudget - $totalSpent;
    $overBudget     = array_filter($budgetData, fn($b) => $b['status'] === 'danger');
    ?>
    <div class="stat-card">
        <p class="text-xs font-medium mb-2" style="color: var(--text-muted);">Total Anggaran</p>
        <p class="text-2xl font-bold"><?= formatRupiah($totalBudget) ?></p>
    </div>
    <div class="stat-card">
        <p class="text-xs font-medium mb-2" style="color: var(--text-muted);">Total Terpakai</p>
        <p class="text-2xl font-bold text-amber-400"><?= formatRupiah($totalSpent) ?></p>
    </div>
    <div class="stat-card">
        <p class="text-xs font-medium mb-2" style="color: var(--text-muted);">Sisa Anggaran</p>
        <p class="text-2xl font-bold <?= $totalRemaining < 0 ? 'text-red-400' : 'text-emerald-400' ?>">
            <?= formatRupiah($totalRemaining) ?>
        </p>
    </div>
</div>

<div class="grid-budget">

    <!-- Realisasi Anggaran -->
    <div class="card p-6">
        <h2 class="font-semibold mb-5">Realisasi Anggaran</h2>

        <?php if (!empty($budgetData)): ?>
        <div class="space-y-5">
            <?php foreach ($budgetData as $item):
                $statusColors = [
                    'safe'    => '#22c55e',
                    'warning' => '#f59e0b',
                    'danger'  => '#ef4444',
                ];
                $color = $statusColors[$item['status']] ?? '#22c55e';
            ?>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-xl flex items-center justify-center"
                              style="background: <?= e($item['category_color']) ?>1a;">
                            <i data-lucide="<?= e($item['category_icon']) ?>" class="w-3.5 h-3.5"
                               style="color: <?= e($item['category_color']) ?>;"></i>
                        </span>
                        <span class="text-sm font-medium"><?= e($item['category_name']) ?></span>

                        <?php if ($item['status'] === 'danger'): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-500/15 text-red-400">Melebihi</span>
                        <?php elseif ($item['status'] === 'warning'): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-400">Hampir Habis</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-bold" style="color: <?= $color ?>;"><?= $item['percentage'] ?>%</span>
                        <p class="text-xs" style="color: var(--text-muted);">
                            <?= $item['formatted_spent'] ?> / <?= $item['formatted_budget'] ?>
                        </p>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= min(100, $item['percentage']) ?>%; background: <?= $color ?>;"></div>
                </div>
                <?php if ($item['remaining'] < 0): ?>
                    <p class="text-xs mt-1 text-red-400">Melebihi anggaran sebesar <?= formatRupiah(abs($item['remaining'])) ?></p>
                <?php else: ?>
                    <p class="text-xs mt-1" style="color: var(--text-muted);">Sisa <?= $item['formatted_spent'] === 'Rp 0' ? $item['formatted_budget'] : formatRupiah($item['remaining']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <i data-lucide="pie-chart" class="w-12 h-12 mb-3" style="color: var(--text-muted); opacity: 0.3;"></i>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Belum ada anggaran untuk periode ini.</p>
                <p style="color: var(--text-muted); font-size: 0.8rem;">Atur anggaran di panel sebelah kanan.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Form Set Budget -->
    <div class="card p-6">
        <h2 class="font-semibold mb-5">Atur Anggaran</h2>
        <p class="text-xs mb-4" style="color: var(--text-muted);">
            Kosongkan atau isi 0 untuk tidak menetapkan anggaran pada kategori tersebut.
        </p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="save_budget">

            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                <?php foreach ($expCats as $cat):
                    // Cari nilai budget yang sudah ada untuk kategori ini
                    $existingBudget = 0;
                    foreach ($budgetData as $bd) {
                        if ($bd['category_name'] === $cat['name']) {
                            $existingBudget = $bd['budget_amount'];
                            break;
                        }
                    }
                ?>
                <div class="flex items-center gap-3">
                    <input type="hidden" name="category_ids[]" value="<?= $cat['id'] ?>">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <span class="w-7 h-7 rounded-xl flex items-center justify-center flex-shrink-0"
                              style="background: <?= e($cat['color']) ?>1a;">
                            <i data-lucide="<?= e($cat['icon']) ?>" class="w-3.5 h-3.5"
                               style="color: <?= e($cat['color']) ?>;"></i>
                        </span>
                        <label class="text-sm truncate"><?= e($cat['name']) ?></label>
                    </div>
                    <input type="number" name="budgets[]" min="0" step="1000"
                           value="<?= $existingBudget > 0 ? (int)$existingBudget : '' ?>"
                           class="input-dark text-right" style="width: 130px; padding: 0.5rem 0.75rem;"
                           placeholder="0">
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-primary w-full mt-5">
                <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>Simpan Anggaran
            </button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/assets/partials/footer.php'; ?>
