<?php

declare(strict_types=1);

use Aigen\Core\Database;
use Aigen\Support\Crypto;
use Aigen\Support\FundamentalScore;
use Aigen\Support\JobRunner;
use Aigen\Tests\TestCase;
use Aigen\Vendor\InvezgoClient;
use Aigen\Vendor\VendorException;

/**
 * Pasang vendor uji dengan api_key terisi, karena seed sengaja
 * mengosongkannya. Dikembalikan id vendornya.
 */
function seedVendor(string $name, string $apiKey, ?int $quota = 5000, int $active = 1): int
{
    $pdo = Database::connection();

    $pdo->prepare('DELETE FROM data_vendors WHERE vendor_name = :n')->execute(['n' => $name]);

    $pdo->prepare(
        "INSERT INTO data_vendors (vendor_name, base_url, api_key, auth_type, daily_quota, is_active, created_at)
         VALUES (:n, 'https://vendor.test/api/v1', :k, 'bearer', :q, :a, datetime('now'))"
    )->execute(['n' => $name, 'k' => $apiKey, 'q' => $quota, 'a' => $active]);

    return (int) $pdo->lastInsertId();
}

/** Klien dengan transport palsu; $responses dikonsumsi berurutan. */
function fakeClient(string $vendorName, array $responses, ?array &$calls = null): InvezgoClient
{
    $calls  = [];
    $client = new InvezgoClient($vendorName);

    $client->setTransport(function (string $url, array $headers, int $timeout) use (&$responses, &$calls): array {
        $calls[] = ['url' => $url, 'headers' => $headers];

        $next = array_shift($responses) ?? ['body' => '{}', 'status' => 200, 'error' => ''];

        return [
            'body'   => $next['body']   ?? '{}',
            'status' => $next['status'] ?? 200,
            'error'  => $next['error']  ?? '',
        ];
    });

    return $client;
}

// ---------------------------------------------------------------------- Crypto

TestCase::group('Crypto');

TestCase::test('enkripsi lalu dekripsi mengembalikan nilai semula', function () {
    $secret = 'invezgo-live-key-8f3a91';
    $cipher = Crypto::encrypt($secret);

    TestCase::assertTrue($cipher !== $secret, 'Ciphertext tidak boleh sama dengan aslinya');
    TestCase::assertSame($secret, Crypto::decrypt($cipher));
});

TestCase::test('ciphertext berbeda tiap kali walau isinya sama', function () {
    // IV acak — kalau dua enkripsi menghasilkan keluaran identik, berarti IV
    // statis dan pola isi rahasia bisa terbaca dari database.
    $a = Crypto::encrypt('kunci-yang-sama');
    $b = Crypto::encrypt('kunci-yang-sama');

    TestCase::assertTrue($a !== $b, 'Dua enkripsi harus menghasilkan ciphertext berbeda');
    TestCase::assertSame('kunci-yang-sama', Crypto::decrypt($a));
    TestCase::assertSame('kunci-yang-sama', Crypto::decrypt($b));
});

TestCase::test('nilai teks polos dilewatkan apa adanya', function () {
    // api_key yang diisi manual lewat phpMyAdmin harus tetap bisa dipakai.
    TestCase::assertSame('kunci-polos-123', Crypto::decrypt('kunci-polos-123'));
    TestCase::assertTrue(!Crypto::isEncrypted('kunci-polos-123'));
    TestCase::assertTrue(Crypto::isEncrypted(Crypto::encrypt('x')));
});

TestCase::test('ciphertext yang diubah ditolak, bukan menghasilkan sampah', function () {
    $cipher = Crypto::encrypt('kunci-asli');

    // Balik satu karakter di bagian base64.
    $tampered = substr($cipher, 0, -2) . (substr($cipher, -2, 1) === 'A' ? 'B' : 'A') . substr($cipher, -1);

    $ditolak = false;
    try {
        Crypto::decrypt($tampered);
    } catch (RuntimeException $e) {
        $ditolak = true;
    }

    TestCase::assertTrue($ditolak, 'GCM harus menolak ciphertext yang dirusak');
});

