-- =============================================================================
--  AIGen — Seed Konfigurasi
-- =============================================================================
--  Ini adalah data KONFIGURASI, bukan data operasional.
--
--  MASUK file ini (wajib ada agar aplikasi bisa jalan):
--    system_settings, feature_flags, credit_costs, credit_packages,
--    subscription_tiers, nav_menu, coming_soon_items, theme_presets,
--    formula_config, data_vendors (TANPA api_key)
--
--  TIDAK masuk file ini (dihasilkan job sinkronisasi):
--    stocks, sectors, indicator_snapshot_fundamental, shareholder_composition
--    Jalankan: php jobs/sync_stocks.php lalu php jobs/sync_fundamental.php
--
--  KEAMANAN: kolom api_key sengaja dikosongkan. Isi lewat panel admin.
--  JANGAN pernah commit API key ke Git.
--
--  Idempotent: aman dijalankan berulang (INSERT ... ON DUPLICATE KEY UPDATE).
--  Sintaks VALUES() dipakai agar kompatibel dengan MariaDB 10.4 (XAMPP).
--
--  Model kredit yang diterapkan:
--    - Trial 7 hari + 100 kredit saat daftar
--    - Screening = 1 kredit, Detail emiten = 2 kredit
--    - Watchlist & cek saldo = gratis
--    - Setelah trial habis -> tier Free, kuota 5 screening/hari
--
--  Import: mysql -u root -p aigen_db < database/seed.sql
-- =============================================================================

USE `aigen_db`;

-- -----------------------------------------------------------------------------
-- 1. SYSTEM SETTINGS
-- -----------------------------------------------------------------------------
INSERT INTO `system_settings`
    (`setting_key`, `setting_value`, `setting_group`, `value_type`, `description`)
VALUES
    -- Branding
    ('site_name',             'AIGen',                             'branding',  'string',  'Nama aplikasi yang tampil di UI'),
    ('site_tagline',          'Analisis Saham IHSG Berbasis Data', 'branding',  'string',  'Tagline singkat'),
    ('site_logo_url',         '',                                  'branding',  'string',  'URL logo utama'),
    ('site_favicon_url',      '',                                  'branding',  'string',  'URL favicon'),
    ('support_email',         'support@aigen.id',                  'branding',  'string',  'Email bantuan pengguna'),

    -- Trial & tier default
    ('trial_credit_amount',   '100',                               'trial',     'number',  'Kredit gratis saat pendaftaran'),
    ('trial_duration_days',   '7',                                 'trial',     'number',  'Lama masa trial (hari)'),
    ('trial_tier_key',        'trial',                             'trial',     'string',  'tier_key yang diberikan saat daftar'),
    ('default_tier_key',      'free',                              'trial',     'string',  'Tier saat tidak punya langganan aktif'),

    -- Kredit
    ('credit_currency_label', 'Kredit',                            'credit',    'string',  'Sebutan satuan kredit di UI'),
    ('min_topup_amount',      '10000',                             'credit',    'number',  'Nominal top-up minimum (Rupiah)'),

    -- Screening
    ('screening_max_limit',   '100',                               'screening', 'number',  'Maksimum baris hasil screening'),
    ('screening_default_limit','50',                               'screening', 'number',  'Jumlah baris default'),

    -- Data & sinkronisasi
    ('data_sync_hour',        '18',                                'data',      'number',  'Jam job sync harian (0-23, WIB)'),
    ('data_staleness_days',   '3',                                 'data',      'number',  'Data dianggap basi setelah N hari'),

    -- Legal
    ('disclaimer_text',
     'AIGen menyajikan data dan analisis untuk tujuan edukasi dan informasi semata, bukan rekomendasi jual atau beli. Segala keputusan investasi sepenuhnya menjadi tanggung jawab pengguna. Kinerja masa lalu tidak menjamin hasil di masa depan.',
     'legal', 'string', 'Disclaimer wajib tampil di footer'),
    ('terms_url',             '/terms',                            'legal',     'string',  'Tautan syarat & ketentuan'),
    ('privacy_url',           '/privacy',                          'legal',     'string',  'Tautan kebijakan privasi'),

    -- Autentikasi
    ('allow_registration',    '1',                                 'auth',      'boolean', 'Izinkan pendaftaran user baru'),
    ('require_email_verification','0',                             'auth',      'boolean', 'Wajib verifikasi email sebelum login')
