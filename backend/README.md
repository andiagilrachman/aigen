# AIGen Backend

PHP 8 native + PDO, tanpa framework dan tanpa Composer. Autoload PSR-4 sendiri
(`src/Core/Autoloader.php`), namespace `Aigen\`.

## Menjalankan (XAMPP / MariaDB)

```bash
cp .env.example .env       # isi kredensial DB
```

Impor skema lalu seed ke MariaDB:

```bash
mysql -u root aigen_db < database/schema.sql
mysql -u root aigen_db < database/seed.sql
```

`seed.sql` bersifat idempoten (`ON DUPLICATE KEY UPDATE`) — aman dijalankan
berulang kali, tidak akan menggandakan baris.

Arahkan document root ke `public/`. `.htaccess` meneruskan semua permintaan ke
`public/index.php` sebagai satu-satunya pintu masuk, sehingga bootstrap, CORS,
penanganan error, dan guard dijamin selalu jalan.

## Menjalankan tanpa MySQL (pratinjau lokal)

Berguna saat mengembangkan frontend tanpa memasang XAMPP:

```bash
php tools/setup-preview-db.php storage/preview.sqlite
```

Skrip itu menerjemahkan `schema.sql` + `seed.sql` **yang sama persis dengan
produksi** ke SQLite, lalu menambahkan 6 emiten contoh beserta laporan keuangan,
pemegang saham, dan satu akun demo (`demo@aigen.test` / `demo1234`, saldo 100
kredit).

Lalu di `.env`:

```
DB_DRIVER=sqlite
DB_PATH=/path/absolut/ke/backend/storage/preview.sqlite
```

```bash
php -S 0.0.0.0:8080 -t public
```

SQLite hanya untuk pengembangan dan pengujian. Target produksi tetap MariaDB.

## Konfigurasi

Semua nilai dibaca dari `.env` lewat `config/config.php`. Kredensial database
adalah **satu-satunya hardcode yang diizinkan blueprint** — dan itu pun tetap
dipindahkan ke `.env` agar tidak masuk Git. Sisanya (biaya kredit, kuota tier,
menu, tema, feature flag) berada di database.

`APP_KEY` wajib diisi di produksi: dipakai mengenkripsi `data_vendors.api_key`.

## Endpoint

Publik:

| Method | Path             |
| ------ | ---------------- |
| GET    | `/health`        |
| GET    | `/config`        |
| POST   | `/auth/register` |
| POST   | `/auth/login`    |

Perlu sesi:

| Method | Path                 | Biaya                 |
| ------ | -------------------- | --------------------- |
| POST   | `/auth/logout`       | gratis                |
| GET    | `/auth/me`           | gratis                |
| GET    | `/navigation`        | gratis                |
| GET    | `/screener/options`  | gratis                |
| POST   | `/screener/run`      | kuota tier → 1 kredit |
| GET    | `/stocks`            | gratis                |
| GET    | `/stocks/{symbol}`   | 2 kredit              |
| GET    | `/credits/balance`   | gratis                |
| GET    | `/credits/history`   | gratis                |
| GET    | `/credits/packages`  | gratis                |

Semua respons memakai amplop yang sama:

```json
{ "success": true, "data": { }, "meta": { } }
{ "success": false, "error": { "message": "…", "code": "…", "fields": { } } }
```

Untuk aksi berbayar, `meta` berisi `charge_type`, `credits_charged`,
`credit_balance`, dan `quota_remaining`.

## Model kredit

Trial 7 hari + 100 kredit saat mendaftar. Setelah trial habis, pengguna jatuh ke
tier Free dengan kuota 5 screening/hari.

Setiap aksi berbayar **wajib** lewat `UsageGate`:

```php
$gate = UsageGate::open($userId, 'view_stock_detail');
try {
    // …kerjakan sesuatu…
    $gate->commit();
} catch (Throwable $e) {
    $gate->rollback('alasan');   // kredit dikembalikan
}
```

Urutan yang dijaga `UsageGate`: kuota langganan dipakai lebih dulu, kredit baru
dipotong bila kuota habis. Kuota tier hanya berlaku untuk `run_screening`.

Kredit dipotong **setelah** pekerjaan dipastikan bisa dilakukan — misalnya
`/stocks/{symbol}` memeriksa keberadaan emiten lebih dulu, karena memungut biaya
untuk kode yang tidak ada sama saja mengambil kredit tanpa imbalan.

## Job sinkronisasi data

Data emiten diambil dari vendor (Invezgo) lewat skrip CLI di `jobs/`. **Hanya
job yang boleh memanggil vendor** — request pengguna tidak pernah menyentuh API
luar, supaya halaman tidak ikut lambat/gagal saat vendor bermasalah.

```bash
php jobs/sync_stocks.php                    # master emiten + sektor
php jobs/sync_fundamental.php               # rasio, skor, (opsional) laporan
php jobs/recalculate_scores.php             # hitung ulang skor, tanpa vendor
```

Urutannya penting: `sync_fundamental` hanya memproses emiten yang sudah ada di
tabel `stocks`, jadi `sync_stocks` dijalankan lebih dulu.

### Argumen yang sering dipakai

| Argumen | Berlaku di | Guna |
|---|---|---|
| `--dry-run` | semua | tampilkan rencana, jangan tulis apa pun |
| `--symbol=BBCA` | sync_fundamental | proses satu emiten saja |
| `--batch=50 --offset=0` | sync_fundamental | potong pekerjaan jadi beberapa bagian |
| `--with-statements` | sync_fundamental | ikut ambil laporan keuangan (BS/IS/CF) |
| `--all` | recalculate_scores | semua tanggal, bukan snapshot terbaru saja |
| `--max-seconds=0` | semua | `0` = tanpa batas waktu (bawaan CLI) |

### Aman diulang

Semua job **idempoten**: menjalankan dua kali tidak menggandakan baris. Emiten
di-upsert, snapshot ditimpa per `(stock_id, snapshot_date)`, dan riwayat laporan
dihapus per `(emiten, jenis, tahun, periode)` sebelum ditulis ulang — karena
tabel `indicator_history_fundamental` sengaja tidak punya unique key.

### Kalau vendor bermasalah

`sync_fundamental` berhenti sendiri setelah 5 kegagalan berturut-turut, dan
langsung berhenti untuk masalah kredensial (401/403) atau kuota harian yang
habis — dua kondisi yang tidak akan membaik dengan mencoba emiten berikutnya.

Saat berhenti, job mencetak perintah untuk melanjutkan:

```
Lanjutkan dengan:
  php jobs/sync_fundamental.php --offset=12 --batch=100
