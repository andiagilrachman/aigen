# Blueprint AIGen — SaaS Analisis Saham IHSG — Fase 1
## Fundamental Engine (Lengkap) + 5 Engine Lain (Coming Soon) + Sistem Membership/Kredit

**Nama produk:** AIGen · **Nama database:** `aigen_db`

---

## 1. Ringkasan Project

Membangun SaaS analisis saham IHSG **AIGen** dari awal (codebase baru), dengan strategi bertahap:

- **Fase 1 (dibangun penuh)**: Engine Fundamental — screening, rating, database, sinkron data dari vendor
- **Ditampilkan di sidebar tapi "Coming Soon"**: Technical, Smart Money, Money Flow, Risk, AI Engine — lengkap dengan mekanisme Vote & Subscribe supaya user bisa menyuarakan prioritas dan production tetap terasa sebagai produk besar sejak hari pertama
- **Model bisnis**: hybrid kredit (sekali pakai) + langganan (kuota/akses tier)
- **Prinsip arsitektur**: NO HARDCODE — semua yang bisa berubah (harga, kuota, teks, vendor, feature flag, tampilan) diatur dari Panel Admin, bukan ditulis di kode

---

## 2. Prinsip No-Hardcode (Fondasi Arsitektur)

Karena dikerjakan solo, prinsip ini menentukan apakah proyek bisa dirawat jangka panjang tanpa harus bongkar kode tiap ada perubahan kecil. Aturannya:

**Kalau sebuah nilai BISA berubah tanpa mengubah cara kerja sistem → nilai itu WAJIB ada di database/panel, bukan di kode.**

Tabel inti yang menopang prinsip ini:

| Tabel | Isi | Contoh yang tersimpan |
|---|---|---|
| `system_settings` | Key-value pengaturan global | nama situs, logo, trial_days, trial_credit_amount, kredit per aksi, disclaimer text |
| `feature_flags` | Aktif/nonaktif fitur | `fundamental_screener: true`, `technical_engine: false` (coming soon) |
| `data_vendors` | Konfigurasi tiap provider data (API key, base URL, kuota, status) | DataSectors, Invezgo — bisa tambah vendor baru tanpa deploy ulang |
| `subscription_tiers` | Nama, harga, kuota, fitur per tier | Free/Basic/Pro — bisa ubah harga & kuota kapan saja |
| `credit_packages` | Paket top-up kredit | nominal, harga, bonus |
| `credit_costs` | Biaya kredit per jenis aksi | screening = 1 kredit, detail saham = 1 kredit (bisa diubah tanpa edit kode) |
| `nav_menu` | Struktur sidebar (termasuk status Coming Soon, progress %, eta) | dinamis, bisa reorder/tambah menu dari panel |
| `coming_soon_items` | Metadata tiap fitur yang belum rilis | judul, deskripsi, progress %, eta, vote count |
| `payment_gateways` | Konfigurasi gateway pembayaran | credential Midtrans, mode sandbox/production |

**Satu-satunya pengecualian yang boleh tetap di file config**: kredensial koneksi database itu sendiri (karena dibutuhkan sebelum koneksi ke DB terbuka).

**Konsekuensi teknis**: setiap modul backend membaca nilai lewat helper `Settings::get('key')` / `FeatureFlag::isActive('key')`, bukan konstanta di kode. Setiap harga/biaya dihitung dari tabel, bukan angka tertulis langsung (magic number) di logic.

---

## 3. Tech Stack (Rekomendasi)

Mengingat pelajaran dari histori project sebelumnya (terutama ganti-ganti stack di 4IGen), fase 1 sebaiknya **satu stack final, tidak berubah lagi**:

| Layer | Pilihan | Alasan |
|---|---|---|
| Backend | PHP native (PDO) | Sudah paling matang & teruji di stockdataengine.com — auth, kredit, payment, refund logic semua sudah terbukti jalan |
| Frontend | React 19 + Vite + TypeScript + Tailwind 4 + Framer Motion | Konsisten dengan seluruh template yang sudah dieksplorasi, mendukung animasi sci-fi yang diinginkan |
| Database | MySQL (via XAMPP saat development) | Sesuai preferensi yang sudah konsisten di semua project |
| Payment | Midtrans Snap | Sudah diintegrasikan & teruji di stockdataengine.com (QRIS/VA/e-wallet/kartu) |
| AI Provider (nanti, fase AI Engine) | Groq (primary, gratis) + Gemini (fallback) | Sudah dipakai di project-project sebelumnya, hemat biaya di awal |

