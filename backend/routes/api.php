<?php

/**
 * Definisi seluruh endpoint API.
 *
 * Semua rute terkumpul di satu berkas sehingga cakupan endpoint dan guard-nya
 * bisa dibaca sekali lihat. Ini menggantikan pola lama "satu file PHP per
 * endpoint" yang membuat endpoint admin bisa lolos tanpa guard tanpa ketahuan.
 *
 * Aturan yang dipegang:
 *   - Rute publik hanya yang benar-benar dibutuhkan sebelum login.
 *   - Semua rute lain berada di dalam group ber-middleware auth.
 *   - Biaya kredit TIDAK ditentukan di sini, melainkan di tabel credit_costs.
 */

declare(strict_types=1);

use Aigen\Controllers\AuthController;
use Aigen\Controllers\CreditController;
use Aigen\Controllers\NavigationController;
use Aigen\Controllers\ScreenerController;
use Aigen\Controllers\StockController;
use Aigen\Core\Middleware;
use Aigen\Core\Request;
use Aigen\Core\Response;
use Aigen\Core\Router;

$router = new Router();

$auth = new AuthController();
$nav = new NavigationController();
$screener = new ScreenerController();
$stock = new StockController();
$credit = new CreditController();

// -----------------------------------------------------------------------------
// Publik
// -----------------------------------------------------------------------------
$router->get('/health', static function (Request $request) use ($router): void {
    Response::success([
        'status' => 'ok',
        'time'   => date('c'),
        'routes' => count($router->list()),
    ]);
});

// Branding dan tema dibutuhkan halaman login, jadi harus bisa diakses publik.
$router->get('/config', [$nav, 'config']);

$router->post('/auth/register', [$auth, 'register']);
$router->post('/auth/login', [$auth, 'login']);

// -----------------------------------------------------------------------------
// Wajib login
// -----------------------------------------------------------------------------
$router->group('', static function (Router $r) use ($auth, $nav, $screener, $stock, $credit): void {
    // Sesi
    $r->post('/auth/logout', [$auth, 'logout']);
    $r->get('/auth/me', [$auth, 'me']);

    // Sidebar dinamis
    $r->get('/navigation', [$nav, 'index']);

    // Screener — gerbang kredit ada di dalam controller lewat UsageGate
    $r->get('/screener/options', [$screener, 'options'], [Middleware::feature('fundamental_screening')]);
    $r->post('/screener/run', [$screener, 'run'], [Middleware::feature('fundamental_screening')]);

    // Emiten
    $r->get('/stocks', [$stock, 'index']);
    $r->get('/stocks/{symbol}', [$stock, 'show'], [Middleware::feature('fundamental_detail')]);

    // Kredit
    $r->get('/credits/balance', [$credit, 'balance']);
    $r->get('/credits/history', [$credit, 'history']);
    $r->get('/credits/packages', [$credit, 'packages']);
}, [Middleware::auth()]);

return $router;
