# Blueprint Lengkap AIGen + Audit Progres Fase 1

**Produk:** AIGen — SaaS Analisis Saham IHSG
**Database:** `aigen_db` (MariaDB 10.4 / MySQL)
**Dokumen ini menggantikan:** `blueprint-saas-saham-fase1.md` (tetap disimpan sebagai referensi historis)
**Tanggal audit:** 21 Agustus 2026
**Basis audit:** commit `09bd007` — 29 file PHP (±1.250 baris), skema 31 tabel, 4 view React

---

# RINGKASAN EKSEKUTIF

## Progres Fase 1 secara keseluruhan: ±30%

| Lapisan | Progres | Keterangan singkat |
|---|---|---|
| Skema database | ██████████ 100% | 31 tabel, 23 FK, index lengkap — **selesai** |
| Seed data konfigurasi | ░░░░░░░░░░ 5% | Hanya `formula_config`. 6 tabel config lain kosong → **sistem belum bisa jalan** |
| Core backend (helper) | ███████░░░ 70% | Auth/Settings/FeatureFlag/CreditManager/Response ada; `AdminAuth` belum ada |
| Endpoint API user | ████░░░░░░ 40% | 15 endpoint. Watchlist, membership, payment belum ada |
| Endpoint API admin | █░░░░░░░░░ 15% | 2 dari ±14 modul, **dan keduanya tanpa autentikasi** |
| Job sinkronisasi vendor | ███░░░░░░░ 35% | Stocks + fundamental dasar. Shareholder, corp action, history belum |
| Mesin skor fundamental | ████░░░░░░ 40% | Skor komposit jalan; rating & 4 formula lanjutan belum |
| Membership & kredit | ███░░░░░░░ 30% | Wallet + transaksi + biaya jalan; tier, trial, Midtrans belum |
| Frontend user | ██░░░░░░░░ 25% | 4 view fungsional tapi tanpa layout/sidebar, styling masih inline |
| Frontend admin | ░░░░░░░░░░ 0% | Folder `src/admin/pages/` kosong |
| Keamanan produksi | ██░░░░░░░░ 20% | 3 lubang kritikal terbuka (lihat Bagian B.1) |

## 5 hal yang paling mendesak

1. **Endpoint admin terbuka tanpa login** — siapa pun bisa mengubah seluruh `system_settings`
2. **Folder `jobs/` bisa dipanggil dari browser** — kuota API vendor bisa dikuras orang lain
3. **Seed config kosong** — project tidak bisa dijalankan dari nol oleh siapa pun
4. **Race condition saldo kredit** — kredit bisa terpakai ganda / saldo minus
5. **Frontend hanya ada di dalam `htdocs.zip`** — tidak masuk version control

---

---

# BAGIAN A — BLUEPRINT LENGKAP

---

## A.1 Prinsip Arsitektur

### A.1.1 No-Hardcode (fondasi utama)

> Kalau sebuah nilai BISA berubah tanpa mengubah cara kerja sistem → nilai itu WAJIB ada di database/panel, bukan di kode.

Tabel penopang prinsip ini:

| Tabel | Isi | Status |
|---|---|---|
| `system_settings` | Key-value global (nama situs, trial, disclaimer) | Tabel ✓ / Data ✗ |
| `feature_flags` | Aktif-nonaktif fitur | Tabel ✓ / Data ✗ / Belum dipakai kode |
| `data_vendors` | Kredensial & kuota vendor | Tabel ✓ / Data ✗ |
| `subscription_tiers` | Harga, kuota, fitur per tier | Tabel ✓ / Data ✗ / Kode ✗ |
| `credit_packages` | Paket top-up | Tabel ✓ / Data ✗ / Kode ✗ |
| `credit_costs` | Biaya kredit per aksi | Tabel ✓ / Data ✗ |
| `nav_menu` | Struktur sidebar | Tabel ✓ / Data ✗ |
| `coming_soon_items` | Metadata fitur belum rilis | Tabel ✓ / Data ✗ |
| `theme_presets` | Preset tampilan | Tabel ✓ / Data ✗ |
| `formula_config` | Rumus & threshold skor | Tabel ✓ / **Data ✓** |

