<?php
// File: jobs/sync_stocks.php
// Jalankan manual dari admin (nanti disambungkan ke tombol "Sync") atau via cron.
// Ini SATU-SATUNYA tempat yang boleh memanggil InvezgoClient::stockList().

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/VendorClient/InvezgoClient.php';

$pdo = getDbConnection();

echo "Mulai sinkron master saham...\n";

try {
    $client = new InvezgoClient();
    $stocks = $client->stockList();
} catch (Exception $e) {
    echo "GAGAL: " . $e->getMessage() . "\n";
    $stocks = [];
}

if (empty($stocks)) {
    echo "Tidak ada data saham diterima dari vendor.\n";
    $stocks = []; // lanjut ke akhir file tanpa exit(), supaya aman dipanggil dari admin panel juga
}

$inserted = 0;
$updated = 0;

foreach ($stocks as $row) {
    // Struktur field disesuaikan saat pertama kali lihat response asli /analysis/list/stock
    $symbol = $row['code'] ?? $row['symbol'] ?? null;
    $name = $row['name'] ?? $row['company_name'] ?? null;
    if (!$symbol || !$name) {
        continue;
    }

    $sectorName = $row['sector'] ?? null;
    $sectorId = null;
    if ($sectorName) {
        $sectorStmt = $pdo->prepare('SELECT id FROM sectors WHERE name = :name LIMIT 1');
        $sectorStmt->execute(['name' => $sectorName]);
        $sector = $sectorStmt->fetch();
        if ($sector) {
            $sectorId = $sector['id'];
        } else {
            $insertSector = $pdo->prepare('INSERT INTO sectors (name) VALUES (:name)');
            $insertSector->execute(['name' => $sectorName]);
            $sectorId = $pdo->lastInsertId();
        }
    }

    $check = $pdo->prepare('SELECT id FROM stocks WHERE symbol = :symbol');
    $check->execute(['symbol' => $symbol]);
    $existing = $check->fetch();

    if ($existing) {
        $upd = $pdo->prepare(
            'UPDATE stocks SET company_name = :name, sector_id = :sector_id, updated_at = NOW() WHERE id = :id'
        );
        $upd->execute(['name' => $name, 'sector_id' => $sectorId, 'id' => $existing['id']]);
        $updated++;
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO stocks (symbol, company_name, sector_id, exchange, is_active, created_at)
             VALUES (:symbol, :name, :sector_id, "IDX", 1, NOW())'
        );
        $ins->execute(['symbol' => $symbol, 'name' => $name, 'sector_id' => $sectorId]);
        $inserted++;
    }
}

echo "Selesai. Ditambah: $inserted, Diperbarui: $updated, Total diterima: " . count($stocks) . "\n";
