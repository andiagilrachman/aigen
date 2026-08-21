<?php
// File: jobs/recalculate_scores.php
// Hitung ulang fundamental_score dari data ROE/ROA/DER/PER/PBV yang SUDAH tersimpan
// di indicator_snapshot_fundamental — tidak memanggil vendor sama sekali, jadi cepat
// dan tidak makan kuota API. Dipakai setelah formula_config diisi/diubah.

require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "Mulai hitung ulang skor...\n";

$configs = $pdo->query(
    'SELECT formula_key, weight, threshold_good, threshold_bad FROM formula_config WHERE is_active = 1'
)->fetchAll();

if (empty($configs)) {
    echo "formula_config masih kosong. Isi dulu sebelum jalankan skrip ini.\n";
    return;
}

$rows = $pdo->query('SELECT id, roe, roa, der, per, pbv FROM indicator_snapshot_fundamental')->fetchAll();

$updated = 0;
$updateStmt = $pdo->prepare('UPDATE indicator_snapshot_fundamental SET fundamental_score = :score WHERE id = :id');

foreach ($rows as $row) {
    $metrics = [
        'roe' => $row['roe'],
        'roa' => $row['roa'],
        'der' => $row['der'],
        'per' => $row['per'],
        'pbv' => $row['pbv'],
    ];

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

    $score = $totalWeight > 0 ? round($weightedScore / $totalWeight, 2) : null;

    $updateStmt->execute(['score' => $score, 'id' => $row['id']]);
    $updated++;
}

echo "Selesai. $updated baris diperbarui skornya.\n";