**Pengecualian tunggal:** kredensial koneksi database (`config/database.php`), karena dibutuhkan sebelum koneksi terbuka.

**Pelanggaran prinsip yang masih ada di kode saat ini:**
- CORS origin di-hardcode `http://localhost:5173` (`config/cors.php`)
- Nama vendor di-hardcode sebagai string literal `"Invezgo"` / `"DataSectors"` di dalam SQL vendor client
- URL `http://localhost/aigen-backend/...` ditulis langsung di pesan output `sync_fundamental.php`

### A.1.2 Pemisahan Vendor (keputusan arsitektur yang sudah benar)

```
┌─────────────┐   1x/hari    ┌──────────────┐   baca saja   ┌─────────────┐
│ Vendor API  │ ───────────► │ Tabel lokal  │ ◄──────────── │ Endpoint    │
│ Invezgo/DS  │   via jobs/  │ (snapshot)   │   tanpa API   │ user        │
└─────────────┘              └──────────────┘               └─────────────┘
```

Endpoint user **tidak pernah** memanggil API vendor. Satu kali fetch dipakai berulang oleh semua user. Ini sudah diterapkan konsisten di kode dan harus dipertahankan.

**Konsekuensi yang belum ditangani:** kalau job sync gagal, data jadi basi tanpa ada indikator. Perlu kolom/monitoring "terakhir sync sukses" di panel admin.

### A.1.3 Alur Kredit & Refund

```
Aksi user → cek FeatureFlag → cek kuota tier → kalau kuota habis, potong kredit
   → proses → sukses?
        ya  → catat di credit_transactions (type=usage)
        tidak → refund otomatis (type=refund)
```

Saat ini langkah "cek kuota tier" **dilewati** — kode langsung potong kredit.

---

## A.2 Tech Stack Final

| Layer | Pilihan | Terpasang | Catatan |
|---|---|---|---|
| Backend | PHP 8.2 native + PDO | ✓ | Tanpa framework, sesuai keputusan |
| Database | MySQL/MariaDB 10.4 | ✓ | XAMPP saat dev |
| Frontend | React 19 | ✓ 19.2.8 | |
| Build | Vite 5 + TypeScript 5.6 | ✓ | |
| Styling | Tailwind | ⚠ **v3.4.19, blueprint minta v4** | Perlu keputusan: upgrade atau revisi blueprint |
| Animasi | Framer Motion | ✗ **belum diinstal** | Animasi digit-lock skor belum bisa dibuat |
| Routing | react-router-dom 6 | ✓ | |
| Payment | Midtrans Snap | ✗ | Belum ada kode sama sekali |
| AI (fase lanjut) | Groq + Gemini | ✗ | Di luar scope fase 1 |

**Temuan penting:** Tailwind sudah dikonfigurasi (`tailwind.config.js` memetakan CSS variable ke token warna) **tapi hampir tidak dipakai** — keempat view memakai `style={{...}}` inline. Sistem tema jadi setengah jalan: warna dari DB masuk ke CSS variable, tapi komponen tidak konsisten memakainya.

---

## A.3 Struktur Database (31 tabel — SUDAH SELESAI)

### Grup 1 — Inti & User (5 tabel)
```
users                 ✓ role: super_admin/admin/support/user, theme_preference JSON
user_sessions         ✓ tabel ada, TIDAK dipakai (Auth pakai $_SESSION native)
system_settings       ✓ key-value + value_type (string/number/boolean/json)
feature_flags         ✓
activity_logs         ✓ tabel ada, belum dipakai
```

### Grup 2 — Saham & Fundamental (9 tabel)
```
stocks                          ✓ + is_syariah, market_cap, npwp, listing_date
sectors                         ✓ unique (name, sub_sector)
formula_config                  ✓ + formula_expression untuk dokumentasi rumus
indicator_snapshot_fundamental  ✓ 20 metrik + altman_z, beneish_m, piotroski_f, graham_number
indicator_history_fundamental   ✓ raw line-item (account_name, parent_account_id, statement_type)
shareholder_composition         ✓ + badge (Pengendali/Komisaris/Direksi)
stock_management                ✓ direksi & komisaris
corporate_actions               ✓ earnings/dividend/ipo/split
price_seasonality               ✓ pola musiman per bulan
api_response_cache              ✓ tabel ada, belum dipakai
```

