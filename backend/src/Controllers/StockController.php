<?php

declare(strict_types=1);

namespace Aigen\Controllers;

use Aigen\Core\Database;
use Aigen\Core\Logger;
use Aigen\Core\Request;
use Aigen\Core\Response;
use Aigen\Credit\InsufficientCreditException;
use Aigen\Credit\UnknownActionException;
use Aigen\Credit\UsageGate;
use Aigen\Support\ActivityLog;
use Aigen\Support\ScreenerFilter;
use Throwable;

final class StockController
{
    /**
     * Detail emiten. Aksi berbayar (2 kredit).
     */
    public function show(Request $request): void
    {
        $symbol = strtoupper(trim((string) $request->param('symbol')));

        if ($symbol === '' || !preg_match('/^[A-Z0-9.\-]{1,10}$/', $symbol)) {
            Response::error('Kode emiten tidak valid', 422, 'invalid_symbol');
            return;
        }

        $stock = $this->findStock($symbol);
        if ($stock === null) {
            Response::error("Emiten $symbol tidak ditemukan", 404, 'stock_not_found');
            return;
        }

        $userId = $request->userId();

        // Kredit baru dipotong SETELAH emiten dipastikan ada. Memungut biaya
        // untuk kode yang tidak ada sama saja mengambil kredit tanpa imbalan.
        try {
            $gate = UsageGate::open($userId, 'view_stock_detail');
        } catch (InsufficientCreditException $e) {
            Response::error($e->getMessage(), 402, 'insufficient_credit');
            return;
        } catch (UnknownActionException $e) {
            Logger::error($e->getMessage());
            Response::error('Konfigurasi biaya belum lengkap. Hubungi admin.', 500, 'missing_credit_cost');
            return;
        }

        try {
            $stockId = (int) $stock['id'];

            $payload = [
                'stock'        => $stock,
                'snapshot'     => $this->latestSnapshot($stockId),
                'metrics_meta' => $this->metricsMeta(),
                'financials'   => $this->financialHistory($stockId),
                'shareholders' => $this->shareholders($stockId),
                'in_watchlist' => $this->inWatchlist($userId, $stockId),
            ];

            $gate->commit();
        } catch (Throwable $e) {
            $gate->rollback('Gagal memuat detail emiten, kredit dikembalikan');
            Logger::exception($e);
            Response::error('Gagal memuat detail emiten. Kredit Anda telah dikembalikan.', 500, 'detail_failed');
            return;
        }

        ActivityLog::record($userId, 'view_stock_detail', "Melihat detail $symbol", $request->ip(), [
            'symbol' => $symbol,
        ]);

        Response::success($payload, $gate->meta());
    }

    /**
     * Daftar emiten ringkas untuk autocomplete. Gratis.
     */
    public function index(Request $request): void
    {
        $search = trim($request->string('search', ''));
        $limit = max(1, min($request->int('limit', 20) ?? 20, 100));

        $sql = 'SELECT s.id, s.symbol, s.company_name, s.logo_url, sec.name AS sector_name
                  FROM stocks s
                  LEFT JOIN sectors sec ON sec.id = s.sector_id
                 WHERE s.is_active = 1';
        $bindings = [];

        if ($search !== '') {
            $sql .= ' AND (s.symbol LIKE :q OR s.company_name LIKE :q)';
            $bindings['q'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY s.symbol ASC LIMIT $limit";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bindings);

        $items = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            return $row;
        }, $stmt->fetchAll());

        Response::success(['items' => $items]);
    }