TestCase::test('ciphertext terpotong ditolak', function () {
    $ditolak = false;
    try {
        Crypto::decrypt('enc:v1:' . base64_encode('pendek'));
    } catch (RuntimeException $e) {
        $ditolak = true;
    }
    TestCase::assertTrue($ditolak);
});

// ------------------------------------------------------------- FundamentalScore

TestCase::group('FundamentalScore');

TestCase::test('npm dan cr benar-benar ikut dihitung', function () {
    // Inilah bug versi lama: formula_key 'npm'/'cr' dicocokkan langsung ke
    // nama kolom, padahal kolomnya net_profit_margin/current_ratio. Keduanya
    // diam-diam terlewat sehingga skor hanya berasal dari 5 dari 7 formula.
    $tanpa = FundamentalScore::compute([
        'roe' => 20.0, 'roa' => 10.0, 'der' => 0.5, 'per' => 10.0, 'pbv' => 1.0,
    ]);
    $dengan = FundamentalScore::compute([
        'roe' => 20.0, 'roa' => 10.0, 'der' => 0.5, 'per' => 10.0, 'pbv' => 1.0,
        'net_profit_margin' => 15.0, 'current_ratio' => 2.0,
    ]);

    TestCase::assertSame(5, $tanpa['used']);
    TestCase::assertSame(7, $dengan['used'], 'npm dan cr harus terhitung');
    TestCase::assertSame(7, $dengan['total']);
});

TestCase::test('semua metrik di titik terbaik menghasilkan 100', function () {
    $hasil = FundamentalScore::compute([
        'roe' => 20.0, 'roa' => 10.0, 'der' => 0.5, 'per' => 10.0, 'pbv' => 1.0,
        'net_profit_margin' => 15.0, 'current_ratio' => 2.0,
    ]);

    TestCase::assertSame(100.0, $hasil['score']);
});

TestCase::test('semua metrik di titik terburuk menghasilkan 0', function () {
    $hasil = FundamentalScore::compute([
        'roe' => 5.0, 'roa' => 2.0, 'der' => 2.0, 'per' => 25.0, 'pbv' => 3.0,
        'net_profit_margin' => 3.0, 'current_ratio' => 1.0,
    ]);

    TestCase::assertSame(0.0, $hasil['score']);
});

TestCase::test('metrik arah terbalik dinilai dengan benar', function () {
    // DER: good 0.5, bad 2.0. Utang kecil harus bernilai LEBIH TINGGI.
    $utangKecil = FundamentalScore::compute(['der' => 0.5]);
    $utangBesar = FundamentalScore::compute(['der' => 2.0]);

    TestCase::assertSame(100.0, $utangKecil['score']);
    TestCase::assertSame(0.0, $utangBesar['score']);
});

TestCase::test('nilai di luar rentang dijepit, tidak melebihi 100', function () {
    $luarBiasa = FundamentalScore::compute(['roe' => 250.0]);
    $mengerikan = FundamentalScore::compute(['roe' => -80.0]);

    TestCase::assertSame(100.0, $luarBiasa['score']);
    TestCase::assertSame(0.0, $mengerikan['score']);
});

TestCase::test('interpolasi di tengah rentang', function () {
    // ROE: bad 5, good 20. Nilai 12.5 tepat di tengah.
    $hasil = FundamentalScore::compute(['roe' => 12.5]);
    TestCase::assertSame(50.0, $hasil['score']);
});

TestCase::test('bobot memengaruhi hasil sesuai formula_config', function () {
    // roe berbobot 2.00, roa 1.00. ROE sempurna + ROA terburuk harus condong
    // ke atas: (100*2 + 0*1) / 3 = 66.67.
    $hasil = FundamentalScore::compute(['roe' => 20.0, 'roa' => 2.0]);
    TestCase::assertSame(66.67, $hasil['score']);
});

TestCase::test('snapshot tanpa metrik apa pun menghasilkan null, bukan nol', function () {
    // Nol berarti "sangat buruk" dan akan muncul di hasil screening; null
    // berarti "belum dinilai" dan tersaring keluar.
    $hasil = FundamentalScore::compute(['symbol' => 'XXXX']);

    TestCase::assertSame(null, $hasil['score']);
    TestCase::assertSame(0, $hasil['used']);
});

