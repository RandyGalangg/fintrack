-- ============================================================
-- FinTrack - Sistem Manajemen Keuangan Pribadi
-- Database Schema
-- Author: FinTrack Team
-- Version: 1.0.0
-- Date: 2024
-- ============================================================

CREATE DATABASE IF NOT EXISTS fintrack_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE fintrack_db;

-- ============================================================
-- Table: users
-- Menyimpan data pengguna sistem
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    avatar      VARCHAR(255)        DEFAULT NULL,
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    monthly_budget DECIMAL(15,2)   DEFAULT 0.00,
    is_active   TINYINT(1)         NOT NULL DEFAULT 1,
    created_at  TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP          DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
) ENGINE=InnoDB;

-- ============================================================
-- Table: categories
-- Kategori transaksi (income/expense)
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    name        VARCHAR(100)        NOT NULL,
    type        ENUM('income','expense') NOT NULL,
    icon        VARCHAR(50)         DEFAULT 'tag',
    color       VARCHAR(7)          DEFAULT '#6366f1',
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, type)
) ENGINE=InnoDB;

-- ============================================================
-- Table: transactions
-- Menyimpan semua transaksi keuangan
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    category_id     INT UNSIGNED        NOT NULL,
    type            ENUM('income','expense') NOT NULL,
    amount          DECIMAL(15,2)       NOT NULL,
    description     VARCHAR(255)        NOT NULL,
    note            TEXT                DEFAULT NULL,
    transaction_date DATE               NOT NULL,
    receipt_file    VARCHAR(255)        DEFAULT NULL,
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE RESTRICT,
    INDEX idx_user_date   (user_id, transaction_date),
    INDEX idx_user_type   (user_id, type),
    INDEX idx_date        (transaction_date)
) ENGINE=InnoDB;

-- ============================================================
-- Table: budgets
-- Anggaran per kategori per bulan
-- ============================================================
CREATE TABLE IF NOT EXISTS budgets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    category_id INT UNSIGNED        NOT NULL,
    amount      DECIMAL(15,2)       NOT NULL,
    month       TINYINT UNSIGNED    NOT NULL,   -- 1-12
    year        SMALLINT UNSIGNED   NOT NULL,
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE CASCADE,
    UNIQUE KEY  uq_budget (user_id, category_id, month, year),
    INDEX idx_user_period (user_id, year, month)
) ENGINE=InnoDB;

-- ============================================================
-- Table: savings_goals
-- Tujuan tabungan pengguna
-- ============================================================
CREATE TABLE IF NOT EXISTS savings_goals (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    title           VARCHAR(150)        NOT NULL,
    target_amount   DECIMAL(15,2)       NOT NULL,
    current_amount  DECIMAL(15,2)       NOT NULL DEFAULT 0.00,
    deadline        DATE                DEFAULT NULL,
    icon            VARCHAR(50)         DEFAULT 'piggy-bank',
    color           VARCHAR(7)          DEFAULT '#10b981',
    status          ENUM('active','completed','cancelled') DEFAULT 'active',
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB;

-- ============================================================
-- Table: audit_logs
-- Log aktivitas sistem untuk audit trail
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        DEFAULT NULL,
    action      VARCHAR(100)        NOT NULL,
    entity      VARCHAR(50)         NOT NULL,
    entity_id   INT UNSIGNED        DEFAULT NULL,
    old_data    JSON                DEFAULT NULL,
    new_data    JSON                DEFAULT NULL,
    ip_address  VARCHAR(45)         DEFAULT NULL,
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_entity (entity, entity_id)
) ENGINE=InnoDB;

-- ============================================================
-- Seed Data: Default Admin User
-- Password: Admin@123 (hashed)
-- ============================================================
INSERT INTO users (name, email, password, role, monthly_budget) VALUES
('Administrator', 'admin@fintrack.com', '$2y$10$28cCFZGfsTx0KmZTpCrumu91KyF/HKFJJ02UG/2CuM0GmKtCM9V6O', 'admin', 5000000),
('Demo User', 'demo@fintrack.com', '$2y$10$28cCFZGfsTx0KmZTpCrumu91KyF/HKFJJ02UG/2CuM0GmKtCM9V6O', 'user', 3000000);

-- ============================================================
-- Seed Data: Default Categories for Demo User (id=2)
-- ============================================================
INSERT INTO categories (user_id, name, type, icon, color) VALUES
(2, 'Gaji',           'income',  'briefcase',    '#10b981'),
(2, 'Freelance',      'income',  'laptop',       '#06b6d4'),
(2, 'Investasi',      'income',  'trending-up',  '#8b5cf6'),
(2, 'Makan & Minum',  'expense', 'utensils',     '#f59e0b'),
(2, 'Transportasi',   'expense', 'car',          '#3b82f6'),
(2, 'Belanja',        'expense', 'shopping-bag', '#ec4899'),
(2, 'Tagihan',        'expense', 'file-text',    '#ef4444'),
(2, 'Hiburan',        'expense', 'film',         '#f97316'),
(2, 'Kesehatan',      'expense', 'heart',        '#14b8a6'),
(2, 'Tabungan',       'expense', 'piggy-bank',   '#6366f1');

-- Seed Data: Sample Transactions for Demo User
INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date) VALUES
(2, 1, 'income',  5000000, 'Gaji Januari 2024', '2024-01-01'),
(2, 2, 'income',  1500000, 'Project Website Client A', '2024-01-05'),
(2, 4, 'expense',  350000, 'Makan siang seminggu', '2024-01-07'),
(2, 5, 'expense',  200000, 'Bensin & parkir', '2024-01-10'),
(2, 7, 'expense',  450000, 'Tagihan listrik & internet', '2024-01-15'),
(2, 6, 'expense',  800000, 'Belanja bulanan', '2024-01-20'),
(2, 1, 'income',  5000000, 'Gaji Februari 2024', '2024-02-01'),
(2, 3, 'income',   750000, 'Dividen saham', '2024-02-10'),
(2, 4, 'expense',  400000, 'Makan restoran keluarga', '2024-02-14'),
(2, 9, 'expense',  300000, 'Vitamin & obat-obatan', '2024-02-20');
