<?php
/**
 * FinTrack - Halaman Login & Registrasi
 *
 * @package FinTrack
 */

require_once __DIR__ . '/bootstrap.php';

use FinTrack\Auth\AuthService;

$auth = new AuthService();

// Redirect jika sudah login
if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
}

$error   = '';
$success = '';
$mode    = $_GET['mode'] ?? 'login'; // 'login' atau 'register'

// ============================================================
// Proses POST Request
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan coba lagi.';
    } elseif ($mode === 'login') {
        // ---- Proses Login ----
        $user = $auth->login(
            trim($_POST['email']    ?? ''),
            trim($_POST['password'] ?? '')
        );

        if ($user) {
            setFlash("Selamat datang, {$user['name']}! 👋", 'success');
            redirect('dashboard.php');
        } else {
            $errors = $auth->getErrors();
            $error  = !empty($errors) ? $errors[0] : 'Login gagal';
        }
    } elseif ($mode === 'register') {
        // ---- Proses Registrasi ----
        try {
            $userId = $auth->register([
                'name'             => $_POST['name']             ?? '',
                'email'            => $_POST['email']            ?? '',
                'password'         => $_POST['password']         ?? '',
                'password_confirm' => $_POST['password_confirm'] ?? '',
                'monthly_budget'   => (float)($_POST['monthly_budget'] ?? 0),
            ]);

            if ($userId) {
                $success = 'Akun berhasil dibuat! Silakan login.';
                $mode    = 'login';
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinTrack — Masuk</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        brand: {
                            50:  '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0',
                            300: '#86efac', 400: '#4ade80', 500: '#22c55e',
                            600: '#16a34a', 700: '#15803d', 800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .input-field {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            transition: all 0.2s;
        }
        .input-field:focus {
            outline: none;
            border-color: #22c55e;
            background: rgba(34,197,94,0.05);
            box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
        }
        .input-field::placeholder { color: rgba(255,255,255,0.3); }
        .btn-primary {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            transition: all 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 30px rgba(34,197,94,0.3);
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .floating { animation: float 6s ease-in-out infinite; }
    </style>
</head>
<body class="h-full bg-gray-950 text-white overflow-hidden">

<!-- Background Orbs -->
<div class="fixed inset-0 overflow-hidden pointer-events-none">
    <div class="orb w-96 h-96 bg-emerald-500 top-0 left-0 floating"></div>
    <div class="orb w-80 h-80 bg-teal-400 bottom-0 right-0" style="animation: float 8s ease-in-out infinite reverse;"></div>
    <div class="orb w-64 h-64 bg-green-600 top-1/2 left-1/2" style="animation: float 10s ease-in-out infinite;"></div>
</div>

<!-- Grid Pattern -->
<div class="fixed inset-0 opacity-5" style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 40px 40px;"></div>

<div class="relative h-full flex items-center justify-center p-4">
    <div class="w-full max-w-md">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 mb-4">
                <i data-lucide="wallet" class="w-8 h-8 text-emerald-400"></i>
            </div>
            <h1 class="text-3xl font-bold">
                <span class="text-white">Fin</span><span class="text-emerald-400">Track</span>
            </h1>
            <p class="text-gray-400 text-sm mt-1">Sistem Manajemen Keuangan Pribadi</p>
        </div>

        <!-- Card -->
        <div class="glass rounded-3xl p-8">

            <!-- Tab Switcher -->
            <div class="flex bg-white/5 rounded-2xl p-1 mb-8">
                <a href="?mode=login"
                   class="flex-1 py-2.5 text-center text-sm font-semibold rounded-xl transition-all <?= $mode === 'login' ? 'bg-emerald-500 text-white shadow-lg' : 'text-gray-400 hover:text-white' ?>">
                    Masuk
                </a>
                <a href="?mode=register"
                   class="flex-1 py-2.5 text-center text-sm font-semibold rounded-xl transition-all <?= $mode === 'register' ? 'bg-emerald-500 text-white shadow-lg' : 'text-gray-400 hover:text-white' ?>">
                    Daftar
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="flex items-center gap-3 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 mb-6">
                    <i data-lucide="x-circle" class="w-5 h-5 flex-shrink-0"></i>
                    <span class="text-sm"><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 mb-6">
                    <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
                    <span class="text-sm"><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <?php if ($mode === 'login'): ?>
            <form method="POST" action="?mode=login" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <div class="relative">
                        <i data-lucide="mail" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500"></i>
                        <input type="email" name="email" required
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               placeholder="email@contoh.com"
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500"></i>
                        <input type="password" name="password" id="loginPass" required
                               class="input-field w-full pl-10 pr-12 py-3 rounded-xl text-sm"
                               placeholder="Masukkan password">
                        <button type="button" onclick="togglePass('loginPass','eyeLogin')"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                            <i data-lucide="eye" id="eyeLogin" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full py-3.5 rounded-xl font-semibold text-sm text-white">
                    Masuk ke FinTrack
                </button>
            </form>

            <div class="mt-6 p-4 rounded-xl bg-white/5 border border-white/10">
                <p class="text-xs text-gray-500 font-medium mb-2">Demo Akun:</p>
                <p class="text-xs text-gray-400">📧 demo@fintrack.com</p>
                <p class="text-xs text-gray-400">🔑 password</p>
            </div>

            <!-- Register Form -->
            <?php else: ?>
            <form method="POST" action="?mode=register" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nama Lengkap</label>
                    <div class="relative">
                        <i data-lucide="user" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500"></i>
                        <input type="text" name="name" required minlength="3"
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               placeholder="Nama Anda"
                               value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <div class="relative">
                        <i data-lucide="mail" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500"></i>
                        <input type="email" name="email" required
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               placeholder="email@contoh.com"
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Anggaran Bulanan (Rp)</label>
                    <div class="relative">
                        <i data-lucide="wallet" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500"></i>
                        <input type="number" name="monthly_budget" min="0"
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               placeholder="5000000"
                               value="<?= e($_POST['monthly_budget'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500"></i>
                        <input type="password" name="password" id="regPass" required minlength="8"
                               class="input-field w-full pl-10 pr-12 py-3 rounded-xl text-sm"
                               placeholder="Minimal 8 karakter"
                               oninput="checkStrength(this.value)">
                        <button type="button" onclick="togglePass('regPass','eyeReg')"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                            <i data-lucide="eye" id="eyeReg" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <!-- Password strength indicator -->
                    <div class="mt-2 flex gap-1">
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="str1"></div>
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="str2"></div>
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="str3"></div>
                        <div class="h-1 flex-1 rounded-full bg-white/10" id="str4"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1" id="strLabel">Masukkan password</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Konfirmasi Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500"></i>
                        <input type="password" name="password_confirm" required
                               class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                               placeholder="Ulangi password">
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full py-3.5 rounded-xl font-semibold text-sm text-white mt-2">
                    Buat Akun
                </button>
            </form>
            <?php endif; ?>

        </div>

        <p class="text-center text-xs text-gray-600 mt-6">
            &copy; <?= date('Y') ?> FinTrack. Dibuat dengan ❤️ untuk tugas OOP.
        </p>
    </div>
</div>

<script>
    lucide.createIcons();

    function togglePass(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }

    function checkStrength(password) {
        let score = 0;
        if (password.length >= 8)            score++;
        if (/[A-Z]/.test(password))          score++;
        if (/[0-9]/.test(password))          score++;
        if (/[^A-Za-z0-9]/.test(password))   score++;

        const colors  = ['bg-red-500','bg-orange-500','bg-yellow-500','bg-emerald-500'];
        const labels  = ['Sangat Lemah','Lemah','Sedang','Kuat'];
        const strEls  = ['str1','str2','str3','str4'];

        strEls.forEach((id, i) => {
            const el = document.getElementById(id);
            el.className = 'h-1 flex-1 rounded-full ' +
                (i < score ? colors[score - 1] : 'bg-white/10');
        });

        const label = document.getElementById('strLabel');
        label.textContent = score > 0 ? 'Kekuatan: ' + labels[score - 1] : 'Masukkan password';
        label.className   = 'text-xs mt-1 ' +
            (score >= 3 ? 'text-emerald-400' : score >= 2 ? 'text-yellow-400' : 'text-red-400');
    }
</script>
</body>
</html>