### Grup 3 — Membership & Kredit (8 tabel)
```
subscription_tiers    ✓ screening_quota NULL = unlimited
user_subscriptions    ✓
credit_wallets        ✓ ⚠ balance int signed, tidak ada CHECK >= 0
credit_transactions   ✓ type: topup/usage/refund/bonus/trial
credit_packages       ✓
credit_costs          ✓
payments              ✓ Midtrans order id & transaction id
trial_usage           ✓
```

### Grup 4 — Coming Soon & Navigasi (4 tabel)
```
nav_menu                    ✓ status: active/coming_soon
coming_soon_items           ✓ progress_percent, eta_label
coming_soon_votes           ✓ unique (item, user)
coming_soon_subscriptions   ✓ unique (item, email)
```

### Grup 5 — Vendor & Lain (5 tabel)
```
data_vendors        ✓ auth_type: header_key/bearer, daily_quota
vendor_usage_log    ✓ unique (vendor, date)
watchlists          ✓ tabel ada, belum dipakai
theme_presets       ✓ primary/accent/background/card + radius
(price_seasonality dihitung di grup 2)
```

### Catatan kualitas skema

**Yang sudah bagus:** semua unique key yang diandalkan kode (`ON DUPLICATE KEY`, penanganan error 23000) benar-benar ada; 23 FK dengan ON DELETE yang dipikirkan; konsisten InnoDB + utf8mb4_unicode_ci.

**Yang perlu diperbaiki:**
| Masalah | Dampak | Saran |
|---|---|---|
| `credit_transactions`/`payments` CASCADE dari `users` | Hapus user = riwayat keuangan lenyap | Ubah ke RESTRICT + soft delete |
| `credit_wallets.balance` signed tanpa CHECK | Saldo bisa minus | `UNSIGNED` atau `CHECK (balance >= 0)` |
| `sectors` unique (name, sub_sector) dengan sub_sector NULL | NULL tidak menahan duplikat di MariaDB | Default `''` bukan NULL |
| `trial_usage.trial_ends_at` berulang tiap baris harian | Redundan | Pindah ke `users` |
| `data_vendors.api_key` dikomentari "terenkripsi" | Kode membacanya mentah | Implementasi enkripsi atau ubah komentar |
| Dump tanpa `CREATE DATABASE`/`DROP TABLE IF EXISTS` | Re-import butuh drop manual | Tambah di file `schema.sql` versi repo |

---

## A.4 Peta Endpoint Backend

Legenda: ✅ selesai · 🟡 sebagian · ❌ belum ada · 🔓 tanpa proteksi

### Auth & User
| Endpoint | Status | Catatan |
|---|---|---|
| `POST /api/auth/register.php` | ✅ | Validasi + kredit trial |
| `POST /api/auth/login.php` | ✅ | |
| `POST /api/auth/logout.php` | ✅ | |
| `GET /api/users/me.php` | ✅ | + saldo kredit |
| `POST /api/auth/forgot-password.php` | ❌ | |
| `POST /api/auth/reset-password.php` | ❌ | |
| `POST /api/auth/verify-email.php` | ❌ | Kolom `email_verified_at` sudah ada |
| `PUT /api/users/profile.php` | ❌ | Kolom phone/bio/photo_url sudah ada |
| `PUT /api/users/change-password.php` | ❌ | |
| `GET /api/users/sessions.php` | ❌ | Tabel `user_sessions` sudah ada |

### Fundamental & Saham
| Endpoint | Status | Catatan |
|---|---|---|
| `GET /api/stocks/list.php` | ✅ | Search + limit |
| `GET /api/fundamental/screening.php` | ✅ | 5 filter, whitelist sort aman |
| `GET /api/fundamental/snapshot.php` | ✅ | Detail + shareholder |
| `GET /api/fundamental/history.php` | ❌ | Untuk chart tren |
| `GET /api/fundamental/compare.php` | ❌ | Bandingkan antar emiten |
| `GET /api/stocks/sectors.php` | ❌ | Untuk dropdown filter sektor |
| `GET /api/stocks/corporate-actions.php` | ❌ | Tabel sudah ada |

