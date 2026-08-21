<?php

/**
 * Sinkronisasi data fundamental per emiten.
 *
 * Mengisi indicator_snapshot_fundamental (rasio + skor), lalu opsional
 * indicator_history_fundamental (laporan keuangan mentah) dan
 * shareholder_composition.
 *
 *   php jobs/sync_fundamental.php
 *   php jobs/sync_fundamental.php --offset=200 --batch=100
 *   php jobs/sync_fundamental.php --symbol=BBCA        # satu emiten saja
 *   php jobs/sync_fundamental.php --with-statements    # + laporan keuangan
 *   php jobs/sync_fundamental.php --max-seconds=0      # tanpa batas (CLI)
 *
 * BATCH-RESUMABLE: berhenti sendiri sebelum batas waktu, lalu mencetak
 * perintah untuk melanjutkan dari titik berhenti. Dipertahankan dari versi
 * lama karena memang terbukti perlu — 900-an emiten tidak selesai sekali
 * jalan, dan Apache memutus permintaan di tengah jalan tanpa ampun.
 */

declare(strict_types=1);

use Aigen\Core\Database;
use Aigen\Core\Settings;
use Aigen\Support\FundamentalScore;
use Aigen\Support\JobRunner;
use Aigen\Vendor\InvezgoClient;
use Aigen\Vendor\VendorException;

require __DIR__ . '/bootstrap.php';

JobRunner::guard();

// Batas waktu aman: di CLI longgar, lewat HTTP harus di bawah timeout Apache
// (bawaan 120 detik) supaya job sempat mencetak titik lanjutnya.
$defaultMaxSeconds = PHP_SAPI === 'cli' ? 0 : 100;

$job = new JobRunner('sync_fundamental', $argv ?? [], $defaultMaxSeconds);

$offset         = max(0, $job->intArg('offset', 0));
$batchSize      = max(1, $job->intArg('batch', 100));
$onlySymbol     = $job->arg('symbol');
$withStatements = $job->boolArg('with-statements');
$withHolders    = !$job->boolArg('no-shareholders');
$historyYears   = max(1, $job->intArg('years', 5));

$pdo   = Database::connection();
$today = date('Y-m-d');
$isSqlite = Database::driver() === 'sqlite';
$now   = $isSqlite ? "datetime('now')" : 'NOW()';

$job->header('Sinkronisasi fundamental');

// Peringatkan kalau ada formula aktif yang tidak punya pemetaan kolom.
// Tanpa ini formula tersebut diam-diam tidak pernah ikut menghitung skor.
$unmapped = FundamentalScore::unmappedKeys();
if ($unmapped !== []) {
    $job->warn('formula_config aktif tanpa pemetaan kolom: ' . implode(', ', $unmapped));
    $job->line('    Skor dihitung tanpa metrik tersebut. Tambahkan di FundamentalScore::KEY_TO_COLUMN.');
}

try {
    $client = new InvezgoClient();
} catch (VendorException $e) {
    $job->fail($e->getMessage());
    exit(1);
}

$quota = $client->remainingQuota();
if ($quota !== null) {
    $job->step("Sisa kuota vendor hari ini: $quota permintaan.");

    if ($quota <= 0) {
        $job->fail('Kuota harian vendor sudah habis. Jalankan lagi besok.');
        exit(1);
    }
}

// ---------------------------------------------------------------- daftar kerja