ON DUPLICATE KEY UPDATE
    `setting_group` = VALUES(`setting_group`),
    `value_type`    = VALUES(`value_type`),
    `description`   = VALUES(`description`);

-- -----------------------------------------------------------------------------
-- 2. FEATURE FLAGS
-- -----------------------------------------------------------------------------
INSERT INTO `feature_flags` (`feature_key`, `is_active`, `description`) VALUES
    ('fundamental_screening', 1, 'Mesin screening fundamental (inti fase 1)'),
    ('fundamental_detail',    1, 'Halaman detail emiten'),
    ('watchlist',             1, 'Simpan emiten ke watchlist'),
    ('coming_soon_vote',      1, 'Voting fitur yang akan datang'),
    ('theme_customizer',      1, 'Pemilihan tema oleh pengguna'),
    ('credit_system',         1, 'Pemotongan kredit. Matikan agar semua fitur gratis'),
    ('payment_midtrans',      0, 'Pembayaran Midtrans — nyalakan setelah kredensial terisi'),
    ('registration',          1, 'Pendaftaran pengguna baru'),
    ('technical_engine',      0, 'Engine Technical — coming soon'),
    ('smart_money_engine',    0, 'Engine Smart Money — coming soon'),
    ('money_flow_engine',     0, 'Engine Money Flow — coming soon'),
    ('risk_engine',           0, 'Engine Risk — coming soon'),
    ('ai_engine',             0, 'Engine AI — coming soon')
ON DUPLICATE KEY UPDATE
    `description` = VALUES(`description`);

-- -----------------------------------------------------------------------------
-- 3. CREDIT COSTS  (biaya per aksi — NO HARDCODE)
-- -----------------------------------------------------------------------------
INSERT INTO `credit_costs` (`action_key`, `action_name`, `cost`, `is_active`) VALUES
    ('run_screening',     'Jalankan Screening Fundamental', 1, 1),
    ('view_stock_detail', 'Lihat Detail Emiten',            2, 1),
    ('export_report',     'Ekspor Laporan',                 5, 1),
    ('compare_stocks',    'Bandingkan Emiten',              3, 1),
    -- Aksi gratis didaftarkan eksplisit dengan cost 0, supaya UsageGate tahu
    -- ini memang gratis — bukan sekadar belum dikonfigurasi.
    ('view_watchlist',    'Lihat Watchlist',                0, 1),
    ('add_watchlist',     'Tambah ke Watchlist',            0, 1),
    ('view_balance',      'Cek Saldo Kredit',               0, 1),
    ('view_stock_list',   'Lihat Daftar Emiten',            0, 1)
ON DUPLICATE KEY UPDATE
    `action_name` = VALUES(`action_name`),
    `cost`        = VALUES(`cost`),
    `is_active`   = VALUES(`is_active`);

-- -----------------------------------------------------------------------------
-- 4. SUBSCRIPTION TIERS
--    screening_quota NULL = unlimited
-- -----------------------------------------------------------------------------
INSERT INTO `subscription_tiers`
    (`tier_key`, `name`, `price`, `billing_period`, `screening_quota`, `bonus_credit`, `features`, `is_active`, `sort_order`)
VALUES
    ('trial', 'Trial 7 Hari',      0.00, 'monthly',   20, 100,
     '["100 kredit gratis","20 screening per hari","Akses penuh detail emiten","Watchlist tanpa batas"]', 1, 0),

    ('free',  'Free',              0.00, 'monthly',    5,   0,
     '["5 screening per hari","Detail emiten memakai kredit","Watchlist maksimal 10 emiten"]', 1, 1),

    ('basic', 'Basic',         99000.00, 'monthly',   50, 100,
     '["50 screening per hari","100 kredit bonus tiap bulan","Watchlist tanpa batas","Ekspor laporan"]', 1, 2),

    ('pro',   'Pro',          249000.00, 'monthly', NULL, 500,
     '["Screening tanpa batas","500 kredit bonus tiap bulan","Semua fitur Basic","Prioritas akses fitur baru"]', 1, 3)
