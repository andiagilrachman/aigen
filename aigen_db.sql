-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 21 Agu 2026 pada 16.28
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `aigen_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `api_response_cache`
--

CREATE TABLE `api_response_cache` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cache_key` varchar(191) NOT NULL,
  `vendor` varchar(30) NOT NULL,
  `response_body` longtext DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `coming_soon_items`
--

CREATE TABLE `coming_soon_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `nav_menu_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `progress_percent` tinyint(4) NOT NULL DEFAULT 0,
  `eta_label` varchar(50) DEFAULT NULL COMMENT 'misal: Q1 2027, Build v1.2',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `coming_soon_subscriptions`
--

CREATE TABLE `coming_soon_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coming_soon_id` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `coming_soon_votes`
--

CREATE TABLE `coming_soon_votes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coming_soon_id` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `corporate_actions`
--

CREATE TABLE `corporate_actions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `action_type` enum('earnings','dividend','ipo','split') NOT NULL,
  `event_date` date NOT NULL,
  `detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `credit_costs`
--

CREATE TABLE `credit_costs` (
  `id` int(10) UNSIGNED NOT NULL,
  `action_key` varchar(50) NOT NULL COMMENT 'run_screening, view_stock_detail, export_report, dll',
  `action_name` varchar(100) NOT NULL,
  `cost` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `credit_packages`
--

CREATE TABLE `credit_packages` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `credit_amount` int(11) NOT NULL,
  `bonus_credit` int(11) NOT NULL DEFAULT 0,
  `price` decimal(12,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `credit_transactions`
--

CREATE TABLE `credit_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('topup','usage','refund','bonus','trial') NOT NULL,
  `amount` int(11) NOT NULL COMMENT 'positif = masuk, negatif = keluar',
  `balance_after` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'screening, stock_detail, export, payment, dll',
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `credit_wallets`
--

CREATE TABLE `credit_wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_vendors`
--

CREATE TABLE `data_vendors` (
  `id` int(10) UNSIGNED NOT NULL,
  `vendor_name` varchar(50) NOT NULL COMMENT 'DataSectors, Invezgo, dll',
  `base_url` varchar(255) NOT NULL,
  `api_key` text DEFAULT NULL COMMENT 'terenkripsi',
  `auth_type` enum('header_key','bearer') NOT NULL DEFAULT 'header_key',
  `daily_quota` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `feature_flags`
--

CREATE TABLE `feature_flags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `formula_config`
--

CREATE TABLE `formula_config` (
  `id` int(10) UNSIGNED NOT NULL,
  `formula_key` varchar(80) NOT NULL COMMENT 'roe, der, per, altman_z, beneish_m, piotroski_f, graham_number, dll',
  `formula_name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'fundamental',
  `formula_expression` text DEFAULT NULL COMMENT 'representasi rumus untuk referensi/dokumentasi',
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00,
  `threshold_good` decimal(20,4) DEFAULT NULL,
  `threshold_bad` decimal(20,4) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `indicator_history_fundamental`
--

CREATE TABLE `indicator_history_fundamental` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `period_label` varchar(20) NOT NULL COMMENT 'misal Q2 2025',
  `period_type` enum('Q1','Q2','Q3','Q4','FY') NOT NULL,
  `fiscal_year` smallint(6) NOT NULL,
  `account_name` varchar(255) NOT NULL COMMENT 'nama akun asli dari raw line-item Invezgo',
  `statement_type` enum('BS','IS','CF') NOT NULL COMMENT 'Balance Sheet, Income Statement, Cash Flow',
  `account_level` tinyint(4) NOT NULL DEFAULT 0,
  `parent_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(24,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `indicator_snapshot_fundamental`
--

CREATE TABLE `indicator_snapshot_fundamental` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `snapshot_date` date NOT NULL,
  `roe` decimal(10,4) DEFAULT NULL,
  `roa` decimal(10,4) DEFAULT NULL,
  `der` decimal(10,4) DEFAULT NULL,
  `per` decimal(10,4) DEFAULT NULL,
  `pbv` decimal(10,4) DEFAULT NULL,
  `eps` decimal(20,2) DEFAULT NULL,
  `bvps` decimal(20,2) DEFAULT NULL,
  `dividend_yield` decimal(10,4) DEFAULT NULL,
  `revenue_growth_yoy` decimal(10,4) DEFAULT NULL,
  `net_income_growth_yoy` decimal(10,4) DEFAULT NULL,
  `net_profit_margin` decimal(10,4) DEFAULT NULL,
  `gross_profit_margin` decimal(10,4) DEFAULT NULL,
  `current_ratio` decimal(10,4) DEFAULT NULL,
  `quick_ratio` decimal(10,4) DEFAULT NULL,
  `altman_z_score` decimal(10,4) DEFAULT NULL,
  `beneish_m_score` decimal(10,4) DEFAULT NULL,
  `piotroski_f_score` tinyint(4) DEFAULT NULL,
  `graham_number` decimal(20,2) DEFAULT NULL,
  `fundamental_score` decimal(5,2) DEFAULT NULL COMMENT 'skor komposit 0-100 hasil formula_config',
  `vendor_insight_score` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'skor bawaan DataSectors insights untuk pembanding' CHECK (json_valid(`vendor_insight_score`)),
  `rating` varchar(30) DEFAULT NULL COMMENT 'misal: Layak Investasi Jangka Panjang',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `nav_menu`
--

CREATE TABLE `nav_menu` (
  `id` int(10) UNSIGNED NOT NULL,
  `menu_key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `route` varchar(100) DEFAULT NULL,
  `status` enum('active','coming_soon') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `payment_type` enum('subscription','credit_topup') NOT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'tier_id atau credit_package_id',
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','success','failed','expired') NOT NULL DEFAULT 'pending',
  `midtrans_order_id` varchar(100) DEFAULT NULL,
  `midtrans_transaction_id` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `price_seasonality`
--

CREATE TABLE `price_seasonality` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `month` tinyint(4) NOT NULL COMMENT '1-12',
  `avg_return` decimal(10,4) DEFAULT NULL,
  `win_rate` decimal(5,2) DEFAULT NULL,
  `years_sampled` tinyint(4) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `sectors`
--

CREATE TABLE `sectors` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `sub_sector` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `shareholder_composition`
--

CREATE TABLE `shareholder_composition` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `holder_name` varchar(200) NOT NULL,
  `percentage` decimal(8,4) DEFAULT NULL,
  `badge` varchar(50) DEFAULT NULL COMMENT 'Pengendali, Komisaris, Direksi, dll',
  `source` varchar(20) NOT NULL DEFAULT 'invezgo',
  `snapshot_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `stocks`
--

CREATE TABLE `stocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `company_name_short` varchar(80) DEFAULT NULL,
  `sector_id` int(10) UNSIGNED DEFAULT NULL,
  `exchange` varchar(20) NOT NULL DEFAULT 'IDX',
  `listing_date` date DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `is_syariah` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `market_cap` decimal(20,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_management`
--

CREATE TABLE `stock_management` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('commissioner','director') NOT NULL,
  `person_name` varchar(150) NOT NULL,
  `position_title` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `subscription_tiers`
--

CREATE TABLE `subscription_tiers` (
  `id` int(10) UNSIGNED NOT NULL,
  `tier_key` varchar(30) NOT NULL COMMENT 'free, basic, pro',
  `name` varchar(50) NOT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `billing_period` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `screening_quota` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
  `bonus_credit` int(11) NOT NULL DEFAULT 0,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general' COMMENT 'branding, trial, credit, disclaimer, seo, dll',
  `value_type` enum('string','number','boolean','json') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `theme_presets`
--

CREATE TABLE `theme_presets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `preset_key` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `primary_color` varchar(7) NOT NULL,
  `accent_color` varchar(7) NOT NULL,
  `background_color` varchar(7) NOT NULL,
  `card_color` varchar(7) NOT NULL,
  `background_mode` enum('dark','light') NOT NULL DEFAULT 'dark',
  `radius` varchar(10) NOT NULL DEFAULT 'medium' COMMENT 'sharp, medium, rounded',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `trial_usage`
--

CREATE TABLE `trial_usage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `usage_date` date NOT NULL,
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `trial_ends_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `role` enum('super_admin','admin','support','user') NOT NULL DEFAULT 'user',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `language` varchar(5) NOT NULL DEFAULT 'id',
  `theme_preference` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'preset_id, primary_color, accent_color, background, radius, auto_switch' CHECK (json_valid(`theme_preference`)),
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_subscriptions`
--

CREATE TABLE `user_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tier_id` int(10) UNSIGNED NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `started_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `vendor_usage_log`
--

CREATE TABLE `vendor_usage_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` int(10) UNSIGNED NOT NULL,
  `usage_date` date NOT NULL,
  `request_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `watchlists`
--

CREATE TABLE `watchlists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `stock_id` bigint(20) UNSIGNED NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user` (`user_id`),
  ADD KEY `idx_logs_action` (`action`);

--
-- Indeks untuk tabel `api_response_cache`
--
ALTER TABLE `api_response_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cache_key` (`cache_key`),
  ADD KEY `idx_cache_expires` (`expires_at`);

--
-- Indeks untuk tabel `coming_soon_items`
--
ALTER TABLE `coming_soon_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comingsoon_menu` (`nav_menu_id`);

--
-- Indeks untuk tabel `coming_soon_subscriptions`
--
ALTER TABLE `coming_soon_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sub_item_email` (`coming_soon_id`,`email`),
  ADD KEY `fk_sub_user` (`user_id`);

--
-- Indeks untuk tabel `coming_soon_votes`
--
ALTER TABLE `coming_soon_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vote_item_user` (`coming_soon_id`,`user_id`),
  ADD KEY `fk_vote_user` (`user_id`);

--
-- Indeks untuk tabel `corporate_actions`
--
ALTER TABLE `corporate_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_actions_stock` (`stock_id`,`action_type`),
  ADD KEY `idx_actions_date` (`event_date`);

--
-- Indeks untuk tabel `credit_costs`
--
ALTER TABLE `credit_costs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_creditcost_key` (`action_key`);

--
-- Indeks untuk tabel `credit_packages`
--
ALTER TABLE `credit_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `credit_transactions`
--
ALTER TABLE `credit_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_credittx_user` (`user_id`,`created_at`);

--
-- Indeks untuk tabel `credit_wallets`
--
ALTER TABLE `credit_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wallet_user` (`user_id`);

--
-- Indeks untuk tabel `data_vendors`
--
ALTER TABLE `data_vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendor_name` (`vendor_name`);

--
-- Indeks untuk tabel `feature_flags`
--
ALTER TABLE `feature_flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_flags_key` (`feature_key`);

--
-- Indeks untuk tabel `formula_config`
--
ALTER TABLE `formula_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_formula_key` (`formula_key`);

--
-- Indeks untuk tabel `indicator_history_fundamental`
--
ALTER TABLE `indicator_history_fundamental`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_stock_period` (`stock_id`,`fiscal_year`,`period_type`),
  ADD KEY `idx_history_statement` (`statement_type`);

--
-- Indeks untuk tabel `indicator_snapshot_fundamental`
--
ALTER TABLE `indicator_snapshot_fundamental`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_snapshot_stock_date` (`stock_id`,`snapshot_date`),
  ADD KEY `idx_snapshot_score` (`fundamental_score`);

--
-- Indeks untuk tabel `nav_menu`
--
ALTER TABLE `nav_menu`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_navmenu_key` (`menu_key`);

--
-- Indeks untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payments_order` (`midtrans_order_id`),
  ADD KEY `idx_payments_user` (`user_id`,`status`);

--
-- Indeks untuk tabel `price_seasonality`
--
ALTER TABLE `price_seasonality`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seasonality_stock_month` (`stock_id`,`month`);

--
-- Indeks untuk tabel `sectors`
--
ALTER TABLE `sectors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sectors_name` (`name`,`sub_sector`);

--
-- Indeks untuk tabel `shareholder_composition`
--
ALTER TABLE `shareholder_composition`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shareholder_stock` (`stock_id`,`snapshot_date`);

--
-- Indeks untuk tabel `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stocks_symbol` (`symbol`),
  ADD KEY `idx_stocks_sector` (`sector_id`);

--
-- Indeks untuk tabel `stock_management`
--
ALTER TABLE `stock_management`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mgmt_stock` (`stock_id`);

--
-- Indeks untuk tabel `subscription_tiers`
--
ALTER TABLE `subscription_tiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tier_key` (`tier_key`);

--
-- Indeks untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_settings_key` (`setting_key`);

--
-- Indeks untuk tabel `theme_presets`
--
ALTER TABLE `theme_presets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_theme_key` (`preset_key`);

--
-- Indeks untuk tabel `trial_usage`
--
ALTER TABLE `trial_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_trial_user_date` (`user_id`,`usage_date`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indeks untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sessions_token` (`session_token`),
  ADD KEY `idx_sessions_user` (`user_id`);

--
-- Indeks untuk tabel `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usersub_user` (`user_id`,`status`),
  ADD KEY `fk_usersub_tier` (`tier_id`);

--
-- Indeks untuk tabel `vendor_usage_log`
--
ALTER TABLE `vendor_usage_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usage_vendor_date` (`vendor_id`,`usage_date`);

--
-- Indeks untuk tabel `watchlists`
--
ALTER TABLE `watchlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_watchlist_user_stock` (`user_id`,`stock_id`),
  ADD KEY `fk_watchlist_stock` (`stock_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `api_response_cache`
--
ALTER TABLE `api_response_cache`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `coming_soon_items`
--
ALTER TABLE `coming_soon_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `coming_soon_subscriptions`
--
ALTER TABLE `coming_soon_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `coming_soon_votes`
--
ALTER TABLE `coming_soon_votes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `corporate_actions`
--
ALTER TABLE `corporate_actions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `credit_costs`
--
ALTER TABLE `credit_costs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `credit_packages`
--
ALTER TABLE `credit_packages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `credit_transactions`
--
ALTER TABLE `credit_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `credit_wallets`
--
ALTER TABLE `credit_wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `data_vendors`
--
ALTER TABLE `data_vendors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `feature_flags`
--
ALTER TABLE `feature_flags`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `formula_config`
--
ALTER TABLE `formula_config`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `indicator_history_fundamental`
--
ALTER TABLE `indicator_history_fundamental`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `indicator_snapshot_fundamental`
--
ALTER TABLE `indicator_snapshot_fundamental`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `nav_menu`
--
ALTER TABLE `nav_menu`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `price_seasonality`
--
ALTER TABLE `price_seasonality`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `sectors`
--
ALTER TABLE `sectors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `shareholder_composition`
--
ALTER TABLE `shareholder_composition`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `stock_management`
--
ALTER TABLE `stock_management`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `subscription_tiers`
--
ALTER TABLE `subscription_tiers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `theme_presets`
--
ALTER TABLE `theme_presets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `trial_usage`
--
ALTER TABLE `trial_usage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `vendor_usage_log`
--
ALTER TABLE `vendor_usage_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `watchlists`
--
ALTER TABLE `watchlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `coming_soon_items`
--
ALTER TABLE `coming_soon_items`
  ADD CONSTRAINT `fk_comingsoon_menu` FOREIGN KEY (`nav_menu_id`) REFERENCES `nav_menu` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `coming_soon_subscriptions`
--
ALTER TABLE `coming_soon_subscriptions`
  ADD CONSTRAINT `fk_sub_item` FOREIGN KEY (`coming_soon_id`) REFERENCES `coming_soon_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `coming_soon_votes`
--
ALTER TABLE `coming_soon_votes`
  ADD CONSTRAINT `fk_vote_item` FOREIGN KEY (`coming_soon_id`) REFERENCES `coming_soon_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vote_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `corporate_actions`
--
ALTER TABLE `corporate_actions`
  ADD CONSTRAINT `fk_actions_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `credit_transactions`
--
ALTER TABLE `credit_transactions`
  ADD CONSTRAINT `fk_credittx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `credit_wallets`
--
ALTER TABLE `credit_wallets`
  ADD CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `indicator_history_fundamental`
--
ALTER TABLE `indicator_history_fundamental`
  ADD CONSTRAINT `fk_history_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `indicator_snapshot_fundamental`
--
ALTER TABLE `indicator_snapshot_fundamental`
  ADD CONSTRAINT `fk_snapshot_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `price_seasonality`
--
ALTER TABLE `price_seasonality`
  ADD CONSTRAINT `fk_seasonality_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `shareholder_composition`
--
ALTER TABLE `shareholder_composition`
  ADD CONSTRAINT `fk_shareholder_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `stocks`
--
ALTER TABLE `stocks`
  ADD CONSTRAINT `fk_stocks_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `stock_management`
--
ALTER TABLE `stock_management`
  ADD CONSTRAINT `fk_mgmt_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `trial_usage`
--
ALTER TABLE `trial_usage`
  ADD CONSTRAINT `fk_trial_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `fk_usersub_tier` FOREIGN KEY (`tier_id`) REFERENCES `subscription_tiers` (`id`),
  ADD CONSTRAINT `fk_usersub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `vendor_usage_log`
--
ALTER TABLE `vendor_usage_log`
  ADD CONSTRAINT `fk_usage_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `data_vendors` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `watchlists`
--
ALTER TABLE `watchlists`
  ADD CONSTRAINT `fk_watchlist_stock` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_watchlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
