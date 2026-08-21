<?php

/**
 * Mengisi database yang SUDAH ADA dengan data contoh (MariaDB/XAMPP maupun SQLite).
 *
 * Bedanya dengan tools/setup-preview-db.php: skrip itu membangun berkas SQLite
 * baru dari nol dan menghapus yang lama. Skrip ini menempel pada database yang
 * dikonfigurasi di .env — jadi cocok untuk XAMPP, di mana schema.sql dan
 * seed.sql sudah diimpor lewat phpMyAdmin tetapi tabel emiten masih kosong.
 *
 * Tanpa ini, Screener pada instalasi baru tidak menampilkan apa pun, dan sulit
 * dibedakan antara "belum ada data" dan "aplikasinya rusak".
 *
 * Jalankan dari folder backend:
 *
 *   php tools/seed-demo-data.php
 *   php tools/seed-demo-data.php --fresh     # kosongkan dulu data emiten lama
 *
 * Aman diulang: emiten dicocokkan lewat kode, snapshot dan laporan ditulis
 * ulang, bukan ditumpuk.
 */

declare(strict_types=1);

use Aigen\Core\App;
use Aigen\Core\Autoloader;
use Aigen\Core\Database;

require dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__) . '/src');
require __DIR__ . '/demo_dataset.php';

App::boot();

$fresh = in_array('--fresh', $argv, true);

$pdo    = Database::connection();
$driver = Database::driver();

echo "\nMengisi data contoh\n";
echo "───────────────────\n";
echo "  Driver database: $driver\n";

// Tabel inti dari seed.sql harus sudah ada. Kalau belum, pesan PDO soal
// "base table not found" jauh lebih membingungkan daripada penjelasan ini.
try {
    $navCount = (int) $pdo->query('SELECT COUNT(*) FROM nav_menu')->fetchColumn();
} catch (Throwable $e) {
    echo "  x Tabel inti belum ada. Impor dulu database/schema.sql lalu database/seed.sql.\n";
    echo '    (' . $e->getMessage() . ")\n";
    exit(1);
}

if ($navCount === 0) {
    echo "  x Tabel nav_menu kosong — database/seed.sql belum diimpor.\n";
    echo "    Tanpa itu sidebar tidak punya menu dan tarif kredit tidak terdefinisi.\n";
    exit(1);
}

if ($fresh) {
    echo "  Menghapus data emiten lama...\n";

    // Urutan penting: anak dulu, induk belakangan, supaya foreign key tidak
    // menolak. Tabel pengguna sengaja tidak disentuh.
    foreach ([
        'indicator_history_fundamental',
        'indicator_snapshot_fundamental',
        'shareholder_composition',
        'stocks',
        'sectors',
    ] as $table) {
        $pdo->exec("DELETE FROM $table");
    }
}

$pdo->beginTransaction();

try {
    $counts = aigen_seed_demo_data($pdo);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "  x Gagal: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n  Isi database sekarang:\n";
foreach ($counts as $table => $count) {
    printf("    %-34s %d\n", $table, $count);
}

echo "\nSelesai. Login memakai: demo@aigen.test / demo1234 (100 kredit)\n";
echo "Data ini contoh untuk pengembangan — untuk data sungguhan jalankan job sinkronisasi:\n";
echo "  php jobs/sync_stocks.php && php jobs/sync_fundamental.php\n";