### Watchlist — **seluruh modul belum ada**
| Endpoint | Status |
|---|---|
| `GET /api/watchlist/list.php` | ❌ |
| `POST /api/watchlist/add.php` | ❌ |
| `DELETE /api/watchlist/remove.php` | ❌ |

### Membership, Kredit & Pembayaran
| Endpoint | Status | Catatan |
|---|---|---|
| `GET /api/credits/balance.php` | ✅ | |
| `GET /api/credits/history.php` | ❌ | Tabel transaksi sudah ada |
| `GET /api/membership/tiers.php` | ❌ | |
| `GET /api/membership/my-subscription.php` | ❌ | |
| `GET /api/membership/packages.php` | ❌ | |
| `POST /api/payments/create.php` | ❌ | Midtrans Snap token |
| `POST /api/payments/webhook.php` | ❌ | **Kritikal** — tanpa ini pembayaran tidak pernah terkonfirmasi |
| `GET /api/payments/history.php` | ❌ | |

### Coming Soon & Tema
| Endpoint | Status |
|---|---|
| `GET /api/coming-soon/list.php` | ✅ |
| `POST /api/coming-soon/vote.php` | ✅ |
| `POST /api/coming-soon/subscribe.php` | ✅ |
| `GET /api/theme/presets.php` | ✅ |
| `POST /api/theme/save-preference.php` | ✅ |
| `GET /api/nav/menu.php` | ❌ | Sidebar dinamis dari `nav_menu` |

### Panel Admin — 2 dari ±14 modul
| Modul | Status | Catatan |
|---|---|---|
| `api/admin/settings.php` | 🟡🔓 | Jalan, **tanpa autentikasi** |
| `api/admin/run-sync.php` | 🟡🔓 | Jalan, **tanpa autentikasi** |
| `api/admin/users/*` | ❌ | Folder kosong |
| `api/admin/roles/*` | ❌ | Folder kosong |
| `api/admin/stocks/*` | ❌ | Folder kosong |
| `api/admin/sectors/*` | ❌ | Folder kosong |
| `api/admin/vendors/*` | ❌ | Folder kosong |
| `api/admin/tiers/*` | ❌ | |
| `api/admin/credit-packages/*` | ❌ | |
| `api/admin/credit-costs/*` | ❌ | |
| `api/admin/formula/*` | ❌ | Editor rumus & threshold |
| `api/admin/nav-menu/*` | ❌ | |
| `api/admin/coming-soon/*` | ❌ | |
| `api/admin/feature-flags/*` | ❌ | |
| `api/admin/payment-gateway/*` | ❌ | |
| `api/admin/dashboard.php` | ❌ | Statistik & health |

---

## A.5 Job Sinkronisasi

| Job | Status | Catatan |
|---|---|---|
| `sync_stocks.php` | ✅ | Master saham dari Invezgo. ⚠ N+1 query sektor, tanpa transaksi |
| `sync_fundamental.php` | ✅ | Batch-resumable, anti-timeout, stop setelah 5 gagal berturut-turut — **desain bagus** |
| `recalculate_scores.php` | ✅ | Hitung ulang skor tanpa panggil vendor |
| `sync_shareholder.php` | ❌ | Tabel & endpoint pembaca sudah ada, datanya tidak pernah terisi |
| `sync_financial_statement.php` | ❌ | Untuk `indicator_history_fundamental` + formula lanjutan |
| `sync_corporate_actions.php` | ❌ | |
| `sync_seasonality.php` | ❌ | |
| `assign_ratings.php` | ❌ | Kolom `rating` dibaca API tapi tidak pernah diisi |
| `cleanup_cache.php` | ❌ | Bersihkan `api_response_cache` kedaluwarsa |

**Keterbatasan data yang sudah terdokumentasi di kode:** DataSectors sedang expired (401), jadi sementara hanya Invezgo. Untuk sebagian emiten, field arus (Revenue, Net Profit) hanya terisi di tahun terbaru — karena itu growth & margin sengaja dibiarkan NULL agar tidak menyesatkan. Keputusan ini benar dan harus dipertahankan.

---

