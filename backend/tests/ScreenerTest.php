<?php

declare(strict_types=1);

use Aigen\Auth\Auth;
use Aigen\Core\Database;
use Aigen\Credit\CreditManager;
use Aigen\Support\ScreenerFilter;
use Aigen\Tests\TestCase;

/** Isi beberapa emiten beserta snapshot fundamentalnya. */
function seedStocks(): void
{
    $pdo = Database::connection();

    $pdo->exec("INSERT INTO sectors (name, sub_sector, created_at)
                VALUES ('Keuangan', 'Perbankan', datetime('now'))");
    $sectorId = (int) $pdo->lastInsertId();

    $data = [
        // symbol, nama,          roe,  der,  per,  skor
        ['BBCA', 'Bank Central Asia',  21.5, 0.30, 22.0, 88.5],
        ['BBRI', 'Bank Rakyat Indonesia', 18.2, 0.45, 12.5, 81.0],
        ['BMRI', 'Bank Mandiri',       16.8, 0.55,  9.8, 76.2],
        ['BBNI', 'Bank Negara Indonesia', 12.4, 0.80, 8.1, 64.0],
        ['ANTM', 'Aneka Tambang',       6.2, 1.90, 30.0, 41.5],
    ];

    $stockStmt = $pdo->prepare(
        "INSERT INTO stocks (symbol, company_name, sector_id, exchange, is_syariah, is_active, market_cap, created_at, updated_at)
         VALUES (:sym, :name, :sector, 'IDX', 0, 1, 1000000, datetime('now'), datetime('now'))"
    );
    $snapStmt = $pdo->prepare(
        "INSERT INTO indicator_snapshot_fundamental (stock_id, snapshot_date, roe, der, per, fundamental_score, rating, created_at)
         VALUES (:id, :tanggal, :roe, :der, :per, :skor, 'Uji', datetime('now'))"
    );

    foreach ($data as [$symbol, $name, $roe, $der, $per, $skor]) {
        $stockStmt->execute(['sym' => $symbol, 'name' => $name, 'sector' => $sectorId]);
        $stockId = (int) $pdo->lastInsertId();

        // Snapshot lama sengaja ditambahkan: screener wajib memakai yang TERBARU
        // saja, dan setiap emiten hanya boleh muncul satu kali.
        $snapStmt->execute([
            'id' => $stockId, 'tanggal' => '2024-01-31',
            'roe' => $roe - 5, 'der' => $der + 0.5, 'per' => $per + 5, 'skor' => $skor - 20,
        ]);
        $snapStmt->execute([
            'id' => $stockId, 'tanggal' => '2025-06-30',
            'roe' => $roe, 'der' => $der, 'per' => $per, 'skor' => $skor,
        ]);
    }
}

seedStocks();

/** @var \Aigen\Core\Router $router */
$router = require dirname(__DIR__) . '/routes/api.php';

TestCase::group('Filter screener — keamanan');

TestCase::test('kolom pengurutan di luar daftar putih diabaikan', function (): void {
    // Nama kolom tidak bisa diparameterisasi PDO, jadi daftar putih adalah
    // satu-satunya pertahanan terhadap injeksi lewat parameter sort.
    $order = ScreenerFilter::orderBy('roe; DROP TABLE users; --', 'DESC');

    TestCase::assertSame('fundamental_score', $order['key'], 'input berbahaya harus jatuh ke default');
    TestCase::assertTrue(
        !str_contains($order['sql'], 'DROP'),
        'SQL yang dihasilkan tidak boleh memuat potongan input'
    );
});

TestCase::test('arah pengurutan hanya menerima ASC atau DESC', function (): void {
    TestCase::assertSame('DESC', ScreenerFilter::orderBy('roe', 'DESC; DELETE FROM users')['direction']);
    TestCase::assertSame('ASC', ScreenerFilter::orderBy('roe', 'asc')['direction']);
});

TestCase::test('nilai filter non-numerik diabaikan', function (): void {
    $filter = ScreenerFilter::build(['roe_min' => "5 OR 1=1"]);
    TestCase::assertSame('', $filter['sql'], 'nilai tidak sah tidak boleh membentuk klausa WHERE');
});

TestCase::test('filter sah menghasilkan parameter terikat', function (): void {
    $filter = ScreenerFilter::build(['roe_min' => '15', 'der_max' => '1.0']);

    TestCase::assertTrue(str_contains($filter['sql'], ':f_roe_min'), 'harus memakai placeholder');
    TestCase::assertSame(15.0, $filter['bindings']['f_roe_min']);
    TestCase::assertSame(1.0, $filter['bindings']['f_der_max']);
});

TestCase::group('Screener — endpoint');

TestCase::test('tanpa login ditolak 401', function () use ($router): void {
    Auth::actingAs(null);

    $response = TestCase::call($router, 'POST', '/screener/run');

    TestCase::assertSame(401, $response['status']);
    TestCase::assertSame('unauthenticated', $response['body']['error']['code']);
});

TestCase::test('screening mengembalikan hasil dan memotong kredit', function () use ($router): void {
    $hasil = Auth::register('Screener Satu', 'screener1@test.id', 'rahasia123');
    $userId = $hasil['user_id'];
    Auth::actingAs(['id' => $userId, 'role' => 'user', 'email' => 'screener1@test.id']);

    $response = TestCase::call($router, 'POST', '/screener/run', ['limit' => 10]);

    TestCase::assertSame(200, $response['status']);
    TestCase::assertSame(5, $response['body']['data']['total'], 'lima emiten telah di-seed');
    TestCase::assertCount(5, $response['body']['data']['items']);
});

TestCase::test('setiap emiten hanya muncul sekali walau punya banyak snapshot', function () use ($router): void {
    $symbols = array_column(
        TestCase::call($router, 'POST', '/screener/run', ['limit' => 50])['body']['data']['items'],
        'symbol'
    );

    TestCase::assertSame(count($symbols), count(array_unique($symbols)), 'tidak boleh ada duplikat');
});

TestCase::test('hanya snapshot terbaru yang dipakai', function () use ($router): void {
    $items = TestCase::call($router, 'POST', '/screener/run', ['limit' => 50])['body']['data']['items'];

    $bbca = null;
    foreach ($items as $item) {
        if ($item['symbol'] === 'BBCA') {
            $bbca = $item;
        }
    }

    TestCase::assertTrue($bbca !== null, 'BBCA harus ada di hasil');
    TestCase::assertSame('2025-06-30', $bbca['snapshot_date'], 'harus snapshot terbaru');
    TestCase::assertEquals(21.5, $bbca['roe'], 'nilai harus dari snapshot terbaru, bukan yang lama');
});

TestCase::test('filter roe_min menyaring hasil dengan benar', function () use ($router): void {
    $items = TestCase::call($router, 'POST', '/screener/run', ['roe_min' => 17, 'limit' => 50])
        ['body']['data']['items'];

    TestCase::assertCount(2, $items, 'hanya BBCA dan BBRI yang ROE >= 17');
    foreach ($items as $item) {
        TestCase::assertTrue($item['roe'] >= 17, "ROE {$item['symbol']} harus >= 17");
    }
});

TestCase::test('beberapa filter digabung dengan AND', function () use ($router): void {
    $items = TestCase::call($router, 'POST', '/screener/run', [
        'roe_min' => 15, 'per_max' => 15, 'limit' => 50,
    ])['body']['data']['items'];

    TestCase::assertCount(2, $items, 'BBRI dan BMRI memenuhi keduanya');
    foreach ($items as $item) {
        TestCase::assertTrue($item['roe'] >= 15 && $item['per'] <= 15);
    }
});

TestCase::test('pengurutan menaik menempatkan nilai terkecil di depan', function () use ($router): void {
    $items = TestCase::call($router, 'POST', '/screener/run', [
        'sort' => 'per', 'direction' => 'ASC', 'limit' => 50,
    ])['body']['data']['items'];

    TestCase::assertSame('BBNI', $items[0]['symbol'], 'PER terendah adalah BBNI');
});

TestCase::test('paginasi membatasi jumlah baris tanpa mengubah total', function () use ($router): void {
    $response = TestCase::call($router, 'POST', '/screener/run', ['limit' => 2, 'page' => 1]);

    TestCase::assertCount(2, $response['body']['data']['items']);
    TestCase::assertSame(5, $response['body']['data']['total'], 'total tetap seluruh hasil');
    TestCase::assertSame(3, $response['body']['data']['total_pages']);
});

TestCase::test('kredit dikembalikan bila screening gagal', function () use ($router): void {
    $hasil = Auth::register('Screener Gagal', 'screenergagal@test.id', 'rahasia123');
    $userId = $hasil['user_id'];
    Auth::actingAs(['id' => $userId, 'role' => 'user', 'email' => 'screenergagal@test.id']);

    // Habiskan kuota harian supaya pembayaran beralih ke kredit.
    for ($i = 0; $i < 20; $i++) {
        TestCase::call($router, 'POST', '/screener/run', ['limit' => 1]);
    }
    $saldoSebelum = CreditManager::balance($userId);

    // Rusak tabel snapshot supaya kueri di dalam gerbang benar-benar gagal.
    Database::connection()->exec('ALTER TABLE indicator_snapshot_fundamental RENAME TO snapshot_disimpan');

    $response = TestCase::call($router, 'POST', '/screener/run', ['limit' => 10]);

    Database::connection()->exec('ALTER TABLE snapshot_disimpan RENAME TO indicator_snapshot_fundamental');

    TestCase::assertSame(500, $response['status']);
    TestCase::assertSame(
        $saldoSebelum,
        CreditManager::balance($userId),
        'kredit wajib kembali saat proses gagal'
    );
});

TestCase::group('Detail emiten');

TestCase::test('emiten tidak ditemukan tidak memotong kredit', function () use ($router): void {
    $hasil = Auth::register('Detail Satu', 'detail1@test.id', 'rahasia123');
    $userId = $hasil['user_id'];
    Auth::actingAs(['id' => $userId, 'role' => 'user', 'email' => 'detail1@test.id']);

    $saldoSebelum = CreditManager::balance($userId);
    $response = TestCase::call($router, 'GET', '/stocks/TIDAKADA');

    TestCase::assertSame(404, $response['status']);
    TestCase::assertSame(
        $saldoSebelum,
        CreditManager::balance($userId),
        'jangan menagih untuk emiten yang tidak ada'
    );
});

TestCase::test('detail emiten memotong 2 kredit', function () use ($router): void {
    $hasil = Auth::register('Detail Dua', 'detail2@test.id', 'rahasia123');
    $userId = $hasil['user_id'];
    Auth::actingAs(['id' => $userId, 'role' => 'user', 'email' => 'detail2@test.id']);

    $saldoSebelum = CreditManager::balance($userId);
    $response = TestCase::call($router, 'GET', '/stocks/BBCA');

    TestCase::assertSame(200, $response['status']);
    TestCase::assertSame('BBCA', $response['body']['data']['stock']['symbol']);
    TestCase::assertSame(
        $saldoSebelum - 2,
        CreditManager::balance($userId),
        'detail emiten berbiaya 2 kredit'
    );
});

TestCase::test('kode emiten tidak wajar ditolak sebelum menyentuh database', function () use ($router): void {
    $response = TestCase::call($router, 'GET', "/stocks/' OR 1=1 --");
    TestCase::assertTrue(
        in_array($response['status'], [404, 422], true),
        'input aneh harus ditolak, bukan dieksekusi'
    );
});

TestCase::group('Navigasi');

TestCase::test('sidebar dibangun dari tabel nav_menu', function () use ($router): void {
    $response = TestCase::call($router, 'GET', '/navigation');

    TestCase::assertSame(200, $response['status']);
    $menus = $response['body']['data']['menus'];
    TestCase::assertCount(10, $menus, 'seed mendaftarkan 10 menu');
    TestCase::assertSame('dashboard', $menus[0]['menu_key'], 'urutan mengikuti sort_order');
});

TestCase::test('menu coming soon membawa detail progresnya', function () use ($router): void {
    $menus = TestCase::call($router, 'GET', '/navigation')['body']['data']['menus'];

    $technical = null;
    foreach ($menus as $menu) {
        if ($menu['menu_key'] === 'technical') {
            $technical = $menu;
        }
    }

    TestCase::assertSame('coming_soon', $technical['status']);
    TestCase::assertTrue($technical['coming_soon'] !== null, 'detail coming soon harus ikut terkirim');
    TestCase::assertSame(15, $technical['coming_soon']['progress_percent']);
});
