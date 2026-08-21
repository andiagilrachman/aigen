<?php
// File: api/stocks/list.php
// Baca dari tabel lokal saja (hasil sync_stocks.php) — tidak panggil vendor.

require_once __DIR__ . '/../../config/bootstrap.php';

$search = trim($_GET['q'] ?? '');
$limit = min((int)($_GET['limit'] ?? 20), 100);

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.symbol, s.company_name, s.company_name_short, sec.name AS sector_name, s.logo_url
         FROM stocks s
         LEFT JOIN sectors sec ON sec.id = s.sector_id
         WHERE s.is_active = 1 AND (s.symbol LIKE :q OR s.company_name LIKE :q)
         ORDER BY s.symbol ASC
         LIMIT :limit'
    );
    $stmt->bindValue('q', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
} else {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.symbol, s.company_name, s.company_name_short, sec.name AS sector_name, s.logo_url
         FROM stocks s
         LEFT JOIN sectors sec ON sec.id = s.sector_id
         WHERE s.is_active = 1
         ORDER BY s.symbol ASC
         LIMIT :limit'
    );
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
}

$stmt->execute();
Response::success($stmt->fetchAll());