## A.6 Mesin Skor Fundamental

### Yang sudah jalan
Skor komposit 0–100 dari normalisasi linier terhadap `threshold_good`/`threshold_bad`, dibobot per `weight`, dibaca dari `formula_config`. Lima metrik aktif: ROE (bobot 2.0), ROA (1.0), DER (1.5), PER (1.0), PBV (1.0).

### Yang belum
| Item | Kolom DB | Catatan |
|---|---|---|
| Rating tekstual | `rating` | Dibaca API, tidak pernah diisi. Perlu pemetaan skor → label |
| Altman Z-Score | `altman_z_score` | Butuh raw line-item |
| Beneish M-Score | `beneish_m_score` | Butuh raw line-item |
| Piotroski F-Score | `piotroski_f_score` | Butuh raw line-item |
| Graham Number | `graham_number` | Butuh EPS + BVPS |
| Perbandingan vs sektor | — | Skor relatif terhadap rata-rata industri |
| Skor pembanding vendor | `vendor_insight_score` | Dari DataSectors insights |

**Prasyarat 4 formula lanjutan:** job `sync_financial_statement.php` + pemetaan nama akun. Ini bagian tersulit karena istilah laporan keuangan Indonesia bervariasi antar emiten — perlu tabel kamus akun.

---

## A.7 Frontend

### Yang sudah ada (4 view)
| File | Fungsi | Kualitas |
|---|---|---|
| `AuthPages.tsx` | Login + register dalam satu komponen | Fungsional, inline style |
| `DashboardView.tsx` | Sambutan, saldo, switcher tema | Sangat minimal — placeholder |
| `FundamentalScreenerView.tsx` | 3 filter + tabel hasil | Fungsional |
| `FundamentalDetailView.tsx` | Detail emiten + shareholder | Fungsional |

Plus: `AuthContext`, `ThemeContext` (CSS variable injection), `apiClient` wrapper, `api/auth.ts`, `api/fundamental.ts`.

### Yang belum ada
| Item | Folder | Status |
|---|---|---|
| Layout & Sidebar | `components/layout/` | **Kosong** — tidak ada navigasi sama sekali |
| Komponen UI reusable | `components/ui/` | **Kosong** |
| Halaman admin | `src/admin/pages/` | **Kosong** |
| Type definitions | `src/types/` | Kosong |
| Utilities | `src/utils/` | Kosong |
| Halaman Coming Soon + Vote | — | Backend siap, UI belum ada |
| Watchlist | — | Belum ada di kedua sisi |
| Landing page | — | Blueprint menyebut referensi stockdataengine |
| Halaman Pricing / Top-up | — | |
| Profil & Pengaturan akun | — | |
| Theme customizer (color picker) | — | Baru preset switcher |
| Auto-switch tema per waktu | — | Bab 8.3 blueprint lama |
| Animasi digit-lock skor | — | Butuh Framer Motion |
| Command palette ⌘K | — | Bab 8.5 |
| Donut chart & Fear/Greed gauge | — | Bab 8.5 |

### Masalah struktural frontend
1. **Tidak ada di Git** — hanya hidup di dalam `htdocs.zip`
2. **Tailwind terkonfigurasi tapi tidak dipakai** — semua styling inline
3. **Tidak ada error boundary** dan tidak ada penanganan 401 terpusat di `apiClient`
4. **Tidak ada loading skeleton** — hanya teks "Memuat..."

---

## A.8 Keamanan — Daftar Wajib Sebelum Produksi

| # | Item | Status | Prioritas |
|---|---|---|---|
| 1 | `AdminAuth::requireAdmin()` di semua `api/admin/*` | ❌ | **Kritikal** |
| 2 | Proteksi folder `jobs/` (CLI-only atau token) | ❌ | **Kritikal** |
| 3 | Atomisitas transaksi kredit | ❌ | **Kritikal** |
| 4 | Rate limiting login (anti brute-force) | ❌ | Tinggi |
| 5 | CSRF token untuk POST | ❌ | Tinggi |
| 6 | Enkripsi `api_key` vendor | ❌ | Tinggi |
| 7 | CORS dari `system_settings`, bukan hardcode | ❌ | Sedang |
| 8 | Cookie `SameSite=None; Secure` untuk beda domain | ❌ | Sedang |
| 9 | Kredensial DB via `.env`, bukan file kode | ❌ | Sedang |
| 10 | `activity_logs` diisi untuk aksi sensitif | ❌ | Sedang |
| 11 | Validasi ukuran & tipe upload foto profil | ❌ | Rendah |
| 12 | Header keamanan (HSTS, X-Frame-Options) | ❌ | Rendah |