if ($onlySymbol !== null) {
    $stmt = $pdo->prepare('SELECT id, symbol FROM stocks WHERE symbol = :s');
    $stmt->execute(['s' => strtoupper($onlySymbol)]);
    $stocks      = $stmt->fetchAll();
    $totalStocks = count($stocks);

    if ($stocks === []) {
        $job->fail("Emiten '$onlySymbol' tidak ada di tabel stocks. Jalankan sync_stocks.php dulu.");
        exit(1);
    }
} else {
    $totalStocks = (int) $pdo->query('SELECT COUNT(*) FROM stocks WHERE is_active = 1')->fetchColumn();

    if ($totalStocks === 0) {
        $job->fail('Tabel stocks masih kosong. Jalankan lebih dulu: php jobs/sync_stocks.php');
        exit(1);
    }

    $stmt = $pdo->prepare(
        'SELECT id, symbol FROM stocks
          WHERE is_active = 1
          ORDER BY symbol ASC
          LIMIT :lim OFFSET :off'
    );
    $stmt->bindValue('lim', $batchSize, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $stocks = $stmt->fetchAll();

    if ($stocks === []) {
        $job->line("Tidak ada emiten pada offset $offset. Seluruh $totalStocks emiten sudah terlewati.");
        exit(0);
    }

    $job->step(sprintf(
        'Memproses %d emiten (offset %d dari total %d).',
        count($stocks),
        $offset,
        $totalStocks
    ));
}

// ------------------------------------------------------------------ pernyataan

$snapshotSql = $isSqlite
    ? 'INSERT INTO indicator_snapshot_fundamental
           (stock_id, snapshot_date, roe, roa, der, per, pbv, eps, bvps,
            dividend_yield, net_profit_margin, gross_profit_margin,
            current_ratio, quick_ratio, fundamental_score, rating, created_at)
       VALUES (:stock_id, :d, :roe, :roa, :der, :per, :pbv, :eps, :bvps,
               :dy, :npm, :gpm, :cr, :qr, :score, :rating, datetime(\'now\'))
       ON CONFLICT (stock_id, snapshot_date) DO UPDATE SET
           roe = excluded.roe, roa = excluded.roa, der = excluded.der,
           per = excluded.per, pbv = excluded.pbv, eps = excluded.eps,
           bvps = excluded.bvps, dividend_yield = excluded.dividend_yield,
           net_profit_margin = excluded.net_profit_margin,
           gross_profit_margin = excluded.gross_profit_margin,
           current_ratio = excluded.current_ratio, quick_ratio = excluded.quick_ratio,
           fundamental_score = excluded.fundamental_score, rating = excluded.rating'
    : 'INSERT INTO indicator_snapshot_fundamental
           (stock_id, snapshot_date, roe, roa, der, per, pbv, eps, bvps,
            dividend_yield, net_profit_margin, gross_profit_margin,
            current_ratio, quick_ratio, fundamental_score, rating, created_at)
       VALUES (:stock_id, :d, :roe, :roa, :der, :per, :pbv, :eps, :bvps,
               :dy, :npm, :gpm, :cr, :qr, :score, :rating, NOW())
       ON DUPLICATE KEY UPDATE
           roe = VALUES(roe), roa = VALUES(roa), der = VALUES(der),
           per = VALUES(per), pbv = VALUES(pbv), eps = VALUES(eps),
           bvps = VALUES(bvps), dividend_yield = VALUES(dividend_yield),
           net_profit_margin = VALUES(net_profit_margin),
           gross_profit_margin = VALUES(gross_profit_margin),
           current_ratio = VALUES(current_ratio), quick_ratio = VALUES(quick_ratio),
           fundamental_score = VALUES(fundamental_score), rating = VALUES(rating)';

$snapshotStmt = $pdo->prepare($snapshotSql);

$historyStmt = $pdo->prepare(
    "INSERT INTO indicator_history_fundamental
        (stock_id, period_label, period_type, fiscal_year, account_name,
         statement_type, account_level, amount, created_at)
     VALUES (:stock_id, :label, :ptype, :year, :account, :stype, :level, :amount, $now)"
);

$holderStmt = $pdo->prepare(
    "INSERT INTO shareholder_composition
        (stock_id, holder_name, percentage, badge, source, snapshot_date, created_at)
     VALUES (:stock_id, :name, :pct, :badge, 'invezgo', :d, $now)"
);

// -------------------------------------------------------------------- pembantu

/**
 * Ubah rows[] keystat menjadi peta nama -> nilai periode terbaru.
 *
 * @return array<string,float|null>
 */
function keystatMap(array $keystat): array
{
    $rows = $keystat['rows'] ?? $keystat['data']['rows'] ?? $keystat['data'] ?? [];
    if (!is_array($rows)) {
        return [];
    }

    $map = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = $row['name'] ?? $row['label'] ?? null;
        if (!is_string($name)) {
            continue;
        }

        $value = $row['values'][0]['amount']
            ?? $row['values'][0]['value']
            ?? $row['value']
            ?? null;

        $map[strtoupper(trim($name))] = is_numeric($value) ? (float) $value : null;
    }

    return $map;
}

/**
 * Ambil metrik dari peta keystat.
 *
 * `$zeroIsMissing` untuk rasio yang mustahil bernilai nol pada perusahaan yang
 * beroperasi (DER, PER, PBV). Vendor mengirim 0 saat data tidak tersedia, dan
 * menyimpannya apa adanya membuat emiten tanpa data terlihat sebagai emiten
 * dengan utang nol — lalu naik ke puncak hasil screening.
 */
function metric(array $map, array $aliases, bool $zeroIsMissing = false): ?float
{
    foreach ($aliases as $alias) {
        $value = $map[strtoupper($alias)] ?? null;
        if ($value === null) {
            continue;
        }
        if ($zeroIsMissing && abs($value) < 1e-9) {
            continue;
        }
        return $value;
    }
    return null;
}

// ---------------------------------------------------------------------- proses

$success             = 0;
$failed              = 0;
$noData              = 0;
$consecutiveFailures = 0;
$maxConsecutive      = 5;
$firstFailureOffset  = null;   // awal rentetan kegagalan yang sedang berjalan

foreach ($stocks as $i => $stock) {
    if ($job->shouldStop()) {
        // Kalau beberapa emiten terakhir gagal tanpa sempat memicu pemutus
        // rangkaian, ulangi dari kegagalan pertama agar tidak ada yang bolong.
        $stoppedAt = min($firstFailureOffset ?? PHP_INT_MAX, $offset + $i);
        $job->line();
        $job->warn("Berhenti di offset $stoppedAt — batas waktu aman tercapai.");
        $job->resumeHint('sync_fundamental.php', ['offset' => $stoppedAt, 'batch' => $batchSize]);
        exit(0);
    }

    $symbol  = (string) $stock['symbol'];
    $stockId = (int) $stock['id'];

    try {
        $map = keystatMap($client->keystat($symbol, 'FY', $historyYears));

        if ($map === []) {
            $job->step("$symbol — vendor tidak mengirim rasio apa pun, dilewati.");
            $noData++;
            $consecutiveFailures = 0;
            continue;
        }

        $snapshot = [
            'roe'  => metric($map, ['ROE', 'Return on Equity']),
            'roa'  => metric($map, ['ROA', 'Return on Assets']),
            'der'  => metric($map, ['DER', 'Debt to Equity Ratio'], true),
            'per'  => metric($map, ['PER', 'PE Ratio', 'Price Earning Ratio'], true),
            'pbv'  => metric($map, ['PBV', 'PB Ratio', 'Price to Book Value'], true),
            'eps'  => metric($map, ['EPS', 'Earning per Share']),
            'bvps' => metric($map, ['BVPS', 'Book Value per Share']),
            'dividend_yield'      => metric($map, ['DIVIDEND YIELD', 'Dividend Yield']),
            'net_profit_margin'   => metric($map, ['NPM', 'Net Profit Margin']),
            'gross_profit_margin' => metric($map, ['GPM', 'Gross Profit Margin']),
            'current_ratio'       => metric($map, ['CURRENT RATIO', 'CR'], true),
            'quick_ratio'         => metric($map, ['QUICK RATIO', 'QR'], true),
        ];

        $scoring = FundamentalScore::compute($snapshot);
        $score   = $scoring['score'];

        $snapshotStmt->execute([
            'stock_id' => $stockId,
            'd'        => $today,
            'roe'      => $snapshot['roe'],
            'roa'      => $snapshot['roa'],
            'der'      => $snapshot['der'],
            'per'      => $snapshot['per'],
            'pbv'      => $snapshot['pbv'],
            'eps'      => $snapshot['eps'],
            'bvps'     => $snapshot['bvps'],
            'dy'       => $snapshot['dividend_yield'],
            'npm'      => $snapshot['net_profit_margin'],
            'gpm'      => $snapshot['gross_profit_margin'],
            'cr'       => $snapshot['current_ratio'],
            'qr'       => $snapshot['quick_ratio'],
            'score'    => $score,
            'rating'   => FundamentalScore::rating($score),
        ]);

        // --- laporan keuangan mentah (opsional, 3 permintaan tambahan/emiten)
        if ($withStatements) {
            foreach (['IS', 'BS', 'CF'] as $statementType) {
                try {
                    $statement = $client->financialStatement($symbol, $statementType, 'FY', $historyYears);
                    saveStatement($pdo, $historyStmt, $stockId, $statementType, $statement);
                } catch (VendorException $e) {
                    $job->warn("$symbol/$statementType: " . $e->getMessage());
                }
            }
        }

        // --- pemegang saham
        if ($withHolders) {
            try {
                $holders = $client->shareholder($symbol);
                if ($holders !== []) {
                    $del = $pdo->prepare(
                        'DELETE FROM shareholder_composition WHERE stock_id = :id AND snapshot_date = :d'
                    );
                    $del->execute(['id' => $stockId, 'd' => $today]);

                    foreach ($holders as $holder) {
                        $name = $holder['name'] ?? $holder['holder_name'] ?? null;
                        if (!is_string($name) || trim($name) === '') {
                            continue;
                        }
                        $pct = $holder['percentage'] ?? $holder['percent'] ?? $holder['value'] ?? null;

                        $holderStmt->execute([
                            'stock_id' => $stockId,
                            'name'     => mb_substr(trim($name), 0, 200),
                            'pct'      => is_numeric($pct) ? (float) $pct : null,
                            'badge'    => isset($holder['badge']) && is_string($holder['badge'])
                                ? mb_substr($holder['badge'], 0, 50)
                                : null,
                            'd'        => $today,
                        ]);
                    }
                }
            } catch (VendorException $e) {
                $job->warn("$symbol/shareholder: " . $e->getMessage());
            }
        }

        $success++;
        $consecutiveFailures = 0;
        $firstFailureOffset  = null;

        $job->step(sprintf(
            '%-6s skor %s  (%d/%d metrik)',
            $symbol,
            $score !== null ? str_pad(number_format($score, 2), 6, ' ', STR_PAD_LEFT) : '  —   ',
            $scoring['used'],
            $scoring['total']
        ));
    } catch (VendorException $e) {
        $failed++;
        $consecutiveFailures++;
        $firstFailureOffset ??= $offset + $i;
        $job->fail("$symbol: " . $e->getMessage());

        // Titik lanjut adalah AWAL rentetan kegagalan, bukan tempat berhenti.
        // Kalau memakai posisi terakhir, seluruh emiten yang baru saja gagal
        // ikut terlewati saat job dijalankan ulang — kegagalan berubah menjadi
        // lubang data yang senyap.
        if ($e->isAuthProblem() || $e->isQuotaExhausted()) {
            $job->line();
            $job->fail($e->isQuotaExhausted()
                ? 'Kuota vendor habis — dihentikan. Jalankan lagi setelah kuota harian berganti.'
                : 'Masalah kredensial/langganan — dihentikan.');
            $job->resumeHint('sync_fundamental.php', ['offset' => $firstFailureOffset, 'batch' => $batchSize]);
            exit(1);
        }

        if ($consecutiveFailures >= $maxConsecutive) {
            $job->line();
            $job->fail(sprintf(
                'Dihentikan: %d kegagalan berturut-turut mulai offset %d.',
                $maxConsecutive,
                $firstFailureOffset
            ));
            $job->line('  Kalau 5xx, biasanya gangguan sementara dan cukup diulang.');
            $job->resumeHint('sync_fundamental.php', ['offset' => $firstFailureOffset, 'batch' => $batchSize]);
            exit(1);
        }
    }
}

// ------------------------------------------------------------------- ringkasan

$job->line();
$job->step("Berhasil            : $success");
if ($noData > 0) {
    $job->step("Tanpa data          : $noData");
}
if ($failed > 0) {
    $job->step("Gagal               : $failed");
}
$job->step('Permintaan ke vendor: ' . $client->requestCount());

$job->done('Batch selesai.');

if ($onlySymbol === null) {
    $nextOffset = $offset + count($stocks);
    if ($nextOffset < $totalStocks) {
        $sisa = $totalStocks - $nextOffset;
        $job->line();
        $job->line("Masih ada $sisa emiten lagi.");
        $job->resumeHint('sync_fundamental.php', ['offset' => $nextOffset, 'batch' => $batchSize]);
    } else {
        $job->line();
        $job->line("Seluruh $totalStocks emiten sudah diproses.");
    }
}

/**
 * Simpan laporan keuangan mentah.
 *
 * Data lama untuk periode yang sama dihapus lebih dulu — tabel history tidak
 * punya UNIQUE KEY (nama akun bervariasi antar emiten sehingga tidak bisa
 * dijadikan kunci), jadi tanpa penghapusan ini setiap sinkronisasi ulang akan
 * menggandakan seluruh baris.
 */
function saveStatement(
    PDO $pdo,
    PDOStatement $stmt,
    int $stockId,
    string $statementType,
    array $payload,
): void {
    $rows = $payload['rows'] ?? $payload['data']['rows'] ?? $payload['data'] ?? [];
    if (!is_array($rows) || $rows === []) {
        return;
    }

    $periods = $payload['periods'] ?? $payload['data']['periods'] ?? [];
    $touched = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $account = $row['name'] ?? $row['account_name'] ?? null;
        if (!is_string($account) || trim($account) === '') {
            continue;
        }

        $level  = (int) ($row['level'] ?? $row['account_level'] ?? 0);
        $values = $row['values'] ?? [];
        if (!is_array($values)) {
            continue;
        }

        foreach ($values as $index => $entry) {
            $amount = is_array($entry)
                ? ($entry['amount'] ?? $entry['value'] ?? null)
                : $entry;

            if (!is_numeric($amount)) {
                continue;
            }

            $label = is_array($entry)
                ? ($entry['period'] ?? $entry['label'] ?? null)
                : ($periods[$index]['label'] ?? $periods[$index] ?? null);

            if (!is_string($label) || $label === '') {
                continue;
            }

            if (!preg_match('/(\d{4})/', $label, $m)) {
                continue;
            }
            $year = (int) $m[1];

            // period_type adalah ENUM('Q1','Q2','Q3','Q4','FY') — nilai di luar
            // itu ditolak MySQL, jadi apa pun yang tidak jelas dianggap FY.
            $periodType = 'FY';
            if (preg_match('/\bQ([1-4])\b/i', $label, $q)) {
                $periodType = 'Q' . $q[1];
            }

            $key = $year . $periodType;
            if (!isset($touched[$key])) {
                $del = $pdo->prepare(
                    'DELETE FROM indicator_history_fundamental
                      WHERE stock_id = :id AND statement_type = :st
                        AND fiscal_year = :y AND period_type = :pt'
                );
                $del->execute(['id' => $stockId, 'st' => $statementType, 'y' => $year, 'pt' => $periodType]);
                $touched[$key] = true;
            }

            $stmt->execute([
                'stock_id' => $stockId,
                'label'    => mb_substr($label, 0, 20),
                'ptype'    => $periodType,
                'year'     => $year,
                'account'  => mb_substr(trim($account), 0, 255),
                'stype'    => $statementType,
                'level'    => $level,
                'amount'   => (float) $amount,
            ]);
        }
    }
}