TestCase::test('nilai non-numerik diabaikan', function () {
    $hasil = FundamentalScore::compute(['roe' => 'tidak tersedia', 'roa' => 10.0]);
    TestCase::assertSame(1, $hasil['used']);
});

TestCase::test('semua formula_key aktif punya pemetaan kolom', function () {
    TestCase::assertSame([], FundamentalScore::unmappedKeys());
});

TestCase::test('rating mengikuti skor, null tetap null', function () {
    TestCase::assertSame('Sangat Baik', FundamentalScore::rating(88.5));
    TestCase::assertSame('Baik', FundamentalScore::rating(70.0));
    TestCase::assertSame('Cukup', FundamentalScore::rating(50.0));
    TestCase::assertSame('Sangat Lemah', FundamentalScore::rating(10.0));
    TestCase::assertSame(null, FundamentalScore::rating(null));
});

// --------------------------------------------------------------- InvezgoClient

TestCase::group('InvezgoClient');

TestCase::test('vendor tanpa api_key ditolak sejak konstruktor', function () {
    seedVendor('KosongTest', '');

    $pesan = '';
    try {
        new InvezgoClient('KosongTest');
    } catch (VendorException $e) {
        $pesan = $e->getMessage();
    }

    TestCase::assertTrue(str_contains($pesan, 'masih kosong'), "Pesan diterima: $pesan");
});

TestCase::test('vendor nonaktif ditolak', function () {
    seedVendor('NonaktifTest', 'kunci', 5000, 0);

    $pesan = '';
    try {
        new InvezgoClient('NonaktifTest');
    } catch (VendorException $e) {
        $pesan = $e->getMessage();
    }

    TestCase::assertTrue(str_contains($pesan, 'nonaktif'), "Pesan diterima: $pesan");
});

TestCase::test('vendor tak dikenal ditolak', function () {
    $pesan = '';
    try {
        new InvezgoClient('TidakAda');
    } catch (VendorException $e) {
        $pesan = $e->getMessage();
    }
    TestCase::assertTrue(str_contains($pesan, 'tidak ada'), "Pesan diterima: $pesan");
});

TestCase::test('api_key terenkripsi didekripsi sebelum dikirim', function () {
    seedVendor('EnkripsiTest', Crypto::encrypt('kunci-rahasia-nyata'));

    $client = fakeClient('EnkripsiTest', [['body' => '{"data":[]}', 'status' => 200]], $calls);
    $client->stockList();

    TestCase::assertTrue(
        in_array('Authorization: Bearer kunci-rahasia-nyata', $calls[0]['headers'], true),
        'Header harus memuat kunci hasil dekripsi, bukan ciphertext'
    );
});

TestCase::test('kegagalan koneksi tidak menaikkan pemakaian kuota', function () {
    // Bug versi lama: logUsage() dipanggil sebelum hasil curl diperiksa,
    // sehingga jaringan putus pun ikut menghabiskan kuota di pembukuan.
    $vendorId = seedVendor('GagalKoneksiTest', 'kunci');

    $client = fakeClient('GagalKoneksiTest', [
        ['body' => false, 'status' => 0, 'error' => 'Could not resolve host'],
    ]);

    $terlempar = false;
    try {
        $client->stockList();
    } catch (VendorException $e) {
        $terlempar = true;
        TestCase::assertTrue($e->isTransient(), 'Kegagalan jaringan harus dianggap sementara');
    }
    TestCase::assertTrue($terlempar);

    $stmt = Database::connection()->prepare(
        'SELECT COALESCE(SUM(request_count), 0) FROM vendor_usage_log WHERE vendor_id = :id'
    );
    $stmt->execute(['id' => $vendorId]);

    TestCase::assertSame(0, (int) $stmt->fetchColumn(), 'Kuota tidak boleh terpakai saat vendor tak terhubung');
});

