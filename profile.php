<?php
/**
 * FinTrack - Halaman Profil Pengguna
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;
use FinTrack\Auth\User;

$auth      = new AuthService();
$auth->requireLogin();
$userId    = $auth->getCurrentUserId();
$userModel = new User();

// Proses POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('Token tidak valid', 'error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $updated = $userModel->updateProfile($userId, [
                'name'           => trim($_POST['name'] ?? ''),
                'monthly_budget' => (float)($_POST['monthly_budget'] ?? 0),
            ]);

            if ($updated) {
                $_SESSION['user_name'] = trim($_POST['name'] ?? '');
                setFlash('Profil berhasil diperbarui!', 'success');
            } else {
                setFlash('Gagal memperbarui profil', 'error');
            }
            redirect('profile.php');
        }

        if ($action === 'change_password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass     = $_POST['new_password']     ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            // Verifikasi password lama
            $userData = null;
            // Ambil user dengan password untuk verifikasi
            try {
                $pdo  = \FinTrack\Config\Database::getInstance()->getConnection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch();
            } catch (\Exception $e) {}

            if (!$userData || !password_verify($currentPass, $userData['password'])) {
                setFlash('Password lama tidak sesuai', 'error');
            } elseif (strlen($newPass) < 8) {
                setFlash('Password baru minimal 8 karakter', 'error');
            } elseif ($newPass !== $confirmPass) {
                setFlash('Konfirmasi password tidak cocok', 'error');
            } else {
                $userModel->changePassword($userId, $newPass);
                setFlash('Password berhasil diubah!', 'success');
            }
            redirect('profile.php');
        }
    }
}

$user = $auth->getCurrentUser();

$pageTitle  = 'Profil';
$activePage = 'profile';
require_once __DIR__ . '/assets/partials/header.php';
?>

<div class="mb-7">
    <h1 class="text-2xl font-bold">Profil Saya</h1>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Kelola informasi akun Anda</p>
</div>

<?= flashMessage('success') ?>
<?= flashMessage('error') ?>

<div class="grid-budget">

    <!-- Sidebar Info -->
    <div class="">
        <div class="card p-6 text-center mb-4">
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl font-bold"
                 style="background: rgba(34,197,94,0.15); color: #22c55e;">
                <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
            </div>
            <h2 class="font-bold text-lg"><?= e($user['name'] ?? '') ?></h2>
            <p class="text-sm mt-1" style="color: var(--text-muted);"><?= e($user['email'] ?? '') ?></p>
            <span class="inline-block mt-2 px-3 py-1 rounded-full text-xs font-semibold"
                  style="background: rgba(34,197,94,0.15); color: #22c55e;">
                <?= ucfirst($user['role'] ?? 'user') ?>
            </span>
        </div>

        <div class="card p-5">
            <h3 class="text-sm font-semibold mb-3">Info Akun</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-xs" style="color: var(--text-muted);">Bergabung</span>
                    <span class="text-xs font-medium"><?= isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '-' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs" style="color: var(--text-muted);">Anggaran Bulanan</span>
                    <span class="text-xs font-medium text-emerald-400"><?= formatRupiah((float)($user['monthly_budget'] ?? 0)) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs" style="color: var(--text-muted);">Status</span>
                    <span class="text-xs font-medium text-emerald-400">Aktif</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Forms -->
    <div class="space-y-5">

        <!-- Update Profil -->
        <div class="card p-6">
            <h2 class="font-semibold mb-5">Informasi Profil</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="update_profile">

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Nama Lengkap</label>
                    <input type="text" name="name" required class="input-dark"
                           value="<?= e($user['name'] ?? '') ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Email</label>
                    <input type="email" class="input-dark" disabled
                           value="<?= e($user['email'] ?? '') ?>"
                           style="opacity: 0.5; cursor: not-allowed;">
                    <p class="text-xs mt-1" style="color: var(--text-muted);">Email tidak dapat diubah</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Anggaran Bulanan (Rp)</label>
                    <input type="number" name="monthly_budget" min="0" class="input-dark"
                           value="<?= (int)($user['monthly_budget'] ?? 0) ?>">
                    <p class="text-xs mt-1" style="color: var(--text-muted);">Digunakan sebagai batas pengeluaran bulanan Anda</p>
                </div>

                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </form>
        </div>

        <!-- Ganti Password -->
        <div class="card p-6">
            <h2 class="font-semibold mb-5">Ganti Password</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Password Lama</label>
                    <input type="password" name="current_password" required class="input-dark"
                           placeholder="Masukkan password lama">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Password Baru</label>
                    <input type="password" name="new_password" required minlength="8" class="input-dark"
                           placeholder="Minimal 8 karakter"
                           oninput="checkPassStrength(this.value)">
                    <div class="mt-1.5 flex gap-1">
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="ps1"></div>
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="ps2"></div>
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="ps3"></div>
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="ps4"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--text-muted);">Konfirmasi Password Baru</label>
                    <input type="password" name="confirm_password" required class="input-dark"
                           placeholder="Ulangi password baru">
                </div>

                <button type="submit" class="btn-primary">Ubah Password</button>
            </form>
        </div>

    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function checkPassStrength(password) {
    let score = 0;
    if (password.length >= 8)           score++;
    if (/[A-Z]/.test(password))         score++;
    if (/[0-9]/.test(password))         score++;
    if (/[^A-Za-z0-9]/.test(password))  score++;

    const colors = ['bg-red-500','bg-orange-500','bg-yellow-500','bg-emerald-500'];
    ['ps1','ps2','ps3','ps4'].forEach((id, i) => {
        const el = document.getElementById(id);
        el.className = 'h-1 flex-1 rounded-full ' + (i < score ? colors[score-1] : 'bg-white/10');
    });
}
</script>
JS;

require_once __DIR__ . '/assets/partials/footer.php';
?>