ON DUPLICATE KEY UPDATE
    `name`            = VALUES(`name`),
    `price`           = VALUES(`price`),
    `screening_quota` = VALUES(`screening_quota`),
    `bonus_credit`    = VALUES(`bonus_credit`),
    `features`        = VALUES(`features`),
    `sort_order`      = VALUES(`sort_order`);

-- -----------------------------------------------------------------------------
-- 5. CREDIT PACKAGES  (paket top-up)
-- -----------------------------------------------------------------------------
INSERT INTO `credit_packages`
    (`name`, `credit_amount`, `bonus_credit`, `price`, `is_active`, `sort_order`)
VALUES
    ('Starter',   100,   0,  15000.00, 1, 1),
    ('Value',     300,  30,  40000.00, 1, 2),
    ('Pro Pack',  750, 100,  90000.00, 1, 3),
    ('Maximum',  2000, 400, 220000.00, 1, 4)
ON DUPLICATE KEY UPDATE
    `credit_amount` = VALUES(`credit_amount`),
    `bonus_credit`  = VALUES(`bonus_credit`),
    `price`         = VALUES(`price`),
    `sort_order`    = VALUES(`sort_order`);

-- -----------------------------------------------------------------------------
-- 6. NAV MENU  (sidebar dinamis — tidak boleh hardcode di React)
-- -----------------------------------------------------------------------------
INSERT INTO `nav_menu`
    (`menu_key`, `label`, `icon`, `route`, `status`, `sort_order`, `is_visible`)
VALUES
    ('dashboard',   'Dashboard',            'LayoutDashboard',  '/dashboard',   'active',       1, 1),
    ('screener',    'Fundamental Screener', 'Filter',           '/screener',    'active',       2, 1),
    ('watchlist',   'Watchlist',            'Star',             '/watchlist',   'active',       3, 1),
    ('technical',   'Technical Analysis',   'CandlestickChart', '/technical',   'coming_soon',  4, 1),
    ('smart_money', 'Smart Money',          'Landmark',         '/smart-money', 'coming_soon',  5, 1),
    ('money_flow',  'Money Flow',           'Waves',            '/money-flow',  'coming_soon',  6, 1),
    ('risk',        'Risk Analysis',        'ShieldAlert',      '/risk',        'coming_soon',  7, 1),
    ('ai_insight',  'AI Insight',           'Sparkles',         '/ai-insight',  'coming_soon',  8, 1),
    ('billing',     'Kredit & Langganan',   'Wallet',           '/billing',     'active',       9, 1),
    ('settings',    'Pengaturan',           'Settings',         '/settings',    'active',      10, 1)
ON DUPLICATE KEY UPDATE
    `label`      = VALUES(`label`),
    `icon`       = VALUES(`icon`),
    `route`      = VALUES(`route`),
    `status`     = VALUES(`status`),
    `sort_order` = VALUES(`sort_order`);

-- -----------------------------------------------------------------------------
-- 7. COMING SOON ITEMS  (5 engine selain Fundamental)
--    Tabel ini merujuk nav_menu lewat nav_menu_id, jadi sub-query dipakai
--    agar tidak bergantung pada nilai AUTO_INCREMENT tertentu.
-- -----------------------------------------------------------------------------
INSERT INTO `coming_soon_items`
    (`nav_menu_id`, `title`, `description`, `progress_percent`, `eta_label`)
SELECT `id`, 'Technical Analysis',
       'Indikator harga, pola candlestick, support & resistance otomatis, serta sinyal beli-jual berbasis momentum.',
       15, 'Q4 2026'
  FROM `nav_menu` WHERE `menu_key` = 'technical'
ON DUPLICATE KEY UPDATE
    `title`            = VALUES(`title`),
    `description`      = VALUES(`description`),
    `progress_percent` = VALUES(`progress_percent`),
    `eta_label`        = VALUES(`eta_label`);

