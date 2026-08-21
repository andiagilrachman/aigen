<?php

/**
 * Hitung ulang fundamental_score dari snapshot yang sudah tersimpan.
 *
 *   php jobs/recalculate_scores.php
 *   php jobs/recalculate_scores.php --all          # semua tanggal, bukan hanya terbaru
 *   php jobs/recalculate_scores.php --dry-run      # bandingkan saja
 *
 * Tidak memanggil vendor sama sekali — murni membaca kolom rasio yang sudah ada
 * lalu menerapkan formula_config terkini. Jalankan setiap kali bobot atau
 * ambang batas di formula_config diubah, supaya skor lama ikut menyesuaikan.
 */

declare(strict_types=1);

use Aigen\Core\Database;
use Aigen\Support\FundamentalScore;
use Aigen\Support\JobRunner;

require __DIR__ . '/bootstrap.php';

JobRunner::guard();

$job    = new JobRunner('recalculate_scores', $argv ?? [], PHP_SAPI === 'cli' ? 0 : 100);
$dryRun = $job->boolArg('dry-run');
$allDates = $job->boolArg('all');

$job->header('Hitung ulang skor fundamental');

$unmapped = FundamentalScore::unmappedKeys();
if ($unmapped !== []) {
    $job->warn('formula_config aktif tanpa pemetaan kolom: ' . implode(', ', $unmapped));
}

$formulas = FundamentalScore::activeFormulas();
if ($formulas === []) {
    $job->fail('Tidak ada formula aktif di formula_config. Tidak ada yang bisa dihitung.');
    exit(1);
}

$job->step(sprintf(
    'Memakai %d formula: %s',
    count($formulas),
    implode(', ', array_column($formulas, 'formula_key'))
));

if ($dryRun) {
    $job->line('  Mode uji coba — hanya membandingkan, tidak menyimpan.');
}

$pdo = Database::connection();

// Bawaan: hanya snapshot terbaru per emiten, karena itulah yang dibaca
// Screener. `--all` untuk merapikan seluruh riwayat.
$sql = 'SELECT s.id, s.stock_id, s.snapshot_date, s.fundamental_score,
               s.roe, s.roa, s.der, s.per, s.pbv, s.eps, s.bvps,
               s.dividend_yield, s.net_profit_margin, s.gross_profit_margin,
               s.current_ratio, s.quick_ratio,
               st.symbol
          FROM indicator_snapshot_fundamental s
          JOIN stocks st ON st.id = s.stock_id';

if (!$allDates) {
    $sql .= ' WHERE s.snapshot_date = (
                  SELECT MAX(s2.snapshot_date)
                    FROM indicator_snapshot_fundamental s2
                   WHERE s2.stock_id = s.stock_id
              )';
}

$sql .= ' ORDER BY st.symbol ASC, s.snapshot_date ASC';

$rows = $pdo->query($sql)->fetchAll();

if ($rows === []) {
    $job->warn('Belum ada snapshot fundamental. Jalankan dulu: php jobs/sync_fundamental.php');
    exit(0);
}

$job->step(sprintf('Memeriksa %d snapshot.', count($rows)));
$job->line();

$update = $pdo->prepare(
    'UPDATE indicator_snapshot_fundamental
        SET fundamental_score = :score, rating = :rating
      WHERE id = :id'
);

$changed   = 0;
$unchanged = 0;
$nulled    = 0;

foreach ($rows as $row) {
    $scoring = FundamentalScore::compute($row);
    $new     = $scoring['score'];
    $old     = $row['fundamental_score'] !== null ? (float) $row['fundamental_score'] : null;

    // Bandingkan pada presisi yang benar-benar disimpan (DECIMAL(5,2)),
    // supaya selisih pembulatan tidak dilaporkan sebagai perubahan.
    $same = ($old === null && $new === null)
        || ($old !== null && $new !== null && abs($old - $new) < 0.005);

    if ($same) {
        $unchanged++;
        continue;
    }

    if ($new === null) {
        $nulled++;
    }

    $job->step(sprintf(
        '%-6s %s  %s → %s  (%d/%d metrik)',
        $row['symbol'],
        $row['snapshot_date'],
        $old !== null ? str_pad(number_format($old, 2), 6, ' ', STR_PAD_LEFT) : '     —',
        $new !== null ? str_pad(number_format($new, 2), 6, ' ', STR_PAD_LEFT) : '     —',
        $scoring['used'],
        $scoring['total']
    ));

    if (!$dryRun) {
        $update->execute([
            'score'  => $new,
            'rating' => FundamentalScore::rating($new),
            'id'     => (int) $row['id'],
        ]);
    }

    $changed++;
}

$job->line();
$job->step("Berubah      : $changed");
$job->step("Tetap        : $unchanged");
if ($nulled > 0) {
    $job->step("Jadi kosong  : $nulled (rasio tidak cukup untuk dinilai)");
}

$job->done($dryRun
    ? 'Uji coba selesai — tidak ada yang disimpan.'
    : 'Perhitungan ulang selesai.');