### Detail 3 masalah kritikal

**1. Endpoint admin terbuka**
```php
// api/admin/settings.php baris 3
// TODO: tambahkan AdminAuth::requireAdmin() setelah core/AdminAuth.php dibangun
```
Siapa pun bisa `POST` untuk mengubah seluruh `system_settings` — termasuk harga, kuota, dan jumlah kredit trial.

**2. Job bisa dipanggil dari browser**
`jobs/sync_fundamental.php` membaca `$_GET['offset']` dan `$_GET['batch']`, berada di dalam webroot XAMPP, tanpa cek login. Orang lain bisa memicunya berulang dan menguras kuota API vendor Anda.

**3. Race condition kredit**
```php
// core/CreditManager.php — pola read-then-write
$currentBalance = self::getBalance($userId);   // baca
$newBalance = $currentBalance + $amount;        // hitung
$update->execute([...]);                        // tulis
```
Dua request bersamaan bisa membuat saldo salah. Perbaikan:
```sql
UPDATE credit_wallets
SET balance = balance - :cost
WHERE user_id = :id AND balance >= :cost
```
lalu cek `rowCount()` di dalam transaksi.

---

## A.9 Panel Admin — Cakupan Lengkap

| Modul | Backend | Frontend |
|---|---|---|
| Dashboard admin (statistik, health) | ❌ | ❌ |
| Identitas situs (nama, logo, favicon) | 🟡 via settings | ❌ |
| User Management (suspend, reset, login-as) | ❌ | ❌ |
| Role & Permission (Super Admin + Admin) | ❌ | ❌ |
| Stock Master (CRUD saham) | ❌ | ❌ |
| Sector Master (CRUD sektor) | ❌ | ❌ |
| Vendor / Market Data (API key, kuota, latency) | ❌ | ❌ |
| Subscription Tiers | ❌ | ❌ |
| Credit Packages | ❌ | ❌ |
| Credit Costs per aksi | ❌ | ❌ |
| Pengaturan Trial | 🟡 via settings | ❌ |
| Formula Fundamental (rumus, bobot, threshold) | ❌ | ❌ |
| Nav Menu & Coming Soon | ❌ | ❌ |
| Feature Flags | ❌ | ❌ |
| Payment Gateway (Midtrans) | ❌ | ❌ |
| Disclaimer & konten legal | 🟡 via settings | ❌ |
| Activity Logs viewer | ❌ | ❌ |
| Tombol Sync manual | ✅🔓 | ❌ |

**Catatan:** skema tidak punya tabel `payment_gateways` (disebut di blueprint lama bab 2) maupun tabel role/permission. Kredensial Midtrans bisa ditaruh di `system_settings` grup `payment`; role saat ini cukup lewat enum `users.role` yang sudah punya 4 nilai.

---

## A.10 Roadmap Terurut

### Tahap 0 — Fondasi bisa jalan (prasyarat semua)
- [ ] `database/seed.sql` untuk 6 tabel config yang kosong
- [ ] `.gitignore` + keluarkan `aigen-frontend/` dari zip ke repo
- [ ] README setup yang benar
- [ ] Hapus `htdocs.zip` dari tracking

### Tahap 1 — Tutup lubang keamanan
- [ ] `core/AdminAuth.php` + pasang di semua endpoint admin
- [ ] Proteksi folder `jobs/`
- [ ] Perbaiki atomisitas `CreditManager`
- [ ] Rate limiting login

### Tahap 2 — Lengkapi Fundamental Engine
- [ ] `sync_shareholder.php` (tabel sudah dibaca API tapi kosong)
- [ ] `assign_ratings.php` + pemetaan skor → label
- [ ] Endpoint sektor untuk filter
- [ ] Endpoint history untuk chart