INSERT INTO `coming_soon_items`
    (`nav_menu_id`, `title`, `description`, `progress_percent`, `eta_label`)
SELECT `id`, 'Smart Money',
       'Lacak pergerakan investor institusi dan asing: akumulasi, distribusi, dan konsentrasi kepemilikan.',
       5, 'Q1 2027'
  FROM `nav_menu` WHERE `menu_key` = 'smart_money'
ON DUPLICATE KEY UPDATE
    `title`            = VALUES(`title`),
    `description`      = VALUES(`description`),
    `progress_percent` = VALUES(`progress_percent`),
    `eta_label`        = VALUES(`eta_label`);

INSERT INTO `coming_soon_items`
    (`nav_menu_id`, `title`, `description`, `progress_percent`, `eta_label`)
SELECT `id`, 'Money Flow',
       'Analisis aliran dana masuk dan keluar per emiten dan per sektor secara harian.',
       5, 'Q1 2027'
  FROM `nav_menu` WHERE `menu_key` = 'money_flow'
ON DUPLICATE KEY UPDATE
    `title`            = VALUES(`title`),
    `description`      = VALUES(`description`),
    `progress_percent` = VALUES(`progress_percent`),
    `eta_label`        = VALUES(`eta_label`);

INSERT INTO `coming_soon_items`
    (`nav_menu_id`, `title`, `description`, `progress_percent`, `eta_label`)
SELECT `id`, 'Risk Analysis',
       'Ukur volatilitas, beta, drawdown maksimum, dan skor risiko kebangkrutan tiap emiten.',
       10, 'Q2 2027'
  FROM `nav_menu` WHERE `menu_key` = 'risk'
ON DUPLICATE KEY UPDATE
    `title`            = VALUES(`title`),
    `description`      = VALUES(`description`),
    `progress_percent` = VALUES(`progress_percent`),
    `eta_label`        = VALUES(`eta_label`);

INSERT INTO `coming_soon_items`
    (`nav_menu_id`, `title`, `description`, `progress_percent`, `eta_label`)
SELECT `id`, 'AI Insight',
       'Ringkasan naratif berbasis AI atas kondisi fundamental emiten, lengkap dengan konteks sektor.',
       0, 'Belum dijadwalkan'
  FROM `nav_menu` WHERE `menu_key` = 'ai_insight'
ON DUPLICATE KEY UPDATE
    `title`            = VALUES(`title`),
    `description`      = VALUES(`description`),
    `progress_percent` = VALUES(`progress_percent`),
    `eta_label`        = VALUES(`eta_label`);

-- -----------------------------------------------------------------------------
-- 8. THEME PRESETS
-- -----------------------------------------------------------------------------
INSERT INTO `theme_presets`
    (`preset_key`, `name`, `primary_color`, `accent_color`, `background_color`, `card_color`, `background_mode`, `radius`, `is_default`, `sort_order`)
VALUES
    ('dark',    'Midnight', '#00E5FF', '#7C4DFF', '#0A0E17', '#111623', 'dark',  'medium',  1, 1),
    ('emerald', 'Emerald',  '#10B981', '#34D399', '#04140F', '#0B2119', 'dark',  'medium',  0, 2),
    ('amber',   'Amber',    '#F59E0B', '#FBBF24', '#140F04', '#221909', 'dark',  'medium',  0, 3),
    ('crimson', 'Crimson',  '#EF4444', '#F87171', '#160809', '#241012', 'dark',  'sharp',   0, 4),
    ('violet',  'Violet',   '#8B5CF6', '#A78BFA', '#0F0A1A', '#1A1229', 'dark',  'rounded', 0, 5),
    ('light',   'Daylight', '#0284C7', '#0EA5E9', '#F8FAFC', '#FFFFFF', 'light', 'medium',  0, 6),
    ('sepia',   'Sepia',    '#B45309', '#D97706', '#FAF5EC', '#FFFFFF', 'light', 'rounded', 0, 7),
    ('nordic',  'Nordic',   '#5E81AC', '#88C0D0', '#ECEFF4', '#FFFFFF', 'light', 'sharp',   0, 8)
