# Menjalankan AIGen di PC (XAMPP + MariaDB)

Panduan dari nol sampai bisa login. Perkiraan 10–15 menit.

Yang perlu terpasang lebih dulu:

- **XAMPP** (Apache + MariaDB, PHP 8.1 ke atas) — <https://www.apachefriends.org>
- **Node.js 20 ke atas** — <https://nodejs.org>
- **Git** — <https://git-scm.com>

Cek versinya di terminal:

```bash
node -v      # v20 atau lebih baru
git --version
```

---

## 1. Clone repo

Kode rilis pertama ada di branch `arena/01a024b6-aigen`, **belum di `main`**.
Jadi jangan clone begitu saja — sebutkan branch-nya:

```bash
git clone -b arena/01a024b6-aigen https://github.com/andiagilrachman/aigen.git
cd aigen
```

Pastikan branch-nya benar:

```bash
git branch --show-current      # harus: arena/01a024b6-aigen
```

Kalau terlanjur clone tanpa `-b`, tinggal pindah:

```bash
git checkout arena/01a024b6-aigen
```

> Lokasi folder bebas — tidak harus di dalam `htdocs`. Kita akan mengarahkan
> Apache ke sini lewat konfigurasi, dan itu lebih rapi daripada menyalin file.

---

## 2. Buat database

Nyalakan **Apache** dan **MySQL** dari XAMPP Control Panel.

Buka <http://localhost/phpmyadmin> → tab **SQL**, lalu jalankan:

```sql
CREATE DATABASE aigen_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sekarang impor skema dan data awal. Lewat terminal lebih andal daripada upload
di phpMyAdmin (file seed cukup panjang):

**Windows** (sesuaikan bila XAMPP tidak di `C:\xampp`):

```bat
cd C:\xampp\mysql\bin
mysql -u root aigen_db < D:\path\ke\aigen\backend\database\schema.sql
mysql -u root aigen_db < D:\path\ke\aigen\backend\database\seed.sql
```

**macOS / Linux:**

```bash
mysql -u root aigen_db < backend/database/schema.sql
mysql -u root aigen_db < backend/database/seed.sql
```

Kalau root MySQL Anda berpassword, tambahkan `-p` (nanti diminta mengetik).

Verifikasi seed masuk:

```sql
SELECT COUNT(*) FROM nav_menu;        -- 10
SELECT COUNT(*) FROM theme_presets;   -- 8
SELECT COUNT(*) FROM credit_costs;    -- 8
```

`seed.sql` aman dijalankan berulang kali — memakai `ON DUPLICATE KEY UPDATE`,
jadi tidak akan menggandakan baris kalau Anda mengulanginya.

---

## 3. Konfigurasi backend

```bash
cd backend
cp .env.example .env        # Windows: copy .env.example .env
```

Buka `.env`, sesuaikan bagian database:

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=aigen_db
DB_USER=root
DB_PASSWORD=              # isi kalau root Anda berpassword
```

Lalu isi `APP_KEY` — dipakai mengenkripsi API key vendor di tabel `data_vendors`:

```bash
php -r "echo base64_encode(random_bytes(32));"
```

Salin hasilnya ke `APP_KEY=...`.

> `.env` tidak masuk Git (sengaja — berisi kredensial). Karena itu Anda perlu
> membuatnya sendiri di tiap mesin.

---

## 4. Arahkan Apache ke `backend/public`

Document root **harus** menunjuk ke `backend/public`, bukan ke folder `backend`.
Folder `public` satu-satunya yang boleh diakses dari luar; `src/`, `.env`, dan
`storage/` ada di atasnya supaya tidak bisa diunduh lewat browser.

Buka `C:\xampp\apache\conf\extra\httpd-vhosts.conf` dan tambahkan:

