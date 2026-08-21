<?php

/**
 * Test runner.
 *
 * Jalankan dari folder backend:  php tests/run.php
 *
 * Semua test memakai SQLite in-memory yang dibangun dari database/schema.sql
 * dan database/seed.sql yang SEBENARNYA, sehingga perubahan skema atau seed
 * yang merusak logika langsung ketahuan di sini.
 */

declare(strict_types=1);

use Aigen\Auth\Auth;
use Aigen\Core\App;
use Aigen\Core\Autoloader;
use Aigen\Core\Response;
use Aigen\Tests\TestCase;
use Aigen\Tests\TestSchema;

require dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__) . '/src');

require __DIR__ . '/TestSchema.php';
require __DIR__ . '/TestCase.php';

// Konfigurasi khusus test: SQLite in-memory, tanpa sesi HTTP.
App::boot([
    'app' => [
        'env'          => 'testing',
        'debug'        => true,
        'url'          => 'http://localhost',
        'cors_origins' => ['http://localhost:5173'],
    ],
    'database' => ['driver' => 'sqlite', 'path' => ':memory:'],
    'session'  => ['name' => 'aigen_test', 'lifetime' => 0, 'same_site' => 'Lax', 'secure' => false],
    'security' => [
        'app_key'             => 'test-key',
        'job_token'           => '',
        'login_max_attempts'  => 5,
        'login_decay_minutes' => 15,
    ],
]);

Response::enableTestMode();

TestCase::$pdo = TestSchema::bootSqlite();
TestSchema::loadSeed(TestCase::$pdo);
TestCase::flushCaches();

$exitCode = 0;

foreach (['ConfigTest', 'CreditTest', 'AuthTest', 'ScreenerTest', 'VendorTest'] as $suite) {
    $file = __DIR__ . '/' . $suite . '.php';
    if (is_file($file)) {
        require $file;
    }
}

exit(TestCase::summary());
