<?php
// File: jobs/sync_fundamental.php
// SEMENTARA pakai Invezgo saja (langganan DataSectors sedang expired, error 401
// "Subscription has expired" - dikonfirmasi lewat debug_datasectors.php).
// Begitu DataSectors diperpanjang, bisa diaktifkan lagi berdampingan (arsitektur
// data_vendors sudah mendukung banyak vendor tanpa ubah struktur kode).
//
// Endpoint user (api/fundamental/screening.php) hanya baca hasil sync ini dari
// tabel indicator_snapshot_fundamental — tidak pernah panggil vendor langsung.
//
// Sumber data: Invezgo /analysis/keystat/{code}?type=FY
// Field yang tersedia: ROE, ROA, DER, PER, PBV, EPS, BVPS (per tahun, histori 5 tahun)
// CATATAN DATA: untuk sebagian saham, field arus (Revenue, Net Profit, EBITDA, dst)
// hanya terisi di tahun terbaru, tahun sebelumnya bernilai 0 — kemungkinan
// keterbatasan data provider. Growth/margin TIDAK dihitung dari nilai 0 (dibiarkan
// NULL) supaya tidak menghasilkan angka salah/menyesatkan.
//
// Formula lanjutan (Altman Z, Beneish M, Piotroski F, Graham Number) masih di luar
// scope versi ini — butuh raw line-item dari financial-statement + pemetaan nama
// akun (istilah laporan keuangan Indonesia bervariasi antar emiten).
//
// BATCH-RESUMABLE: berhenti otomatis sebelum timeout Apache, tampilkan link lanjut.