```apache
<VirtualHost *:80>
    DocumentRoot "D:/path/ke/aigen/backend/public"
    ServerName aigen.local

    <Directory "D:/path/ke/aigen/backend/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`AllowOverride All` wajib — tanpa itu `.htaccess` diabaikan dan semua URL selain
`/index.php` akan 404.

Tambahkan ke file hosts:

- Windows: `C:\Windows\System32\drivers\etc\hosts` (buka Notepad **as Administrator**)
- macOS/Linux: `/etc/hosts` (pakai `sudo`)

```
127.0.0.1   aigen.local
```

**Restart Apache** dari XAMPP Control Panel, lalu uji:

```bash
curl http://aigen.local/health
```

Harusnya keluar:

```json
{"success":true,"data":{"status":"ok","time":"...","routes":14}}
```

Kalau muncul 404 atau halaman XAMPP, lihat bagian *Kalau macet* di bawah.

---

## 5. Jalankan frontend

```bash
cd frontend
npm install
cp .env.example .env        # Windows: copy .env.example .env
```

Buka `frontend/.env` dan arahkan ke backend Anda:

```ini
VITE_BACKEND_ORIGIN=http://aigen.local
```

Lalu:

```bash
npm run dev
```

Buka <http://localhost:5173>.

Login dengan akun demo — tapi **hanya kalau Anda memakai database pratinjau**.
Kalau tadi impor ke MariaDB kosong, belum ada user sama sekali, jadi klik
**"Daftar gratis"** untuk membuat akun. Pendaftaran otomatis memberi trial 7 hari
+ 100 kredit.

> Yang akan Anda lihat: sidebar berisi 10 menu, Screener yang bisa dijalankan,
> tapi **tabel hasilnya kosong**. Itu wajar — `seed.sql` hanya mengisi
> konfigurasi (menu, tema, tarif kredit), bukan data emiten. Mengisi data saham
> butuh job sinkronisasi vendor yang belum dikerjakan (lihat bagian akhir).

---

## Cara kerja `/api` (supaya tidak bingung saat produksi)

Frontend tidak pernah memanggil backend secara langsung. Semua permintaan pergi
ke path relatif `/api/...`, dan Vite yang meneruskannya ke PHP:

```
browser  →  localhost:5173/api/health  →  [proxy Vite]  →  aigen.local/health
```

Dua alasan:

1. **Cookie sesi.** Karena bagi browser semuanya satu origin (`localhost:5173`),
   cookie login ikut terkirim otomatis tanpa perlu setelan CORS apa pun.
2. Browser Anda tidak selalu berada di mesin yang sama dengan backend.

Konsekuensinya saat **deploy produksi**: proxy Vite hanya hidup di `npm run dev`.
Pilih salah satu:

- **Satu origin (disarankan).** Taruh hasil `npm run build` (folder `dist/`) dan
  backend di domain yang sama, backend melayani `/api/*`. Tidak perlu CORS.
- **Beda domain.** Isi `CORS_ORIGINS` di `backend/.env` dengan domain frontend,
  lalu set `SESSION_SAMESITE=None` **dan** `SESSION_SECURE=true` (wajib HTTPS).
  Kalau tidak keduanya, browser modern akan membuang cookie sesi dan Anda akan
  ter-logout terus tanpa pesan error yang jelas.

---

## Menguji tanpa MySQL

Kalau sekadar ingin melihat tampilannya tanpa menyiapkan XAMPP:

```bash
cd backend
php tools/setup-preview-db.php storage/preview.sqlite
```

Skrip ini membangun SQLite dari `schema.sql` + `seed.sql` **yang sama persis**
dengan produksi, ditambah 6 emiten contoh (BBCA, ICBP, ANTM, TLKM, PTBA, GOTO)
lengkap dengan laporan keuangan dan pemegang saham, plus akun demo.

Di `backend/.env`:

```ini
DB_DRIVER=sqlite
DB_PATH=/path/absolut/ke/aigen/backend/storage/preview.sqlite
```

Jalankan server bawaan PHP (tidak perlu Apache):

```bash
php -S 127.0.0.1:8080 -t public
```

Set `VITE_BACKEND_ORIGIN=http://127.0.0.1:8080` di `frontend/.env`, lalu
`npm run dev`. Login: **`demo@aigen.test` / `demo1234`** (100 kredit).

Mode ini untuk pengembangan saja. Target produksi tetap MariaDB.

---

## Menjalankan test

```bash
cd backend  && php tests/run.php     # 51 assertion
cd frontend && npm run verify        # typecheck + build + 28 pemeriksaan UI
```

`npm run verify` menjalankan aplikasi hasil build di dalam DOM tiruan memakai
rekaman respons backend sungguhan — menangkap layar putih akibat error runtime,
yang tidak bisa dilihat oleh `tsc`.

---

## Kalau macet

**`curl http://aigen.local/health` → 404 atau halaman XAMPP**
Apache belum memakai vhost Anda. Pastikan di `httpd.conf` baris ini tidak
dikomentari: `Include conf/extra/httpd-vhosts.conf`. Lalu restart Apache.

**Semua URL 404 kecuali halaman depan**
`.htaccess` diabaikan. Pastikan `AllowOverride All` ada di blok `<Directory>`,
dan `mod_rewrite` aktif (`LoadModule rewrite_module modules/mod_rewrite.so`
tidak dikomentari di `httpd.conf`).

**"SQLSTATE[HY000] [1045] Access denied for user 'root'"**
`DB_PASSWORD` di `.env` tidak cocok dengan MySQL Anda.

**"Base table or view not found"**
`schema.sql` belum terimpor, atau terimpor ke database lain. Cek `DB_NAME`.

**Frontend jalan tapi semua permintaan gagal / layar error konfigurasi**
`VITE_BACKEND_ORIGIN` salah, atau backend mati. Uji langsung dulu:
`curl http://aigen.local/health`. **Vite tidak membaca ulang `.env` dengan
sempurna saat berjalan** — hentikan `npm run dev` lalu jalankan lagi setelah
mengubahnya.

**Login berhasil tapi langsung ter-logout**
Cookie sesi tidak tersimpan. Saat dev, pastikan Anda mengakses lewat
`localhost:5173` (bukan IP), sehingga proxy membuatnya same-origin.

**Port 5173 sudah dipakai**
`npm run dev -- --port 5174`.

---

## Yang belum ada

Rilis ini sengaja dibatasi pada satu alur: **Login → Sidebar → Screener →
Detail emiten**. Belum dikerjakan:

- **Job sinkronisasi data vendor** — karena itu tabel emiten masih kosong di
  database baru. Ini yang membuat Screener belum menampilkan hasil apa pun.
- Pembayaran Midtrans dan pembelian kredit
- Panel admin
- Watchlist
- 5 engine selain Fundamental (tampil sebagai "Coming Soon", diambil dari
  tabel `coming_soon_items`)