```

Offset itu menunjuk ke **awal rentetan kegagalan**, bukan tempat job berhenti,
supaya emiten yang barusan gagal ikut dicoba lagi. Kalau memakai posisi terakhir,
kegagalan berubah jadi lubang data yang tidak kelihatan.

Kuota harian vendor dicatat di `vendor_usage_log` dan hanya bertambah kalau
permintaan benar-benar sampai ke vendor — kegagalan koneksi tidak ikut menghabiskan
jatah.

### Skor fundamental

Skor dihitung dari `formula_config` (bobot + ambang `good`/`bad` per metrik),
bukan dari angka yang ditanam di kode. Ubah bobot di tabel itu, lalu jalankan
`recalculate_scores.php` untuk menerapkannya ke snapshot yang sudah tersimpan —
tanpa perlu menarik ulang data dari vendor.

Nilai `0` dari vendor pada DER/PER/PBV/CR/QR diperlakukan sebagai **data tidak
tersedia**, bukan nol sungguhan, dan disimpan sebagai NULL. Metrik yang NULL
tidak ikut dihitung, dan jumlah metrik terpakai ditampilkan sebagai `(5/7 metrik)`
agar skor dengan data tipis mudah dikenali.

## Pengujian

```bash
php tests/run.php
```

86 assertion, memakai SQLite in-memory. `tests/TestSchema.php` menerjemahkan
`database/schema.sql` yang asli ke dialek SQLite, sehingga test menguji **skema
yang sebenarnya dipakai produksi** — bukan skema tiruan yang bisa menyimpang
diam-diam saat kolom berubah.