    private function findStock(string $symbol): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.id, s.symbol, s.company_name, s.company_name_short, s.exchange,
                    s.listing_date, s.website, s.logo_url, s.address, s.is_syariah,
                    s.market_cap, s.sector_id, sec.name AS sector_name, sec.sub_sector
               FROM stocks s
               LEFT JOIN sectors sec ON sec.id = s.sector_id
              WHERE s.symbol = :symbol AND s.is_active = 1
              LIMIT 1'
        );
        $stmt->execute(['symbol' => $symbol]);
        $stock = $stmt->fetch();

        if ($stock === false) {
            return null;
        }

        $stock['id'] = (int) $stock['id'];
        $stock['is_syariah'] = (bool) (int) $stock['is_syariah'];
        $stock['market_cap'] = $stock['market_cap'] === null ? null : (float) $stock['market_cap'];
        $stock['sector_id'] = $stock['sector_id'] === null ? null : (int) $stock['sector_id'];

        return $stock;
    }

    private function latestSnapshot(int $stockId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM indicator_snapshot_fundamental
              WHERE stock_id = :id
              ORDER BY snapshot_date DESC
              LIMIT 1'
        );
        $stmt->execute(['id' => $stockId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        unset($row['id'], $row['stock_id']);

        if (isset($row['vendor_insight_score']) && is_string($row['vendor_insight_score'])) {
            $row['vendor_insight_score'] = json_decode($row['vendor_insight_score'], true);
        }

        foreach ($row as $key => $value) {
            if ($value === null || in_array($key, ['snapshot_date', 'rating', 'created_at', 'vendor_insight_score'], true)) {
                continue;
            }
            $row[$key] = $key === 'piotroski_f_score' ? (int) $value : (float) $value;
        }

        return $row;
    }

    /**
     * Label, satuan, dan arah tiap metrik.
     *
     * Dikirim bersama data supaya frontend tidak perlu menyalin daftar metrik —
     * kalau daftar di backend berubah, tampilan ikut menyesuaikan sendiri.
     */
    private function metricsMeta(): array
    {
        $meta = [];
        foreach (ScreenerFilter::metrics() as $key => $info) {
            $meta[$key] = $info;
        }
        return $meta;
    }

    /** Riwayat laporan keuangan, dikelompokkan per jenis laporan dan periode. */
    private function financialHistory(int $stockId, int $limitPeriods = 8): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT period_label, period_type, fiscal_year, statement_type,
                    account_name, account_level, amount
               FROM indicator_history_fundamental
              WHERE stock_id = :id
              ORDER BY fiscal_year DESC, period_label DESC, id ASC'
        );
        $stmt->execute(['id' => $stockId]);

        $grouped = ['BS' => [], 'IS' => [], 'CF' => []];

        foreach ($stmt->fetchAll() as $row) {
            $type = $row['statement_type'];
            if (!isset($grouped[$type])) {
                continue;
            }

            $period = $row['period_label'];
            $grouped[$type][$period][] = [
                'account_name'  => $row['account_name'],
                'account_level' => (int) $row['account_level'],
                'amount'        => $row['amount'] === null ? null : (float) $row['amount'],
            ];
        }

        // Batasi jumlah periode agar respons tidak membengkak.
        foreach ($grouped as $type => $periods) {
            $grouped[$type] = array_slice($periods, 0, $limitPeriods, true);
        }

        return $grouped;
    }

    private function shareholders(int $stockId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT holder_name, percentage, badge, snapshot_date
               FROM shareholder_composition
              WHERE stock_id = :id
                AND snapshot_date = (
                    SELECT MAX(snapshot_date) FROM shareholder_composition WHERE stock_id = :id2
                )
              ORDER BY percentage DESC'
        );
        $stmt->execute(['id' => $stockId, 'id2' => $stockId]);

        return array_map(static function (array $row): array {
            $row['percentage'] = $row['percentage'] === null ? null : (float) $row['percentage'];
            return $row;
        }, $stmt->fetchAll());
    }

    private function inWatchlist(int $userId, int $stockId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM watchlists WHERE user_id = :u AND stock_id = :s LIMIT 1'
        );
        $stmt->execute(['u' => $userId, 's' => $stockId]);

        return $stmt->fetchColumn() !== false;
    }
}
