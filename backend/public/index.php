<?php

/**
 * Front controller — satu-satunya pintu masuk API.
 *
 * Web server mengarahkan semua permintaan ke berkas ini (lihat .htaccess),
 * sehingga bootstrap, CORS, penanganan error, dan guard dijamin selalu jalan.
 */

declare(strict_types=1);

use Aigen\Core\App;
use Aigen\Core\Autoloader;
use Aigen\Core\HaltException;
use Aigen\Core\Request;

require dirname(__DIR__) . '/src/Core/Autoloader.php';

Autoloader::register(dirname(__DIR__) . '/src');

App::boot();
App::applyCors();

/** @var \Aigen\Core\Router $router */
$router = require dirname(__DIR__) . '/routes/api.php';

try {
    $router->dispatch(Request::capture());
} catch (HaltException) {
    // Alur normal: Response sudah mengirim payload dan menghentikan eksekusi.
}
