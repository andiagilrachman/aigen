<?php
// File: api/fundamental/snapshot.php
// Sama seperti screening.php — hanya baca dari tabel lokal, tidak panggil vendor.

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../core/CreditManager.php';

$user = Auth::requireLogin();
$symbol = strtoupper(trim($_GET['symbol'] ?? ''));

if ($symbol === '') {
    Response::error('Parameter symbol wajib diisi', 422);
}

if (!CreditManager::chargeForAction((int)$user['id'], 'view_stock_detail')) {
    Response::error('Kredit Anda tidak cukup. Silakan top-up atau upgrade langganan.', 402);
}

try {
    $stmt = $pdo->prepare(
        'SELECT s.*, sec.name AS sector_name
         FROM stocks s LEFT JOIN sectors sec ON sec.id = s.sector_id
         WHERE s.symbol = :symbol'
    );
    $stmt->execute(['symbol' => $symbol]);
    $stock = $stmt->fetch();

    if (!$stock) {
        CreditManager::refundForAction((int)$user['id'], 'view_stock_detail');
        Response::error('Saham tidak ditemukan', 404);
    }

    $snapStmt = $pdo->prepare(
        'SELECT * FROM indicator_snapshot_fundamental
         WHERE stock_id = :id ORDER BY snapshot_date DESC LIMIT 1'
    );
    $snapStmt->execute(['id' => $stock['id']]);
    $snapshot = $snapStmt->fetch();

    $shareholderStmt = $pdo->prepare(
        'SELECT holder_name, percentage, badge FROM shareholder_composition
         WHERE stock_id = :id ORDER BY percentage DESC LIMIT 10'
    );
    $shareholderStmt->execute(['id' => $stock['id']]);

    Response::success([
        'stock' => $stock,
        'fundamental' => $snapshot ?: null,
        'shareholders' => $shareholderStmt->fetchAll(),
    ]);
} catch (Exception $e) {
    CreditManager::refundForAction((int)$user['id'], 'view_stock_detail');
    Response::error('Gagal memuat detail saham, kredit dikembalikan', 500);
}