set_time_limit(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/VendorClient/InvezgoClient.php';

$pdo = getDbConnection();
$today = date('Y-m-d');

$batchSize = isset($_GET['batch']) ? (int)$_GET['batch'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

echo "Mulai sinkron fundamental via Invezgo (batch mulai dari offset $offset, ukuran $batchSize)...\n";

$totalStmt = $pdo->query('SELECT COUNT(*) AS total FROM stocks WHERE is_active = 1');
$totalStocks = (int)$totalStmt->fetch()['total'];

if ($totalStocks === 0) {
    echo "Belum ada data saham. Jalankan sync_stocks.php dulu.\n";
    return;
}

$stmt = $pdo->prepare('SELECT id, symbol FROM stocks WHERE is_active = 1 ORDER BY symbol ASC LIMIT :limit OFFSET :offset');
$stmt->bindValue('limit', $batchSize, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$stocks = $stmt->fetchAll();

if (empty($stocks)) {
    echo "Tidak ada saham lagi di offset ini. Sinkron sudah mencakup seluruh $totalStocks saham.\n";
    return;
}

$client = new InvezgoClient();
$success = 0;
$failed = 0;
$consecutiveFailures = 0;
$startTime = time();
$maxRunSeconds = 100;

foreach ($stocks as $i => $stock) {
    if (time() - $startTime > $maxRunSeconds) {
        $stoppedAtOffset = $offset + $i;
        echo "\nBerhenti sementara (batas waktu aman tercapai) di offset $stoppedAtOffset.\n";
        echo "Lanjutkan dengan membuka:\n";
        echo "http://localhost/aigen-backend/jobs/sync_fundamental.php?offset=$stoppedAtOffset&batch=$batchSize\n";
        return;
    }

    try {
        $keystat = $client->keystat($stock['symbol'], 'FY', 5);
        $rows = $keystat['rows'] ?? [];

        // Ubah struktur rows[] jadi map: nama_field => nilai tahun terbaru (index 0)
        $metrics = [];
        foreach ($rows as $row) {
            $latestValue = $row['values'][0]['amount'] ?? null;
            $metrics[$row['name']] = $latestValue;
        }

        $roe = $metrics['ROE'] ?? null;
        $roa = $metrics['ROA'] ?? null;
        $der = ($metrics['DER'] ?? null) ?: null; // 0 dianggap data tidak tersedia, bukan DER=0
        $per = $metrics['PER'] ?? null;
        $pbv = $metrics['PBV'] ?? null;
        $eps = $metrics['EPS'] ?? null;

        // Growth & margin sengaja TIDAK dihitung di versi ini (lihat catatan data di atas)
        $revenueGrowth = null;
        $netIncomeGrowth = null;
        $npm = null;
        $gpm = null;
        $dividendYield = null;
        $currentRatio = null;
        $quickRatio = null;

        $score = calculateFundamentalScore($pdo, compact('roe', 'roa', 'der', 'per', 'pbv'));

        $stmtSave = $pdo->prepare(
            'INSERT INTO indicator_snapshot_fundamental
                (stock_id, snapshot_date, roe, roa, der, per, pbv, eps, dividend_yield,
                 revenue_growth_yoy, net_income_growth_yoy, net_profit_margin, gross_profit_margin,
                 current_ratio, quick_ratio, fundamental_score, created_at)
             VALUES
                (:stock_id, :snapshot_date, :roe, :roa, :der, :per, :pbv, :eps, :dividend_yield,
                 :revenue_growth_yoy, :net_income_growth_yoy, :net_profit_margin, :gross_profit_margin,
                 :current_ratio, :quick_ratio, :fundamental_score, NOW())
             ON DUPLICATE KEY UPDATE
                roe = VALUES(roe), roa = VALUES(roa), der = VALUES(der), per = VALUES(per),
                pbv = VALUES(pbv), eps = VALUES(eps),
                fundamental_score = VALUES(fundamental_score)'
        );
        $stmtSave->execute([
            'stock_id' => $stock['id'],
            'snapshot_date' => $today,
            'roe' => $roe, 'roa' => $roa, 'der' => $der, 'per' => $per, 'pbv' => $pbv, 'eps' => $eps,
            'dividend_yield' => $dividendYield,
            'revenue_growth_yoy' => $revenueGrowth,
            'net_income_growth_yoy' => $netIncomeGrowth,
            'net_profit_margin' => $npm,
            'gross_profit_margin' => $gpm,
            'current_ratio' => $currentRatio,
            'quick_ratio' => $quickRatio,
            'fundamental_score' => $score,
        ]);

        $success++;
        $consecutiveFailures = 0;
        usleep(150000); // jeda 150ms antar request, jaga-jaga supaya tidak membanjiri server vendor
    } catch (Exception $e) {
        echo "Gagal sync {$stock['symbol']}: " . $e->getMessage() . "\n";
        $failed++;
        $consecutiveFailures++;

        if ($consecutiveFailures >= 5) {
            $stoppedAtOffset = $offset + $i;
            echo "\nDihentikan otomatis: 5 kegagalan berturut-turut di offset $stoppedAtOffset.\n";
            echo "Kalau errornya 502/503 (gangguan sementara server vendor), biasanya cukup dicoba lagi.\n";
            echo "Kalau errornya 401/402, cek dulu status langganan/API key sebelum lanjut.\n";
            echo "Lanjutkan/coba ulang dengan membuka:\n";
            echo "http://localhost/aigen-backend/jobs/sync_fundamental.php?offset=$stoppedAtOffset&batch=$batchSize\n";
            return;
        }
        continue;
    }
}

echo "Selesai batch ini. Berhasil: $success, Gagal: $failed\n";

$nextOffset = $offset + count($stocks);
if ($nextOffset < $totalStocks) {
    echo "Masih ada saham tersisa. Lanjutkan dengan membuka:\n";
    echo "http://localhost/aigen-backend/jobs/sync_fundamental.php?offset=$nextOffset&batch=$batchSize\n";
} else {
    echo "Semua $totalStocks saham sudah diproses.\n";
}

function calculateFundamentalScore(PDO $pdo, array $metrics): ?float {
    $configs = $pdo->query(
        'SELECT formula_key, weight, threshold_good, threshold_bad FROM formula_config WHERE is_active = 1'
    )->fetchAll();

    if (empty($configs)) {
        return null;
    }

    $totalWeight = 0;
    $weightedScore = 0;

    foreach ($configs as $cfg) {
        $value = $metrics[$cfg['formula_key']] ?? null;
        if ($value === null || $cfg['threshold_good'] === null || $cfg['threshold_bad'] === null) {
            continue;
        }
        $good = (float)$cfg['threshold_good'];
        $bad = (float)$cfg['threshold_bad'];
        $range = $good - $bad;
        $normalized = $range != 0 ? (($value - $bad) / $range) * 100 : 50;
        $normalized = max(0, min(100, $normalized));

        $weightedScore += $normalized * (float)$cfg['weight'];
        $totalWeight += (float)$cfg['weight'];
    }

    return $totalWeight > 0 ? round($weightedScore / $totalWeight, 2) : null;
}