### Tahap 3 — Watchlist (menu blueprint berstatus "Aktif" tapi 0%)
- [ ] 3 endpoint + UI

### Tahap 4 — Membership & Pembayaran
- [ ] Cek kuota tier sebelum potong kredit
- [ ] Endpoint tiers, packages, subscription
- [ ] Integrasi Midtrans Snap + webhook
- [ ] Halaman pricing & top-up

### Tahap 5 — Panel Admin
- [ ] Layout admin + 14 modul CRUD

### Tahap 6 — Frontend matang
- [ ] Layout + sidebar dinamis dari `nav_menu`
- [ ] Halaman Coming Soon + Vote/Subscribe
- [ ] Migrasi inline style → Tailwind
- [ ] Framer Motion + animasi skor
- [ ] Landing page

### Tahap 7 — Formula lanjutan
- [ ] `sync_financial_statement.php` + kamus nama akun
- [ ] Altman Z, Beneish M, Piotroski F, Graham Number

### Tahap 8 — Produksi
- [ ] Cache vendor, monitoring, backup, deploy

---

---

# BAGIAN B — AUDIT: YANG BELUM DIKERJAKAN

Dipetakan langsung ke bab `blueprint-saas-saham-fase1.md`.

---

## B.1 Bab 2 — Prinsip No-Hardcode

| Item | Status |
|---|---|
| Tabel penopang dibuat | ✅ 10/10 |
| Helper `Settings::get()` | ✅ |
| Helper `FeatureFlag::isActive()` | ✅ dibuat, ❌ **tidak pernah dipanggil di endpoint mana pun** |
| Data konfigurasi terisi | ❌ 1 dari 10 tabel |
| Panel untuk mengeditnya | ❌ |

**Pelanggaran prinsip yang masih ada:** CORS origin, nama vendor, dan URL localhost masih hardcode.

## B.2 Bab 4 — Struktur Database
✅ **Selesai 100%.** Bahkan melebihi blueprint: ada `stock_management`, `corporate_actions`, `price_seasonality` yang tidak disebut di bab 4.

Satu tabel di blueprint tidak ada di skema: `payment_gateways` (bisa diganti `system_settings` grup payment).

## B.3 Bab 5 — Sidebar & Navigasi

| Menu | Target | Realita |
|---|---|---|
| Dashboard | Aktif | 🟡 Halaman ada tapi isinya placeholder |
| Fundamental Screener | Aktif | ✅ |
| Watchlist | Aktif | ❌ **0% — belum ada apa pun** |
| Portfolio | Aktif | ❌ **0% — bahkan tabelnya tidak ada di skema** |
| Settings/Account | Aktif | ❌ |
| 5 engine Coming Soon | Tampil + Vote | 🟡 Backend ✅, UI ❌ |
| **Sidebar itu sendiri** | Dinamis dari `nav_menu` | ❌ **Komponen layout belum dibuat** |

**Catatan:** blueprint menyebut Portfolio sebagai menu Aktif, tapi tidak ada tabel `portfolios` di skema maupun kode. Perlu diputuskan: masuk fase 1 atau geser ke fase 2.

## B.4 Bab 6 — Membership & Kredit

| Sub-sistem | Status |
|---|---|
| Wallet + saldo | ✅ (⚠ race condition) |
| Catatan transaksi | ✅ |
| Potong kredit per aksi | ✅ (⚠ cost=0 → gratis diam-diam) |
| Refund otomatis | ✅ |
| Kredit trial saat daftar | ✅ (butuh `system_settings` terisi) |
| **Kuota tier** | ❌ Alur "cek kuota ATAU potong kredit" belum ada |
| **Trial berbasis hari** | ❌ `trial_usage` tidak pernah ditulis |
| **Tier & langganan** | ❌ Tabel kosong, kode nol |
| **Paket top-up** | ❌ |
| **Midtrans** | ❌ Termasuk webhook — tanpa ini pembayaran tidak terkonfirmasi |

## B.5 Bab 7 — Vendor API

