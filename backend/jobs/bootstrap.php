<?php

/**
 * Penyiapan bersama untuk seluruh job CLI.
 *
 * Job tidak melewati public/index.php, jadi autoloader dan konfigurasi harus
 * disiapkan di sini.
 */

declare(strict_types=1);

use Aigen\Core\App;
use Aigen\Core\Autoloader;

require dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__) . '/src');

App::boot();

// Job memanggil vendor ratusan kali; batas waktu bawaan PHP tidak relevan.
// Batas yang sebenarnya dijaga adalah --max-seconds milik JobRunner.
set_time_limit(0);

if (PHP_SAPI === 'cli') {
    ini_set('memory_limit', '512M');
}
