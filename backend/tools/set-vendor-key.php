<?php

/**
 * Menyimpan API key vendor ke tabel data_vendors dalam keadaan terenkripsi.
 *
 * Kolom api_key tidak boleh diisi lewat phpMyAdmin dalam bentuk teks biasa:
 * aplikasi mendekripsinya memakai APP_KEY, jadi nilai mentah akan gagal dibaca
 * dan pesannya tidak mudah ditebak. Skrip ini memastikan formatnya benar.
 *
 * Jalankan dari folder backend:
 *
 *   php tools/set-vendor-key.php Invezgo API_KEY_ANDA
 *   php tools/set-vendor-key.php --list
 */

declare(strict_types=1);

use Aigen\Core\App;
use Aigen\Core\Autoloader;
use Aigen\Core\Database;
use Aigen\Support\Crypto;

require dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__) . '/src');

App::boot();

$pdo = Database::connection();

if (in_array('--list', $argv, true)) {
    echo "\nVendor terdaftar\n────────────────\n";

    foreach ($pdo->query('SELECT vendor_name, base_url, api_key, is_active, daily_quota FROM data_vendors') as $row) {
        printf(
            "  %-12s %-38s key:%-7s %s  kuota/hari:%s\n",
            $row['vendor_name'],
            $row['base_url'],
            ($row['api_key'] ?? '') !== '' ? 'ada' : 'kosong',
            $row['is_active'] ? 'aktif' : 'nonaktif',
            $row['daily_quota'] ?? '-'
        );
    }

    echo "\n";
    exit(0);
}

$vendor = $argv[1] ?? null;
$key    = $argv[2] ?? null;

if ($vendor === null || $key === null) {
    echo "Pemakaian:\n";
    echo "  php tools/set-vendor-key.php <nama_vendor> <api_key>\n";
    echo "  php tools/set-vendor-key.php --list\n\n";
    echo "Contoh:\n  php tools/set-vendor-key.php Invezgo abc123\n";
    exit(1);
}

$find = $pdo->prepare('SELECT id FROM data_vendors WHERE vendor_name = :name');
$find->execute(['name' => $vendor]);
$id = $find->fetchColumn();

if ($id === false) {
    echo "x Vendor '$vendor' tidak ada di tabel data_vendors.\n";
    echo "  Lihat daftarnya: php tools/set-vendor-key.php --list\n";
    exit(1);
}

try {
    $encrypted = Crypto::encrypt($key);
} catch (Throwable $e) {
    echo "x Gagal mengenkripsi: " . $e->getMessage() . "\n";
    echo "  Biasanya APP_KEY di .env belum diisi. Buat dengan:\n";
    echo "  php -r \"echo base64_encode(random_bytes(32));\"\n";
    exit(1);
}

$pdo->prepare('UPDATE data_vendors SET api_key = :key, is_active = 1 WHERE id = :id')
    ->execute(['key' => $encrypted, 'id' => (int) $id]);

// Dibaca ulang lewat jalur yang sama dengan aplikasi. Kalau APP_KEY berubah
// setelah ini, pembacaan akan gagal — lebih baik ketahuan sekarang.
$stored = $pdo->prepare('SELECT api_key FROM data_vendors WHERE id = :id');
$stored->execute(['id' => (int) $id]);

if (Crypto::decrypt((string) $stored->fetchColumn()) !== $key) {
    echo "x Tersimpan, tapi hasil baca ulang tidak cocok. Periksa APP_KEY.\n";
    exit(1);
}

echo "API key untuk '$vendor' tersimpan (terenkripsi) dan terverifikasi.\n";
echo "Vendor diaktifkan. Lanjutkan dengan:\n";
echo "  php jobs/sync_stocks.php\n";