> Desain visual: kulit sci-fi/glassmorphism dari 4IGen + animasi digit-lock skor dari stockvision.id + halaman Landing/Auth/Referral dari stockdataengine.com (sudah dibahas sebelumnya).

---

## 4. Struktur Database — Fase 1

### 4.1 Grup Inti (User & Sistem)
```
users
user_sessions
system_settings
feature_flags
activity_logs
```

### 4.2 Grup Data Saham & Fundamental
```
stocks                          -- master data saham (kode, nama, sektor, dll)
sectors
formula_config                  -- daftar rumus fundamental + threshold, EDITABLE dari panel
indicator_snapshot_fundamental  -- snapshot rasio & skor fundamental terbaru per saham
indicator_history_fundamental   -- histori untuk chart tren
api_response_cache              -- cache respons vendor (hemat kuota)
```

### 4.3 Grup Membership & Kredit
```
subscription_tiers              -- Free/Basic/Pro: harga, kuota, fitur
user_subscriptions               -- status langganan aktif user
credit_wallets                  -- saldo kredit tiap user
credit_transactions             -- histori mutasi kredit (+/-), termasuk refund
credit_packages                 -- paket top-up yang bisa dibeli
credit_costs                    -- biaya kredit per jenis aksi (editable)
payments                        -- transaksi pembayaran (subscription & top-up)
trial_usage                     -- tracking pemakaian trial per user
```

### 4.4 Grup Coming Soon (Sidebar)
```
nav_menu                        -- struktur menu sidebar, termasuk status aktif/coming_soon
coming_soon_items               -- metadata: judul, deskripsi, progress%, eta
coming_soon_votes               -- vote per user per fitur
coming_soon_subscriptions       -- "beri tahu saya saat rilis"
```

### 4.5 Grup Vendor Data
```
data_vendors                    -- konfigurasi vendor (API key terenkripsi, kuota, status)
vendor_usage_log                -- tracking pemakaian kuota harian per vendor
```

---

## 5. Sidebar & Navigasi (Fase 1)

| Menu | Status | Sumber Data |
|---|---|---|
| Dashboard | Aktif | Ringkasan fundamental top saham |
| Fundamental Screener | Aktif | DataSectors + Invezgo (lihat bagian 7) |
| Watchlist | Aktif | Data lokal + skor fundamental |
| Technical Analysis | 🔒 Coming Soon | — |
| Smart Money / Bandarmology | 🔒 Coming Soon | — |
| Money Flow | 🔒 Coming Soon | — |
| Risk Engine | 🔒 Coming Soon | — |
| AI Strategy (Decision Engine, dst) | 🔒 Coming Soon | — |
| Portfolio | Aktif (basis cost, belum P&L live) | — |
| Settings / Account | Aktif | — |

Tiap item "Coming Soon" menampilkan: deskripsi singkat fitur, progress % (diisi manual dari panel), tombol **Vote** (menyatakan minat), tombol **Subscribe** (kirim notifikasi email saat rilis). Semua data ini dari tabel `coming_soon_items` — nambah/ubah fitur coming soon tidak perlu sentuh kode.

---

## 6. Sistem Membership & Kredit

### 6.1 Alur Keseluruhan
```
User baru daftar
   → dapat Trial (jumlah screening & durasi hari, diatur dari panel)
   → Trial habis
       → Opsi A: beli kredit (top-up sekali pakai)
       → Opsi B: subscribe tier bulanan/tahunan (kuota lebih besar/unlimited)
   → Tiap aksi (screening, buka detail saham) → cek kuota tier ATAU potong kredit
   → Kalau aksi gagal diproses → kredit di-refund otomatis
```

### 6.2 Contoh Struktur Tier (nilai dummy, semua editable dari panel)

| Tier | Harga | Kuota Screening/hari | Kredit Bonus |
|---|---|---|---|
| Free (Trial) | Rp0 | 3x, berlaku 3 hari | — |
| Basic | Rp49.000/bln | 30x/bln | +10 kredit |
| Pro | Rp149.000/bln | Unlimited | +50 kredit, akses awal fitur baru |

### 6.3 Biaya Kredit per Aksi (contoh, editable)

| Aksi | Biaya |
|---|---|
| Jalankan screening | 1 kredit |
| Lihat detail rating 1 saham | 1 kredit |
| Export laporan | 2 kredit |

