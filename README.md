# FinTrack — Sistem Manajemen Keuangan Pribadi

> **Versi:** 1.0.0 | **Bahasa:** PHP 8.1+ | **Database:** MySQL | **Frontend:** HTML, CSS, JS, Tailwind CSS

---

## 📋 Daftar Isi
1. [Gambaran Proyek](#gambaran-proyek)
2. [Use Case Diagram](#use-case-diagram)
3. [Data Flow Diagram (DFD)](#data-flow-diagram)
4. [Struktur Direktori](#struktur-direktori)
5. [Persyaratan Sistem](#persyaratan-sistem)
6. [Panduan Instalasi](#panduan-instalasi)
7. [Fitur Aplikasi](#fitur-aplikasi)
8. [Arsitektur & Desain OOP](#arsitektur--desain-oop)
9. [Pemenuhan Brief Requirements](#pemenuhan-brief-requirements)
10. [External Libraries](#external-libraries)
11. [Database Schema](#database-schema)
12. [Akun Demo](#akun-demo)

---

## Gambaran Proyek

**FinTrack** adalah aplikasi web manajemen keuangan pribadi yang memungkinkan pengguna untuk:
- Mencatat dan memantau transaksi pemasukan dan pengeluaran
- Menetapkan anggaran per kategori per bulan
- Mengelola tujuan tabungan
- Menganalisis pola keuangan melalui grafik dan laporan
- Mengekspor data transaksi ke format CSV

---

## Use Case Diagram

```
+--------------------------------------------------+
|              SISTEM FINTRACK                     |
|                                                  |
|  +----------+    +----------------------------+ |
|  |          |    | UC-01: Login/Register      | |
|  |          |--->| UC-02: Kelola Transaksi    | |
|  |  USER    |    | UC-03: Kelola Anggaran     | |
|  |          |--->| UC-04: Kelola Tabungan     | |
|  |          |    | UC-05: Kelola Kategori     | |
|  |          |--->| UC-06: Lihat Laporan       | |
|  |          |    | UC-07: Ekspor CSV          | |
|  |          |--->| UC-08: Update Profil       | |
|  +----------+    +----------------------------+ |
|                                                  |
|  +----------+    +----------------------------+ |
|  |          |    | UC-09: Kelola Semua User   | |
|  |  ADMIN   |--->| UC-10: Lihat Audit Log     | |
|  +----------+    +----------------------------+ |
+--------------------------------------------------+
```

---

## Data Flow Diagram (DFD)

### Level 0 (Context Diagram)
```
          Input Data         +---------------+    Laporan
USER ---------------------->|               |-----------> USER
     <----------------------|   FINTRACK    |
          Informasi         |    SYSTEM     |
                            +---------------+
                                   |
                              +----+----+
                              | MySQL   |
                              | Database|
                              +---------+
```

### Level 1 (DFD)
```
              1.0 Autentikasi
USER -------> [Login/Register] <----> [USERS Table]
                    |
                    v
              2.0 Transaksi
USER -------> [Kelola Transaksi] <---> [TRANSACTIONS Table]
                    |                  [CATEGORIES Table]
                    v
              3.0 Anggaran
USER -------> [Kelola Anggaran] <----> [BUDGETS Table]
                    |
                    v
              4.0 Tabungan
USER -------> [Kelola Tabungan] <----> [SAVINGS_GOALS Table]
                    |
                    v
              5.0 Laporan
USER <------- [Generate Laporan] <---> [Semua Table]
```

---

## Struktur Direktori

```
fintrack/
│
├── index.php              # Halaman login & registrasi
├── dashboard.php          # Dashboard utama
├── transactions.php       # Manajemen transaksi
├── budget.php             # Manajemen anggaran
├── savings.php            # Tujuan tabungan
├── categories.php         # Manajemen kategori
├── reports.php            # Laporan keuangan
├── profile.php            # Profil pengguna
├── logout.php             # Proses logout
├── bootstrap.php          # Bootstrap & autoloader
│
├── auth/                  # Namespace: FinTrack\Auth
│   └── AuthService.php    # AuthService, User, AuthInterface
│
├── finance/               # Namespace: FinTrack\Finance
│   ├── BaseModel.php      # Abstract BaseModel, Auditable, Exportable
│   ├── Transaction.php    # Transaction, Income, Expense
│   └── Category.php       # Category, Budget, SavingsGoal
│
├── config/                # Namespace: FinTrack\Config
│   ├── Database.php       # Database singleton
│   └── schema.sql         # SQL schema & seed data
│
├── assets/
│   ├── css/               # Custom CSS (opsional)
│   ├── js/                # Custom JavaScript (opsional)
│   └── partials/
│       ├── header.php     # Template header & sidebar
│       └── footer.php     # Template footer & scripts
│
├── exports/               # File ekspor CSV (temporary)
├── logs/                  # Error logs
└── docs/                  # Dokumentasi tambahan
```

---

## Persyaratan Sistem

| Komponen | Minimum | Rekomendasi |
|----------|---------|-------------|
| PHP | 8.1 | 8.2+ |
| MySQL | 5.7 | 8.0+ |
| Web Server | Apache/Nginx | Apache 2.4+ |
| Browser | Modern (ES6+) | Chrome/Firefox terbaru |

**PHP Extensions yang dibutuhkan:**
- `pdo_mysql`
- `mbstring`
- `json`
- `session`

---

## Panduan Instalasi

### 1. Clone / Salin Proyek
```bash
# Salin folder fintrack ke web root
cp -r fintrack/ /var/www/html/
# atau untuk XAMPP:
cp -r fintrack/ C:/xampp/htdocs/
```

### 2. Setup Database
```bash
# Login ke MySQL
mysql -u root -p

# Jalankan schema SQL
source /path/to/fintrack/config/schema.sql;
```

### 3. Konfigurasi Database
Edit `config/Database.php` atau set environment variables:
```bash
DB_HOST=localhost
DB_PORT=3306
DB_NAME=fintrack_db
DB_USER=root
DB_PASS=your_password
```

### 4. Buat Direktori yang Dibutuhkan
```bash
mkdir -p fintrack/exports fintrack/logs fintrack/uploads
chmod 755 fintrack/exports fintrack/logs fintrack/uploads
```

### 5. Akses Aplikasi
```
http://localhost/fintrack/
```

---

## Fitur Aplikasi

### 🔐 Autentikasi
- Login dengan email dan password (bcrypt hash)
- Registrasi pengguna baru dengan validasi
- Session management dengan CSRF protection
- Indikator kekuatan password

### 📊 Dashboard
- Ringkasan keuangan bulan berjalan (pemasukan, pengeluaran, saldo, anggaran)
- Grafik tren keuangan 6 bulan (Line Chart — Chart.js)
- Grafik donut pengeluaran per kategori
- Daftar transaksi terbaru
- Progress tujuan tabungan

### 💳 Transaksi
- Tambah/hapus transaksi pemasukan dan pengeluaran
- Filter berdasarkan bulan, tahun, tipe, dan kategori
- Notifikasi peringatan saat anggaran terlampaui
- Ekspor transaksi ke CSV (dengan BOM UTF-8 untuk Excel)

### 💰 Anggaran
- Penetapan anggaran per kategori per bulan
- Visualisasi realisasi vs anggaran dengan progress bar
- Status: Aman (< 80%), Hampir Habis (80-100%), Melebihi (> 100%)
- Upsert anggaran (insert atau update otomatis)

### 🐷 Tabungan
- Buat tujuan tabungan dengan target, deadline, ikon, dan warna
- Tambah dana ke tujuan tabungan
- Hitung otomatis persentase progress
- Status otomatis berubah ke "completed" saat target tercapai

### 📂 Kategori
- Tambah/hapus kategori kustom
- Kategori dikelompokkan berdasarkan tipe (Pemasukan/Pengeluaran)
- Proteksi: kategori dengan transaksi tidak bisa dihapus
- 15+ ikon tersedia

### 📈 Laporan
- Ringkasan keuangan tahunan
- Bar chart pemasukan vs pengeluaran per bulan
- Line chart tren saldo bersih
- Tabel detail per bulan dengan rasio tabungan

---

## Arsitektur & Desain OOP

### Namespace (2 Namespace Utama)
```php
namespace FinTrack\Auth;    // Autentikasi & User
namespace FinTrack\Finance; // Keuangan & Model Data
namespace FinTrack\Config;  // Konfigurasi & Database
```

### Hierarki Class

```
BaseModel (Abstract)
    ├── Transaction
    │   ├── Income          <- Inheritance
    │   └── Expense         <- Inheritance + Polymorphism
    ├── Category
    ├── Budget
    └── SavingsGoal

AuthService implements AuthInterface
User (standalone)
Database (Singleton)
```

### Interface
- **`Auditable`** — Kontrak untuk audit logging (`logActivity()`)
- **`Exportable`** — Kontrak untuk ekspor data (`exportToCsv()`)
- **`AuthInterface`** — Kontrak untuk autentikasi (`login()`, `logout()`, `register()`)

### Polymorphism
```php
// Contoh: Income dan Expense memiliki implementasi berbeda untuk create()
$income  = new Income();
$expense = new Expense();

$income->create($data);   // Memaksa type='income', validasi income
$expense->create($data);  // Memaksa type='expense', cek anggaran

// Keduanya mewarisi create() dari BaseModel tapi berperilaku berbeda
```

### Overloading Method
```php
// Transaction::create() dioverride di Income dan Expense
// untuk menambahkan validasi dan logika khusus per tipe
```

---

## Pemenuhan Brief Requirements

| Req | Implementasi |
|-----|-------------|
| **a** | Sesuai DFD/Use Case yang terdokumentasi di README ini |
| **b** | PHP PSR-12 coding style, DocBlocks, strict_types=1 |
| **c** | UI lengkap: form input, tabel, grafik, modal, filter |
| **d** | Tipe data tepat (int, float, string, array, bool), if/else, for, foreach, do-while |
| **e** | Method pada setiap class: `create()`, `findById()`, `getSummary()`, dll |
| **f** | Array digunakan di `$fillable`, `$availableIcons`, `$catByType`, data chart, dll |
| **g** | MySQL database + ekspor CSV ke file system |
| **h** | `private`/`protected`/`public`, properties, inheritance (Income←Transaction), polymorphism, overloading, interface (Auditable, Exportable, AuthInterface) |
| **i** | `FinTrack\Auth`, `FinTrack\Finance`, `FinTrack\Config` |
| **j** | **Chart.js** (grafik), **Tailwind CSS CDN**, **Lucide Icons**, **Google Fonts** |
| **k** | MySQL dengan 5 tabel: `users`, `categories`, `transactions`, `budgets`, `savings_goals`, `audit_logs` |
| **l** | PHPDoc pada setiap class dan method, README lengkap |

---

## External Libraries

| Library | Versi | Kegunaan |
|---------|-------|---------|
| **Chart.js** | 4.4.0 | Grafik line, bar, doughnut di dashboard & laporan |
| **Tailwind CSS** | 3.x CDN | Framework CSS utility-first untuk UI |
| **Lucide Icons** | Latest | Ikon SVG modern untuk UI |
| **Google Fonts** | Plus Jakarta Sans | Tipografi aplikasi |

---

## Database Schema

### Tabel `users`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT UNSIGNED PK | Auto increment |
| name | VARCHAR(100) | Nama pengguna |
| email | VARCHAR(150) UNIQUE | Email unik |
| password | VARCHAR(255) | Bcrypt hash |
| role | ENUM('admin','user') | Hak akses |
| monthly_budget | DECIMAL(15,2) | Anggaran bulanan |

### Tabel `transactions`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT UNSIGNED PK | Auto increment |
| user_id | FK → users | Pemilik transaksi |
| category_id | FK → categories | Kategori |
| type | ENUM('income','expense') | Tipe transaksi |
| amount | DECIMAL(15,2) | Jumlah uang |
| transaction_date | DATE | Tanggal transaksi |

*(Lihat `config/schema.sql` untuk schema lengkap)*

---

## Akun Demo

| Role | Email | Password |
|------|-------|---------|
| Admin | admin@fintrack.com | password |
| User | demo@fintrack.com | password |

---

## Lisensi

Proyek ini dibuat untuk keperluan tugas akademik mata kuliah Pemrograman Berorientasi Objek.

---

*Dokumentasi ini mengikuti standar PHPDoc dan Markdown documentation guidelines.*