| Kebutuhan | Status |
|---|---|
| Arsitektur multi-vendor | ✅ |
| Invezgo client | ✅ 5 method |
| DataSectors client | 🟡 4 method, langganan expired |
| Sync master saham | ✅ |
| Sync fundamental | ✅ |
| Sync shareholder | ❌ Method client ada, job tidak ada |
| Sync corporate actions | ❌ |
| Sync financial statement | ❌ |
| Cache respons (`api_response_cache`) | ❌ |
| Cek `daily_quota` | ❌ Kolom ada, tidak pernah dicek |
| Monitoring latency/error | ❌ |

## B.6 Bab 8 — Theme Customizer

| Fitur | Status |
|---|---|
| Tabel `theme_presets` | ✅ |
| Endpoint presets + simpan preferensi | ✅ |
| ThemeContext + CSS variable | ✅ |
| **8 preset terisi** | ❌ Tabel kosong |
| **Custom color picker** | ❌ |
| **Pilihan radius** | ❌ Kolom ada, UI tidak |
| **Auto-switch per waktu** | ❌ |
| Donut chart, Fear/Greed gauge, ⌘K | ❌ |

## B.7 Bab 10 — Panel Admin
**Progres ±15%.** 2 dari 18 item, keduanya tanpa autentikasi. Frontend admin 0%.

## B.8 Bab 11 — Roadmap

| Tahap blueprint | Status |
|---|---|
| 1. Setup + skema database | ✅ |
| 2. Auth + Settings/Panel dasar | 🟡 Auth ✅, panel 15% |
| 3. Sinkron data + skor | 🟡 ~40% |
| 4. Screening + Watchlist | 🟡 Screening ✅, Watchlist ❌ |
| 5. Membership + Midtrans | ❌ ~30%, Midtrans 0% |
| 6. Frontend lengkap | 🟡 ~25% |
| 7. Testing end-to-end + deploy | ❌ Belum ada satu pun test |
| 8. Iterasi coming soon | ❌ |

## B.9 Bab 12 — Pertanyaan yang Masih Terbuka

Empat pertanyaan ini **masih belum terjawab** dan memblokir pembuatan seed:

1. **Aksi apa saja yang dipotong kredit?** — `credit_costs` tidak bisa diisi tanpa ini
2. **Berapa jumlah & durasi trial?** — `system_settings` tidak bisa diisi tanpa ini
3. **Apakah tier Free tetap ada setelah trial habis?** — menentukan struktur `subscription_tiers`
4. **Nama produk final** — sudah terjawab: AIGen

---

## B.10 Hal yang Tidak Disebut Blueprint tapi Wajib Ada

| Item | Alasan |
|---|---|
| Halaman error 404/500 | Wajib untuk produk publik |
| Reset password | Standar minimum |
| Verifikasi email | Kolom sudah ada, mencegah akun sampah |
| Backup database terjadwal | Data hasil sync mahal (kuota API) |
| Log error terpusat | `storage/logs/` ada tapi kosong |
| Health check endpoint | Untuk monitoring uptime |
| Kebijakan privasi & ToS | Kewajiban hukum produk berbayar |
| Testing | Nol test — untuk logic kredit/refund ini berisiko |

---

# LAMPIRAN — Inventaris File Saat Ini

```
aigen/
├── aigen_db.sql                    ✅ 31 tabel (structure-only)
├── blueprint-saas-saham-fase1.md   dokumen asli
├── referensi-endpoint-fase1-*.md   pemetaan 196 endpoint vendor
├── htdocs.zip                      ⚠ 21 MB, 4.255 file node_modules
├── README.md                       ⚠ 1 baris
└── aigen-backend/                  29 file PHP
    ├── config/     bootstrap, cors, database
    ├── core/       Auth, CreditManager, Settings, FeatureFlag, Response
    │   └── VendorClient/  DataSectorsClient, InvezgoClient
    ├── api/        15 endpoint (8 folder kosong)
    └── jobs/       3 job + 1 seed SQL

Hanya di dalam zip (belum masuk Git):
└── aigen-frontend/  4 view, 2 context, 3 api module
```

**Semua 29 file PHP lolos `php -l`** — tidak ada syntax error.

---

*Dokumen ini dibuat berdasarkan pembacaan langsung seluruh kode, skema, dan isi arsip pada commit `09bd007`.*
