<?php
/**
 * FinTrack - Halaman Manajemen Kategori
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;
use FinTrack\Finance\Category;

$auth = new AuthService();
$auth->requireLogin();
$userId = $auth->getCurrentUserId();

$catModel = new Category();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid', 'error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            try {
                $catModel->create([
                    'user_id' => $userId,
                    'name'    => trim($_POST['name'] ?? ''),
                    'type'    => $_POST['type'] ?? 'expense',
                    'icon'    => $_POST['icon']  ?? 'tag',
                    'color'   => $_POST['color'] ?? '#6366f1',
                ]);
                setFlash('Kategori berhasil ditambahkan!', 'success');
            } catch (\Exception $e) {
                setFlash($e->getMessage(), 'error');
            }
            redirect('categories.php');
        }

        if ($action === 'delete') {
            $id  = (int)$_POST['id'];
            $cat = $catModel->findById($id);

            if ($cat && (int)$cat['user_id'] === $userId) {
                if ($catModel->hasTransactions($id)) {
                    setFlash('Kategori tidak dapat dihapus karena memiliki transaksi terkait', 'error');
                } else {
                    $catModel->delete($id);
                    setFlash('Kategori dihapus', 'success');
                }
            }
            redirect('categories.php');
        }
    }
}

$grouped = $catModel->getGroupedByType($userId);

$pageTitle  = 'Kategori';
$activePage = 'categories';
require_once __DIR__ . '/assets/partials/header.php';
?>

<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold">Kategori</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Kelola kategori transaksi Anda
        </p>
    </div>
    <button onclick="openModal('catModal')" class="btn-primary">
        <i data-lucide="plus" class="w-4 h-4 inline mr-1"></i> Tambah Kategori
    </button>
</div>

<?= flashMessage('success') ?>
<?= flashMessage('error') ?>

<div class="grid-2col">

    <?php foreach (['expense' => 'Pengeluaran', 'income' => 'Pemasukan'] as $type => $label): ?>
    <div class="card p-6">
        <div class="flex items-center gap-2 mb-5">
            <span class="w-2 h-2 rounded-full <?= $type === 'expense' ? 'bg-red-500' : 'bg-emerald-500' ?>"></span>
            <h2 class="font-semibold"><?= $label ?></h2>
            <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(255,255,255,0.07); color: var(--text-muted);">
                <?= count($grouped[$type]) ?> kategori
            </span>
        </div>

        <div class="space-y-2">
            <?php if (!empty($grouped[$type])): ?>
                <?php foreach ($grouped[$type] as $cat): ?>
                <div class="flex items-center justify-between p-3 rounded-xl" style="background: rgba(255,255,255,0.02);">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                             style="background: <?= e($cat['color']) ?>1a;">
                            <i data-lucide="<?= e($cat['icon']) ?>" class="w-4 h-4"
                               style="color: <?= e($cat['color']) ?>;"></i>
                        </div>
                        <span class="text-sm font-medium"><?= e($cat['name']) ?></span>
                    </div>

                    <form method="POST" onsubmit="return confirm('Hapus kategori ini?')">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="hover:text-red-400 transition-colors" style="color: var(--text-muted);">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-sm text-center py-4" style="color: var(--text-muted);">Belum ada kategori</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- Modal: Tambah Kategori -->
<div class="modal-overlay" id="catModal">
    <div class="modal-box" style="max-width: 420px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold">Tambah Kategori</h2>
            <button onclick="closeModal('catModal')" style="color: var(--text-muted);">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Nama Kategori</label>
                <input type="text" name="name" required class="input-dark" placeholder="Contoh: Transportasi">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Tipe</label>
                <select name="type" class="input-dark">
                    <option value="expense">Pengeluaran</option>
                    <option value="income">Pemasukan</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Ikon</label>
                <select name="icon" class="input-dark">
                    <?php foreach (Category::$availableIcons as $iconKey => $iconLabel): ?>
                        <option value="<?= $iconKey ?>"><?= $iconLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Warna</label>
                <input type="color" name="color" value="#6366f1" class="input-dark" style="height: 42px; padding: 0.25rem;">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('catModal')" class="btn-ghost flex-1" style="justify-content: center;">Batal</button>
                <button type="submit" class="btn-primary flex-1">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/assets/partials/footer.php'; ?>
