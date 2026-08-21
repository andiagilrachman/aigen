<?php

/**
 * Menyiapkan database SQLite untuk pratinjau lokal.
 *
 * Environment pengembangan ini tidak memiliki server MySQL, sedangkan target
 * produksi adalah XAMPP/MariaDB. Skrip ini menerjemahkan schema.sql + seed.sql
 * yang SAMA PERSIS dengan yang dipakai produksi ke SQLite, lalu menambahkan
 * beberapa emiten contoh agar screener punya sesuatu untuk ditampilkan.
 *
 * Berkas ini murni alat bantu pengembangan — tidak pernah dipanggil aplikasi.
 *
 * Jalankan:  php tools/setup-preview-db.php storage/preview.sqlite
 */

declare(strict_types=1);

use Aigen\Core\Autoloader;
use Aigen\Tests\TestSchema;

require dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__) . '/src');
require dirname(__DIR__) . '/tests/TestSchema.php';
require __DIR__ . '/demo_dataset.php';

$target = $argv[1] ?? (dirname(__DIR__) . '/storage/preview.sqlite');
if ($target[0] !== '/') {
    $target = dirname(__DIR__) . '/' . $target;
}

if (file_exists($target)) {
    unlink($target);
}

@mkdir(dirname($target), 0777, true);

echo "Menyiapkan skema di $target\n";
$pdo = TestSchema::bootSqlite($target);
TestSchema::loadSeed($pdo);

// Data contoh dipakai bersama dengan tools/seed-demo-data.php (jalur MariaDB),
// supaya angka yang muncul di kedua mode persis sama.

$counts = aigen_seed_demo_data($pdo);

echo "Selesai.\n";
foreach ($counts as $table => $count) {
    printf("  %-34s %d\n", $table, $count);
}
echo "\nAkun demo: demo@aigen.test / demo1234\n";