> Pertanyaan yang masih perlu dijawab bersama: mana saja aksi yang dikenai kredit — perlu ditentukan sebelum tabel `credit_costs` diisi datanya.

### 6.4 Aturan Refund
Kalau proses gagal (misal vendor API error saat screening), kredit yang sudah terpotong dikembalikan otomatis ke `credit_wallets`, dicatat di `credit_transactions` dengan tipe `refund`. Pola ini sudah teruji di stockdataengine.com.

### 6.5 Panel Admin untuk Membership/Kredit
Semua di atas (harga tier, kuota, biaya kredit per aksi, jumlah trial) diedit lewat halaman **Settings → Membership & Kredit** — tidak ada angka yang ditulis langsung di kode manapun.

---

## 7. Vendor API — Fase 1 (Fundamental)

| Kebutuhan | Endpoint | Vendor |
|---|---|---|
| Pencarian saham | `/api/search/market`, `/api/stocks/v2/search` | DataSectors |
| Earnings & forecast | `/api/stocks/v2/earnings` | DataSectors |
| Profil & laporan keuangan | `/api/stocks/v2/equities` | DataSectors |
| Insight vs peer/industri | `/api/stocks/v2/insights` | DataSectors |
| Rasio kunci (PER/PBV/ROE/DER, histori 1994+) | `/api/stocks/v2/key-ratios` | DataSectors |
| Kalender earnings/dividen/IPO/split | `/api/stocks/earnings/events`, `/dividends/events`, `/ipo/events`, `/splits/events` | DataSectors |
| Laporan keuangan detail | `/analysis/financial-statement/{code}` | Invezgo (perlu dites granularitasnya untuk formula lanjutan) |
| Komposisi pemegang saham | `/analysis/shareholder/{code}`, `/shareholder/ksei/{code}` | Invezgo |

**Catatan yang masih perlu verifikasi**: formula lanjutan seperti Altman Z-Score, Beneish M-Score, Piotroski F-Score, Graham Number butuh raw line item — baru bisa dipastikan setelah tes langsung response `/analysis/financial-statement/{code}`.

Semua kredensial vendor (API key, base URL, kuota harian) disimpan di tabel `data_vendors`, bisa ganti/tambah vendor dari panel tanpa ubah kode manapun (pola `Settings.php` + `AdminAuth.php` yang sudah pernah dibangun).

---

## 8. Theme Customizer (Dark/Light + Custom)

Terinspirasi dari referensi tampilan Dashboard bergaya "AI Investment Intelligence Platform" — sistem tema dua tingkat, konsisten dengan prinsip no-hardcode.

### 8.1 Preset Theme
Pilihan cepat, ditampilkan sebagai swatch bulat: Dark, Light, Neon Blue, Purple, Ocean, Sunset, Forest, Cyber — dan bisa ditambah preset baru kapan saja dari panel tanpa deploy ulang.

### 8.2 Custom Theme
User bisa atur sendiri:
- Primary Color (color picker)
- Accent Color (color picker)
- Background mode (Dark/Light)
- Radius (Sharp/Medium/Rounded)

### 8.3 Auto Switch
Opsi otomatis ganti tema sesuai waktu (misal otomatis ke Dark setelah jam 18:00).

### 8.4 Skema Tabel

```
theme_presets                   -- preset bawaan & custom (nama, primary_color, accent_color, background_mode, radius, is_default)
users.theme_preference          -- preset aktif user + override custom (JSON: primary_color, accent_color, background, radius, auto_switch)
```

> Preset default (Dark, Light, dst) tetap disimpan sebagai baris di `theme_presets`, bukan hardcode di komponen React — supaya warna/preset bisa disesuaikan dari panel admin kapan saja.

### 8.5 Catatan Implementasi
- Widget globe 3D interaktif pada dashboard referensi **ditunda ke fase polish** (bukan fase 1) — berat secara performa dan tidak esensial untuk Fundamental Engine
- Elemen ringan (donut chart Market Summary/Breadth, Fear & Greed gauge, command palette search ⌘K) realistis dibangun di fase 1 karena mendukung kesan produk yang matang tanpa beban performa berarti

---

## 10. Panel Admin — Checklist Konfigurasi (No-Hardcode)