TestCase::test('permintaan yang sampai ke vendor dicatat, termasuk yang 500', function () {
    $vendorId = seedVendor('CatatTest', 'kunci');

    $client = fakeClient('CatatTest', [
        ['body' => '{"data":[]}', 'status' => 200],
        ['body' => 'Internal Server Error', 'status' => 500],
    ]);

    $client->stockList();
    try {
        $client->stockList();
    } catch (VendorException $e) {
        // 500 tetap terhitung: vendor menerima dan memproses permintaannya.
        TestCase::assertTrue($e->isTransient());
    }

    $stmt = Database::connection()->prepare(
        'SELECT request_count FROM vendor_usage_log WHERE vendor_id = :id'
    );
    $stmt->execute(['id' => $vendorId]);

    TestCase::assertSame(2, (int) $stmt->fetchColumn());
});

TestCase::test('kuota habis menghentikan permintaan sebelum terkirim', function () {
    $vendorId = seedVendor('KuotaTest', 'kunci', 3);

    Database::connection()->prepare(
        'INSERT INTO vendor_usage_log (vendor_id, usage_date, request_count) VALUES (:id, :d, 3)'
    )->execute(['id' => $vendorId, 'd' => date('Y-m-d')]);

    $client = fakeClient('KuotaTest', [['body' => '{"data":[]}', 'status' => 200]], $calls);

    TestCase::assertSame(0, $client->remainingQuota());

    $pesan = '';
    try {
        $client->stockList();
    } catch (VendorException $e) {
        $pesan = $e->getMessage();
    }

    TestCase::assertTrue(str_contains($pesan, 'Kuota harian'), "Pesan diterima: $pesan");
    TestCase::assertSame(0, count($calls), 'Tidak boleh ada permintaan yang benar-benar dikirim');
});

TestCase::test('kuota habis ditandai khusus, bukan sekadar 429 vendor', function () {
    // Job memakai penanda ini untuk berhenti seketika. Kalau tercampur dengan
    // 429 biasa, job akan mengira gangguan sementara lalu mencoba emiten
    // berikutnya satu per satu sampai pemutus rangkaian bekerja.
    $vendorId = seedVendor('KuotaTandaTest', 'kunci', 1);

    Database::connection()->prepare(
        'INSERT INTO vendor_usage_log (vendor_id, usage_date, request_count) VALUES (:id, :d, 1)'
    )->execute(['id' => $vendorId, 'd' => date('Y-m-d')]);

    $client = fakeClient('KuotaTandaTest', []);

    try {
        $client->stockList();
        TestCase::assertTrue(false, 'Seharusnya melempar');
    } catch (VendorException $e) {
        TestCase::assertTrue($e->isQuotaExhausted(), 'Harus ditandai sebagai kuota habis');
        TestCase::assertTrue(!$e->isAuthProblem());
    }
});

TestCase::test('429 dari vendor bukan penanda kuota lokal habis', function () {
    seedVendor('Rate429Test', 'kunci');

    $client = fakeClient('Rate429Test', [['body' => 'Too Many Requests', 'status' => 429]]);

    try {
        $client->stockList();
        TestCase::assertTrue(false, 'Seharusnya melempar');
    } catch (VendorException $e) {
        TestCase::assertTrue(!$e->isQuotaExhausted(), '429 vendor beda dari kuota kita sendiri');
        TestCase::assertTrue($e->isTransient(), '429 layak dicoba ulang nanti');
    }
});

TestCase::test('401 dikenali sebagai masalah kredensial, bukan gangguan sementara', function () {
    seedVendor('AuthTest401', 'kunci-salah');

    $client = fakeClient('AuthTest401', [['body' => 'Unauthorized', 'status' => 401]]);

    try {
        $client->stockList();
        TestCase::assertTrue(false, 'Seharusnya melempar');
    } catch (VendorException $e) {
        TestCase::assertTrue($e->isAuthProblem(), '401 harus terbaca sebagai masalah auth');
        TestCase::assertTrue(!$e->isTransient(), 'Mengulang permintaan tidak akan memperbaiki 401');
        TestCase::assertSame(401, $e->httpStatus);
    }
});

