<?php

/**
 * Bangun database SQLite untuk mencoba job sinkronisasi tanpa MySQL.
 *
 *   php tools/make-job-testdb.php storage/jobtest.sqlite http://127.0.0.1:8091/api/v1 kunci
 *
 * Perkakas pengembangan, bukan bagian dari alur produksi.
 */

declare(strict_types=1);

use Aigen\Core\Autoloader;
use Aigen\Core\App;
use Aigen\Support\Crypto;
use Aigen\Tests\TestSchema;

require dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__) . '/src');
require dirname(__DIR__) . '/tests/TestSchema.php';

$path    = $argv[1] ?? dirname(__DIR__) . '/storage/jobtest.sqlite';
$baseUrl = $argv[2] ?? 'http://127.0.0.1:8091/api/v1';
$apiKey  = $argv[3] ?? 'kunci-uji-rahasia';

App::boot([
    'app'      => ['env' => 'testing', 'debug' => true, 'url' => 'http://localhost', 'cors_origins' => []],
    'database' => ['driver' => 'sqlite', 'path' => $path],
    'session'  => ['name' => 'aigen_job', 'lifetime' => 0, 'same_site' => 'Lax', 'secure' => false],
    'security' => [
        'app_key'             => base64_encode(str_repeat('k', 32)),
        'job_token'           => '',
        'login_max_attempts'  => 5,
        'login_decay_minutes' => 15,
    ],
]);

@unlink($path);

$pdo = TestSchema::bootSqlite($path);
TestSchema::loadSeed($pdo);

$pdo->prepare(
    "UPDATE data_vendors SET base_url = :u, api_key = :k, is_active = 1 WHERE vendor_name = 'Invezgo'"
)->execute(['u' => $baseUrl, 'k' => Crypto::encrypt($apiKey)]);

echo "Database job siap: $path\n";
echo "Vendor Invezgo -> $baseUrl (api_key terenkripsi)\n";
