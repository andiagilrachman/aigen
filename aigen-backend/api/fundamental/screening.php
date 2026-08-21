<?php
// File: api/fundamental/screening.php
// PENTING: endpoint ini TIDAK PERNAH memanggil vendor API (DataSectors/Invezgo) langsung.
// Semua data dibaca dari indicator_snapshot_fundamental yang sudah diisi oleh
// jobs/sync_fundamental.php. Ini sesuai prinsip: satu kali fetch dari vendor per hari
// (via job), dipakai berulang kali oleh semua user tanpa menambah beban kuota API key.

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../core/CreditManager.php';

$user = Auth::requireLogin();

// Potong kredit dulu sebelum proses (sesuai pola credit_costs yang sudah dirancang)
if (!CreditManager::chargeForAction((int)$user['id'], 'run_screening')) {
    Response::error('Kredit Anda tidak cukup. Silakan top-up atau upgrade langganan.', 402);
}

try {
    $minScore = isset($_GET['min_score']) ? (float)$_GET['min_score'] : null;
    $sectorId = isset($_GET['sector_id']) ? (int)$_GET['sector_id'] : null;
    $minRoe = isset($_GET['min_roe']) ? (float)$_GET['min_roe'] : null;
    $maxDer = isset($_GET['max_der']) ? (float)$_GET['max_der'] : null;
    $maxPer = isset($_GET['max_per']) ? (float)$_GET['max_per'] : null;
    $sortBy = in_array($_GET['sort_by'] ?? '', ['fundamental_score', 'roe', 'der', 'per', 'pbv'])
        ? $_GET['sort_by'] : 'fundamental_score';
    $limit = min((int)($_GET['limit'] ?? 20), 100);

    $where = ['1=1'];
    $params = [];

    if ($minScore !== null) {
        $where[] = 'f.fundamental_score >= :min_score';
        $params['min_score'] = $minScore;
    }
    if ($sectorId !== null) {
        $where[] = 's.sector_id = :sector_id';
        $params['sector_id'] = $sectorId;
    }
    if ($minRoe !== null) {
        $where[] = 'f.roe >= :min_roe';
        $params['min_roe'] = $minRoe;
    }
    if ($maxDer !== null) {
        $where[] = 'f.der <= :max_der';
        $params['max_der'] = $maxDer;
    }
    if ($maxPer !== null) {
        $where[] = 'f.per <= :max_per';
        $params['max_per'] = $maxPer;
    }

    $whereClause = implode(' AND ', $where);

    // Ambil snapshot terbaru per saham (subquery tanggal terakhir)
    $sql = "SELECT s.id AS stock_id, s.symbol, s.company_name, sec.name AS sector_name,
                   f.snapshot_date, f.roe, f.roa, f.der, f.per, f.pbv, f.eps, f.dividend_yield,
                   f.revenue_growth_yoy, f.net_income_growth_yoy, f.fundamental_score, f.rating
            FROM indicator_snapshot_fundamental f
            INNER JOIN stocks s ON s.id = f.stock_id
            LEFT JOIN sectors sec ON sec.id = s.sector_id
            INNER JOIN (
                SELECT stock_id, MAX(snapshot_date) AS max_date
                FROM indicator_snapshot_fundamental
                GROUP BY stock_id
            ) latest ON latest.stock_id = f.stock_id AND latest.max_date = f.snapshot_date
            WHERE $whereClause AND s.is_active = 1
            ORDER BY f.$sortBy DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Simpan riwayat pencarian (opsional, untuk fitur "Recent Scan" nanti)
    Response::success([
        'total' => count($results),
        'results' => $results,
    ]);
} catch (Exception $e) {
    // Gagal setelah kredit terpotong -> refund otomatis (prinsip yang sudah disepakati)
    CreditManager::refundForAction((int)$user['id'], 'run_screening');
    Response::error('Gagal menjalankan screening, kredit dikembalikan', 500);
}
