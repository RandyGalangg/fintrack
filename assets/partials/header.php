<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'FinTrack') ?> — FinTrack</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                }
            }
        }
    </script>

    <style>
        :root {
            --bg-primary:   #0a0a0f;
            --bg-secondary: #111118;
            --bg-card:      #16161f;
            --border:       rgba(255,255,255,0.07);
            --text-primary: #f0f0f5;
            --text-muted:   #6b7280;
            --accent:       #22c55e;
            --accent-soft:  rgba(34,197,94,0.12);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
        }

        /* ======== SIDEBAR ======== */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 40;
            transition: transform 0.3s ease;
        }
        .sidebar.hidden-mobile {
            transform: translateX(-100%);
        }

        /* Overlay mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 39;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active { display: block; }

        /* Main content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Mobile topbar */
        .mobile-topbar {
            display: none;
            position: sticky;
            top: 0;
            z-index: 30;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 0.875rem 1rem;
            align-items: center;
            justify-content: space-between;
        }

        /* Bottom navigation mobile */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border);
            z-index: 40;
            padding: 0.5rem 0;
        }
        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
            padding: 0.4rem 0.5rem;
            border-radius: 0.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.6rem;
            font-weight: 500;
            transition: all 0.15s;
            flex: 1;
        }
        .bottom-nav-item.active { color: var(--accent); }
        .bottom-nav-item i { width: 1.25rem; height: 1.25rem; }

        /* Responsive breakpoints */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .mobile-topbar { display: flex; }
            .bottom-nav { display: flex; }
            .main-content { padding-bottom: 70px; }
        }
        @media (min-width: 1025px) {
            .sidebar { transform: translateX(0) !important; }
            .main-content { margin-left: 260px; }
            .mobile-topbar { display: none !important; }
            .bottom-nav { display: none !important; }
            .sidebar-overlay { display: none !important; }
        }

        /* Sidebar link */
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-muted);
            transition: all 0.15s;
            text-decoration: none;
        }
        .sidebar-link:hover { color: var(--text-primary); background: rgba(255,255,255,0.05); }
        .sidebar-link.active { color: var(--accent); background: var(--accent-soft); }

        /* Inputs */
        .input-dark {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: 0.75rem;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            width: 100%;
            transition: all 0.2s;
        }
        .input-dark:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--accent-soft);
            box-shadow: 0 0 0 3px rgba(34,197,94,0.1);
        }
        .input-dark option { background: #1a1a24; }

        /* Badges */
        .badge-income  { background: rgba(34,197,94,0.12); color: #4ade80; }
        .badge-expense { background: rgba(239,68,68,0.12); color: #f87171; }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white; font-weight: 600;
            border-radius: 0.75rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            border: none; cursor: pointer;
            transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-danger {
            background: rgba(239,68,68,0.15); color: #f87171;
            border: 1px solid rgba(239,68,68,0.2);
            font-weight: 600; border-radius: 0.75rem;
            padding: 0.5rem 1rem; font-size: 0.8rem;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }
        .btn-ghost {
            background: rgba(255,255,255,0.05); color: var(--text-muted);
            border: 1px solid var(--border); border-radius: 0.75rem;
            padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 500;
            cursor: pointer; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .btn-ghost:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* Stat & progress */
        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 1.25rem; padding: 1.5rem; transition: all 0.2s;
        }
        .stat-card:hover { border-color: rgba(255,255,255,0.12); transform: translateY(-2px); }
        .progress-bar { height: 6px; background: rgba(255,255,255,0.07); border-radius: 99px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 99px; transition: width 0.6s ease; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

        /* Modal */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 50; display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #1a1a24;
            border: 1px solid var(--border);
            border-radius: 1.5rem 1.5rem 0 0;
            padding: 1.5rem;
            width: 100%; max-width: 100%;
            max-height: 92vh; overflow-y: auto;
        }
        @media (min-width: 640px) {
            .modal-overlay { align-items: center; padding: 1rem; }
            .modal-box { border-radius: 1.5rem; max-width: 480px; }
        }

        /* Responsive grids */
        .grid-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
        @media (min-width: 1024px) { .grid-stats { grid-template-columns: repeat(4, 1fr); gap: 1.25rem; } }

        .grid-2col { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 768px)  { .grid-2col { grid-template-columns: repeat(2, 1fr); } }

        .grid-3col { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px)  { .grid-3col { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .grid-3col { grid-template-columns: repeat(3, 1fr); } }

        .grid-chart { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1024px) { .grid-chart { grid-template-columns: 2fr 1fr; } }

        .grid-bottom { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1024px) { .grid-bottom { grid-template-columns: 3fr 2fr; } }

        .grid-budget { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1024px) { .grid-budget { grid-template-columns: 3fr 2fr; } }

        /* Table responsive */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        /* Page padding */
        .page-content { padding: 1.25rem; }
        @media (min-width: 768px) { .page-content { padding: 2rem; } }
    </style>
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ===================== SIDEBAR ===================== -->
<aside class="sidebar hidden-mobile" id="sidebar">
    <!-- Logo -->
    <div class="p-5 border-b" style="border-color: var(--border);">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: var(--accent-soft);">
                    <i data-lucide="wallet" class="w-5 h-5" style="color: var(--accent);"></i>
                </div>
                <div>
                    <div class="font-bold text-base">FinTrack</div>
                    <div class="text-xs" style="color: var(--text-muted);">Keuangan Pribadi</div>
                </div>
            </div>
            <!-- Close button (mobile) -->
            <button onclick="closeSidebar()" class="lg:hidden" style="color: var(--text-muted);">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <a href="dashboard.php" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>" onclick="closeSidebar()">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
        </a>
        <a href="transactions.php" class="sidebar-link <?= ($activePage ?? '') === 'transactions' ? 'active' : '' ?>" onclick="closeSidebar()">
            <i data-lucide="arrow-left-right" class="w-4 h-4"></i> Transaksi
        </a>
        <a href="budget.php" class="sidebar-link <?= ($activePage ?? '') === 'budget' ? 'active' : '' ?>" onclick="closeSidebar()">
            <i data-lucide="pie-chart" class="w-4 h-4"></i> Anggaran
        </a>
        <a href="savings.php" class="sidebar-link <?= ($activePage ?? '') === 'savings' ? 'active' : '' ?>" onclick="closeSidebar()">
            <i data-lucide="piggy-bank" class="w-4 h-4"></i> Tabungan
        </a>
        <a href="categories.php" class="sidebar-link <?= ($activePage ?? '') === 'categories' ? 'active' : '' ?>" onclick="closeSidebar()">
            <i data-lucide="tag" class="w-4 h-4"></i> Kategori
        </a>
        <a href="reports.php" class="sidebar-link <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>" onclick="closeSidebar()">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Laporan
        </a>
        <div class="pt-4 pb-2">
            <p class="px-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Akun</p>
        </div>
        <a href="profile.php" class="sidebar-link <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>" onclick="closeSidebar()">
            <i data-lucide="user" class="w-4 h-4"></i> Profil
        </a>
    </nav>

    <!-- User Info -->
    <div class="p-4 border-t" style="border-color: var(--border);">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                 style="background: var(--accent-soft); color: var(--accent);">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= e($_SESSION['user_name'] ?? '') ?></div>
                <div class="text-xs truncate" style="color: var(--text-muted);"><?= ucfirst($_SESSION['user_role'] ?? 'user') ?></div>
            </div>
        </div>
        <a href="../lsp/logout.php" class="sidebar-link text-red-400" style="background: rgba(239,68,68,0.05);">
            <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
        </a>
    </div>
</aside>

<!-- ===================== MOBILE TOPBAR ===================== -->
<div class="mobile-topbar">
    <div class="flex items-center gap-3">
        <button onclick="openSidebar()" style="color: var(--text-muted);">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: var(--accent-soft);">
                <i data-lucide="wallet" class="w-4 h-4" style="color: var(--accent);"></i>
            </div>
            <span class="font-bold text-sm">Fin<span style="color: var(--accent);">Track</span></span>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-sm font-medium" style="color: var(--text-muted);"><?= e($pageTitle ?? '') ?></span>
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
             style="background: var(--accent-soft); color: var(--accent);">
            <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
        </div>
    </div>
</div>

<!-- ===================== MAIN CONTENT ===================== -->
<div class="main-content">
    <div class="page-content">

<!-- ===================== BOTTOM NAV (mobile) ===================== -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i data-lucide="layout-dashboard"></i><span>Home</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item <?= ($activePage ?? '') === 'transactions' ? 'active' : '' ?>">
        <i data-lucide="arrow-left-right"></i><span>Transaksi</span>
    </a>
    <a href="budget.php" class="bottom-nav-item <?= ($activePage ?? '') === 'budget' ? 'active' : '' ?>">
        <i data-lucide="pie-chart"></i><span>Anggaran</span>
    </a>
    <a href="savings.php" class="bottom-nav-item <?= ($activePage ?? '') === 'savings' ? 'active' : '' ?>">
        <i data-lucide="piggy-bank"></i><span>Tabungan</span>
    </a>
    <a href="profile.php" class="bottom-nav-item <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
        <i data-lucide="user"></i><span>Profil</span>
    </a>
</nav>