TestCase::test('balasan bukan JSON ditolak dengan jelas', function () {
    seedVendor('HtmlTest', 'kunci');

    $client = fakeClient('HtmlTest', [['body' => '<html>maintenance</html>', 'status' => 200]]);

    $pesan = '';
    try {
        $client->stockList();
    } catch (VendorException $e) {
        $pesan = $e->getMessage();
    }
    TestCase::assertTrue(str_contains($pesan, 'bukan JSON'), "Pesan diterima: $pesan");
});

TestCase::test('pembungkus data/result/items dibuka', function () {
    seedVendor('BungkusTest', 'kunci');

    $client = fakeClient('BungkusTest', [
        ['body' => '{"data":[{"code":"BBCA"},{"code":"TLKM"}]}', 'status' => 200],
        ['body' => '[{"code":"ANTM"}]', 'status' => 200],
        ['body' => '{"result":{"code":"BBCA","name":"Bank Central Asia"}}', 'status' => 200],
    ]);

    TestCase::assertSame(2, count($client->stockList()));
    TestCase::assertSame(1, count($client->stockList()));

    $info = $client->information('BBCA');
    TestCase::assertSame('Bank Central Asia', $info['name']);
});

TestCase::test('parameter kueri ikut terkirim di URL', function () {
    seedVendor('KueriTest', 'kunci');

    $client = fakeClient('KueriTest', [['body' => '{"rows":[]}', 'status' => 200]], $calls);
    $client->keystat('BBCA', 'FY', 5);

    TestCase::assertTrue(str_contains($calls[0]['url'], '/analysis/keystat/BBCA'), $calls[0]['url']);
    TestCase::assertTrue(str_contains($calls[0]['url'], 'type=FY'), $calls[0]['url']);
    TestCase::assertTrue(str_contains($calls[0]['url'], 'limit=5'), $calls[0]['url']);
});

// -------------------------------------------------------------------- JobRunner

TestCase::group('JobRunner');

TestCase::test('argumen --k=v terbaca', function () {
    $job = new JobRunner('uji', ['skrip.php', '--offset=250', '--batch=50', '--dry-run']);

    TestCase::assertSame(250, $job->intArg('offset', 0));
    TestCase::assertSame(50, $job->intArg('batch', 100));
    TestCase::assertTrue($job->boolArg('dry-run'));
    TestCase::assertTrue(!$job->boolArg('with-statements'));
});

TestCase::test('nilai bawaan dipakai saat argumen tidak ada atau bukan angka', function () {
    $job = new JobRunner('uji', ['skrip.php', '--offset=abc']);

    TestCase::assertSame(0, $job->intArg('offset', 0));
    TestCase::assertSame(100, $job->intArg('batch', 100));
    TestCase::assertSame(null, $job->arg('symbol'));
});

TestCase::test('--flag=0 dibaca sebagai tidak aktif', function () {
    $job = new JobRunner('uji', ['skrip.php', '--dry-run=0', '--verbose=false']);

    TestCase::assertTrue(!$job->boolArg('dry-run'));
    TestCase::assertTrue(!$job->boolArg('verbose'));
});

TestCase::test('tanpa batas waktu, job tidak pernah minta berhenti', function () {
    $job = new JobRunner('uji', ['skrip.php'], 0);
    TestCase::assertTrue(!$job->shouldStop());
});

TestCase::test('--max-seconds menimpa batas bawaan', function () {
    // Batas 100 detik dari pemanggil, tapi operator memaksa berhenti langsung.
    $job = new JobRunner('uji', ['skrip.php', '--max-seconds=0'], 100);
    TestCase::assertTrue(!$job->shouldStop(), '0 berarti tanpa batas');

    $segera = new JobRunner('uji', ['skrip.php'], 100);
    TestCase::assertTrue(!$segera->shouldStop(), 'Baru mulai, belum waktunya berhenti');
});

TestCase::test('batas waktu terlampaui memicu permintaan berhenti', function () {
    $job = new JobRunner('uji', ['skrip.php'], 1);
    usleep(1_100_000);
    TestCase::assertTrue($job->shouldStop(), 'Setelah lewat 1 detik job harus minta berhenti');
});
