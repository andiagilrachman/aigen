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

## Pengujian

```bash
php tests/run.php
```

51 assertion, memakai SQLite in-memory. `tests/TestSchema.php` menerjemahkan
`database/schema.sql` yang asli ke dialek SQLite, sehingga test menguji **skema
yang sebenarnya dipakai produksi** — bukan skema tiruan yang bisa menyimpang
diam-diam saat kolom berubah.