ON DUPLICATE KEY UPDATE
    `name`             = VALUES(`name`),
    `primary_color`    = VALUES(`primary_color`),
    `accent_color`     = VALUES(`accent_color`),
    `background_color` = VALUES(`background_color`),
    `card_color`       = VALUES(`card_color`),
    `background_mode`  = VALUES(`background_mode`),
    `radius`           = VALUES(`radius`),
    `sort_order`       = VALUES(`sort_order`);

-- -----------------------------------------------------------------------------
-- 9. FORMULA CONFIG  (bobot & threshold skor fundamental)
--
--    Skor tiap metrik dinormalisasi linier antara threshold_bad dan
--    threshold_good, lalu dirata-rata tertimbang memakai weight.
--
--    Arah metrik ditentukan dari perbandingan kedua threshold:
--      threshold_good > threshold_bad  -> makin besar makin baik (ROE, ROA)
--      threshold_good < threshold_bad  -> makin kecil makin baik (DER, PER, PBV)
--    Jadi tidak diperlukan kolom higher_is_better terpisah.
-- -----------------------------------------------------------------------------
INSERT INTO `formula_config`
    (`formula_key`, `formula_name`, `category`, `formula_expression`, `weight`, `threshold_good`, `threshold_bad`, `is_active`)
VALUES
    ('roe', 'Return on Equity',     'fundamental', 'laba_bersih / ekuitas * 100',      2.00, 20.0000,  5.0000, 1),
    ('roa', 'Return on Assets',     'fundamental', 'laba_bersih / total_aset * 100',   1.00, 10.0000,  2.0000, 1),
    ('der', 'Debt to Equity Ratio', 'fundamental', 'total_liabilitas / ekuitas',       1.50,  0.5000,  2.0000, 1),
    ('per', 'Price to Earnings',    'fundamental', 'harga / laba_per_saham',           1.00, 10.0000, 25.0000, 1),
    ('pbv', 'Price to Book Value',  'fundamental', 'harga / nilai_buku_per_saham',     1.00,  1.0000,  3.0000, 1),
    ('npm', 'Net Profit Margin',    'fundamental', 'laba_bersih / pendapatan * 100',   1.25, 15.0000,  3.0000, 1),
    ('cr',  'Current Ratio',        'fundamental', 'aset_lancar / liabilitas_lancar',  1.00,  2.0000,  1.0000, 1)
ON DUPLICATE KEY UPDATE
    `formula_name`       = VALUES(`formula_name`),
    `category`           = VALUES(`category`),
    `formula_expression` = VALUES(`formula_expression`),
    `weight`             = VALUES(`weight`),
    `threshold_good`     = VALUES(`threshold_good`),
    `threshold_bad`      = VALUES(`threshold_bad`);

-- -----------------------------------------------------------------------------
-- 10. DATA VENDORS
--     api_key SENGAJA KOSONG. Isi lewat panel admin setelah instalasi.
--     Jangan pernah menuliskan API key di file yang masuk Git.
-- -----------------------------------------------------------------------------
INSERT INTO `data_vendors`
    (`vendor_name`, `base_url`, `api_key`, `auth_type`, `daily_quota`, `is_active`)
VALUES
    ('Invezgo',     'https://api.invezgo.com/api/v1', '', 'bearer',     5000, 1),
    ('DataSectors', 'https://api.datasectors.com',    '', 'header_key', 2000, 0)
ON DUPLICATE KEY UPDATE
    `base_url`    = VALUES(`base_url`),
    `auth_type`   = VALUES(`auth_type`),
    `daily_quota` = VALUES(`daily_quota`);

-- =============================================================================
--  SELESAI
-- =============================================================================
--  Langkah berikutnya:
--    1. Buat akun admin   : php jobs/create_admin.php
--    2. Isi API key vendor lewat panel admin
--    3. Tarik data emiten : php jobs/sync_stocks.php
--    4. Tarik fundamental : php jobs/sync_fundamental.php
-- =============================================================================
