<?php
/**
 * FinTrack - Halaman Tujuan Tabungan
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;
use FinTrack\Finance\SavingsGoal;

$auth = new AuthService();
$auth->requireLogin();
$userId = $auth->getCurrentUserId();

$savingsModel = new SavingsGoal();

// Proses POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid', 'error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            try {
                $savingsModel->create([
                    'user_id'        => $userId,
                    'title'          => trim($_POST['title'] ?? ''),
                    'target_amount'  => (float)$_POST['target_amount'],
                    'current_amount' => (float)($_POST['current_amount'] ?? 0),
                    'deadline'       => !empty($_POST['deadline']) ? $_POST['deadline'] : null,
                    'icon'           => $_POST['icon']  ?? 'piggy-bank',
                    'color'          => $_POST['color'] ?? '#10b981',
                    'status'         => 'active',
                ]);
                setFlash('Tujuan tabungan berhasil dibuat!', 'success');
            } catch (\Exception $e) {
                setFlash($e->getMessage(), 'error');
            }
            redirect('savings.php');
        }

        if ($action === 'add_fund') {
            $id     = (int)$_POST['goal_id'];
            $amount = (float)$_POST['add_amount'];
            if ($savingsModel->addFund($id, $amount)) {
                setFlash('Dana berhasil ditambahkan!', 'success');
            } else {
                setFlash('Gagal menambahkan dana', 'error');
            }
            redirect('savings.php');
        }

        if ($action === 'delete') {
            $id   = (int)$_POST['id'];
            $goal = $savingsModel->findById($id);
            if ($goal && (int)$goal['user_id'] === $userId) {
                $savingsModel->delete($id);
                setFlash('Tujuan tabungan dihapus', 'success');
            }
            redirect('savings.php');
        }
    }
}

$activeGoals    = $savingsModel->getByUser($userId, 'active');
$completedGoals = $savingsModel->getByUser($userId, 'completed');

// Array warna yang tersedia
$colors = ['#10b981','#6366f1','#f59e0b','#3b82f6','#ec4899','#ef4444','#14b8a6'];
$icons  = ['piggy-bank','home','car','plane','laptop','graduation-cap','heart','gift','star','zap'];

$pageTitle  = 'Tabungan';
$activePage = 'savings';
require_once __DIR__ . '/assets/partials/header.php';
?>

<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold">Tujuan Tabungan</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            <?= count($activeGoals) ?> aktif · <?= count($completedGoals) ?> selesai
        </p>
    </div>
    <button onclick="openModal('savingsModal')" class="btn-primary">
        <i data-lucide="plus" class="w-4 h-4 inline mr-1"></i> Tujuan Baru
    </button>
</div>

<?= flashMessage('success') ?>
<?= flashMessage('error') ?>

<!-- Active Goals Grid -->
<?php if (!empty($activeGoals)): ?>
<h2 class="font-semibold mb-4 text-sm uppercase tracking-wider" style="color: var(--text-muted);">Sedang Berjalan</h2>
<div class="grid-3col mb-6">
    <?php foreach ($activeGoals as $goal): ?>
    <div class="card p-5">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                     style="background: <?= e($goal['color']) ?>1a;">
                    <i data-lucide="<?= e($goal['icon']) ?>" class="w-5 h-5" style="color: <?= e($goal['color']) ?>;"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-sm"><?= e($goal['title']) ?></h3>
                    <?php if ($goal['days_left'] !== null): ?>
                        <p class="text-xs <?= $goal['days_left'] < 30 ? 'text-amber-400' : '' ?>" style="color: var(--text-muted);">
                            <?= $goal['days_left'] >= 0 ? $goal['days_left'].' hari lagi' : 'Sudah lewat' ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" onsubmit="return confirm('Hapus tujuan ini?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $goal['id'] ?>">
                <button type="submit" style="color: var(--text-muted);" class="hover:text-red-400 transition-colors">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
        </div>

        <div class="mb-3">
            <div class="flex justify-between text-sm mb-1.5">
                <span style="color: var(--text-muted);">Progress</span>
                <span class="font-bold" style="color: <?= e($goal['color']) ?>;"><?= $goal['percentage'] ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min(100, $goal['percentage']) ?>%; background: <?= e($goal['color']) ?>;"></div>
            </div>
        </div>

        <div class="flex justify-between text-xs mb-4" style="color: var(--text-muted);">
            <span><?= formatRupiah($goal['current_amount']) ?></span>
            <span><?= formatRupiah($goal['target_amount']) ?></span>
        </div>

        <!-- Form tambah dana -->
        <form method="POST" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add_fund">
            <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
            <input type="number" name="add_amount" min="1" class="input-dark flex-1" style="padding: 0.4rem 0.6rem; font-size: 0.8rem;" placeholder="Tambah dana">
            <button type="submit" class="btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; white-space: nowrap;">
                + Dana
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Completed Goals -->
<?php if (!empty($completedGoals)): ?>
<h2 class="font-semibold mb-4 text-sm uppercase tracking-wider" style="color: var(--text-muted);">Selesai 🎉</h2>
<div class="grid-stats">
    <?php foreach ($completedGoals as $goal): ?>
    <div class="card p-4" style="opacity: 0.7;">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-emerald-500/15">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400"></i>
            </div>
            <div>
                <h3 class="font-medium text-sm"><?= e($goal['title']) ?></h3>
                <p class="text-xs text-emerald-400"><?= formatRupiah($goal['target_amount']) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($activeGoals) && empty($completedGoals)): ?>
<div class="flex flex-col items-center justify-center py-24 text-center">
    <i data-lucide="piggy-bank" class="w-16 h-16 mb-4" style="color: var(--text-muted); opacity: 0.2;"></i>
    <p class="font-semibold">Belum ada tujuan tabungan</p>
    <p class="text-sm mt-1" style="color: var(--text-muted);">Buat tujuan pertama Anda!</p>
    <button onclick="openModal('savingsModal')" class="btn-primary mt-4">Buat Tujuan</button>
</div>
<?php endif; ?>

<!-- Modal: Tambah Tujuan -->
<div class="modal-overlay" id="savingsModal">
    <div class="modal-box">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold">Tujuan Tabungan Baru</h2>
            <button onclick="closeModal('savingsModal')" style="color: var(--text-muted);">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Nama Tujuan</label>
                <input type="text" name="title" required class="input-dark" placeholder="Contoh: Dana Darurat">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Target (Rp)</label>
                    <input type="number" name="target_amount" required min="1" class="input-dark" placeholder="0">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Dana Awal (Rp)</label>
                    <input type="number" name="current_amount" min="0" class="input-dark" placeholder="0">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Target Tanggal</label>
                <input type="date" name="deadline" class="input-dark">
            </div>

            <!-- Pilih Ikon -->
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-muted);">Ikon</label>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($icons as $i => $icon): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="icon" value="<?= $icon ?>" class="sr-only" <?= $i === 0 ? 'checked' : '' ?>>
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center border transition-all icon-opt"
                              style="border-color: var(--border); background: rgba(255,255,255,0.03);">
                            <i data-lucide="<?= $icon ?>" class="w-4 h-4" style="color: var(--text-muted);"></i>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pilih Warna -->
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-muted);">Warna</label>
                <div class="flex gap-2">
                    <?php foreach ($colors as $i => $color): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="color" value="<?= $color ?>" class="sr-only" <?= $i === 0 ? 'checked' : '' ?>>
                        <span class="w-7 h-7 rounded-full block border-2 transition-all"
                              style="background: <?= $color ?>; border-color: transparent;"></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('savingsModal')" class="btn-ghost flex-1" style="justify-content: center;">Batal</button>
                <button type="submit" class="btn-primary flex-1">Buat Tujuan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/assets/partials/footer.php'; ?>