### 10.1 Fase 1 (dibangun penuh)
- [ ] Identitas situs (nama, logo, favicon, warna tema)
- [ ] Manajemen vendor data / Market Data (tambah/edit/nonaktifkan, API key, kuota, monitoring latency & error per provider — referensi: modul Market Data)
- [ ] Manajemen tier langganan (harga, kuota, fitur per tier)
- [ ] Manajemen paket kredit (nominal, harga, bonus)
- [ ] Biaya kredit per aksi
- [ ] Pengaturan trial (jumlah, durasi hari)
- [ ] Manajemen menu sidebar & status Coming Soon (progress %, eta, deskripsi)
- [ ] Manajemen formula fundamental (rumus, threshold, bobot skor) — supaya rumus bisa disesuaikan tanpa edit kode
- [ ] Payment gateway (kredensial Midtrans, mode sandbox/production)
- [ ] Disclaimer & konten legal
- [ ] Feature flags (aktif/nonaktifkan modul)
- [ ] **User Management** — daftar user lengkap dengan status (Active/Trial/Suspended), verifikasi email/phone, 2FA, riwayat aktivitas & langganan, aksi Reset Password/Suspend/Ban/Login as User (referensi: modul User Management)
- [ ] **Role & Permission** — permission matrix per modul (View/Create/Edit/Delete/Export/Import/Setting), role dasar Super Admin & Admin sesuai kesepakatan awal (referensi: modul Role & Permission, disederhanakan dari 8 role di referensi menjadi 2 dulu untuk fase 1)
- [ ] **Stock Master** — CRUD data saham lengkap (kode, nama, bursa, sektor, status aktif/nonaktif), tab detail Fundamental & Profil Perusahaan (referensi: modul Stock Master)
- [ ] **Sector Master** — CRUD sektor/industri/sub-industri, bobot IDX per sektor (referensi: modul Sector Master)

### 10.2 Disimpan untuk Fase Lanjutan (di luar scope fase 1)
Referensi berikut sengaja **ditunda**, bukan dibangun di fase 1, supaya fondasi Fundamental selesai dulu sebelum menambah kompleksitas:

- **Broker Master** — master data broker & partisipan pasar (kode, alias, tipe, keanggotaan bursa). Ini pendukung Smart Money/Bandarmology, jadi menyusul saat engine tersebut mulai dibangun (fase 2/3)
- **AI Engine / AI Model Management** — panel kelola multi-provider AI (GPT-4o, Claude, Gemini, DeepSeek, Llama, dst) dengan tracking token & biaya real-time per model. Jauh lebih kompleks dari rencana AI Strategy fase 1 (yang masih Groq+Gemini gratis) — disimpan sebagai referensi arsitektur untuk saat AI Strategy engine mulai dibangun (fase 4)
- **AI Prompt Builder** — editor prompt dengan template variabel ({{ticker}}, {{financial_report}}, dst), versioning, dan uji coba langsung. Relevan begitu AI Strategy/Decision Engine mulai dikerjakan
- **Admin Dashboard lengkap** (system health, queue monitor BullMQ, API usage per endpoint, storage usage) — versi sederhana cukup untuk fase 1, versi lengkap ini jadi acuan saat traffic sudah besar

---

## 11. Roadmap Ringkas

| Tahap | Isi |
|---|---|
| 1 | Setup project baru, skema database (grup Inti + Fundamental + Membership + Coming Soon) |
| 2 | Backend Auth + Settings/Panel Admin dasar (no-hardcode dari awal, bukan ditambah belakangan) |
| 3 | Sinkron data Fundamental dari DataSectors + Invezgo, hitung skor/rating |
| 4 | Endpoint Screening Fundamental + Watchlist |
| 5 | Sistem Membership & Kredit (trial, tier, top-up, refund) + integrasi Midtrans |
| 6 | Frontend: Dashboard, Fundamental Screener, Watchlist, Sidebar dengan Coming Soon + Vote/Subscribe |
| 7 | Testing end-to-end, deploy |
| 8+ | Iterasi fitur Coming Soon berikutnya berdasarkan data vote user asli |

---

## 12. Pertanyaan Terbuka (perlu diputuskan sebelum mulai coding)

1. Aksi apa saja yang dikenai potongan kredit? (screening, detail saham, export, dll)
2. Berapa jumlah & durasi trial yang diinginkan?
3. Apakah tier Free tetap ada setelah trial habis (dengan kuota sangat kecil), atau trial adalah satu-satunya akses gratis?
4. Nama produk final untuk fase 1 ini — tetap salah satu dari 3 nama sebelumnya, atau nama baru?
