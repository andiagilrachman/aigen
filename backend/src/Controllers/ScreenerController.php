<?php

declare(strict_types=1);

namespace Aigen\Controllers;

use Aigen\Core\Database;
use Aigen\Core\Logger;
use Aigen\Core\Request;
use Aigen\Core\Response;
use Aigen\Core\Settings;
use Aigen\Credit\InsufficientCreditException;
use Aigen\Credit\UnknownActionException;
use Aigen\Credit\UsageGate;
use Aigen\Support\ActivityLog;
use Aigen\Support\ScreenerFilter;
use Throwable;

/**
 * Screener fundamental — fitur inti fase 1.
 */
final class ScreenerController
{
    /**
     * Metadata untuk membangun form filter di frontend.
     * Gratis: tanpa ini pengguna bahkan tidak bisa melihat form screener.
     */
    public function options(Request $request): void
    {
        $sectors = Database::connection()->query(
            'SELECT id, name, sub_sector FROM sectors ORDER BY name ASC, sub_sector ASC'
        )->fetchAll();

        $metrics = [];
        foreach (ScreenerFilter::metrics() as $key => $meta) {
            $metrics[] = [
                'key'              => $key,
                'label'            => $meta['label'],
                'unit'             => $meta['unit'],
                'higher_is_better' => $meta['higher_is_better'],
            ];
        }

        Response::success([
            'metrics'       => $metrics,
            'sectors'       => $sectors,
            'default_limit' => Settings::int('screening_default_limit', 50),
            'max_limit'     => Settings::int('screening_max_limit', 100),
        ]);
    }

    /**
     * Jalankan screening. Aksi berbayar.
     *
     * Seluruh pekerjaan dibungkus UsageGate: kalau kueri gagal di tengah jalan,
     * kredit yang sudah dipotong dikembalikan otomatis. Versi lama memotong
     * kredit lebih dulu tanpa jaring pengaman semacam ini.
     */
    public function run(Request $request): void
    {
        $userId = $request->userId();

        try {
            $gate = UsageGate::open($userId, 'run_screening');
        } catch (InsufficientCreditException $e) {
            Response::error($e->getMessage(), 402, 'insufficient_credit', []);
            return;
        } catch (UnknownActionException $e) {
            Logger::error($e->getMessage());
            Response::error('Konfigurasi biaya belum lengkap. Hubungi admin.', 500, 'missing_credit_cost');
            return;
        }

        try {
            $result = $this->query($request);
            $gate->commit();
        } catch (Throwable $e) {
            $gate->rollback('Screening gagal, kredit dikembalikan');
            Logger::exception($e);
            Response::error('Gagal menjalankan screening. Kredit Anda telah dikembalikan.', 500, 'screening_failed');
            return;
        }

        ActivityLog::record($userId, 'run_screening', 'Menjalankan screening fundamental', $request->ip(), [
            'filter_count' => count($result['applied_filters']),
            'total'        => $result['total'],
        ]);

        Response::success($result, $gate->meta());
    }

    /** @return array<string,mixed> */
    private function query(Request $request): array
    {
        $input = $request->all();

        $maxLimit = Settings::int('screening_max_limit', 100);
        $limit = $request->int('limit', Settings::int('screening_default_limit', 50)) ?? 50;
        $limit = max(1, min($limit, $maxLimit));

        $page = max(1, $request->int('page', 1) ?? 1);
        $offset = ($page - 1) * $limit;

        $filter = ScreenerFilter::build($input);
        $order = ScreenerFilter::orderBy(
            $request->string('sort', 'fundamental_score'),
            $request->string('direction', 'DESC')
        );

        // Hanya snapshot TERBARU per emiten yang dipakai. Sub-kueri ini penting:
        // tanpa itu, emiten dengan banyak snapshot akan muncul berkali-kali.
        $baseFrom = '
            FROM stocks s
            INNER JOIN indicator_snapshot_fundamental i
                    ON i.stock_id = s.id
                   AND i.snapshot_date = (
                        SELECT MAX(i2.snapshot_date)
                          FROM indicator_snapshot_fundamental i2
                         WHERE i2.stock_id = s.id
                   )
            LEFT JOIN sectors sec ON sec.id = s.sector_id
            WHERE s.is_active = 1';

        $pdo = Database::connection();

        $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $baseFrom . $filter['sql']);
        $countStmt->execute($filter['bindings']);
        $total = (int) $countStmt->fetchColumn();

        $sql = '
            SELECT s.id, s.symbol, s.company_name, s.company_name_short, s.logo_url,
                   s.is_syariah, s.market_cap,
                   sec.name AS sector_name, sec.sub_sector,
                   i.snapshot_date, i.fundamental_score, i.rating,
                   i.roe, i.roa, i.der, i.per, i.pbv, i.eps, i.bvps,
                   i.dividend_yield, i.revenue_growth_yoy, i.net_income_growth_yoy,
                   i.net_profit_margin, i.gross_profit_margin,
                   i.current_ratio, i.quick_ratio,
                   i.altman_z_score, i.beneish_m_score, i.piotroski_f_score, i.graham_number'
            . $baseFrom . $filter['sql'] . $order['sql']
            . " LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($filter['bindings']);
        $rows = array_map([$this, 'castRow'], $stmt->fetchAll());

        return [
            'items'            => $rows,
            'total'            => $total,
            'page'             => $page,
            'limit'            => $limit,
            'total_pages'      => $limit > 0 ? (int) ceil($total / $limit) : 0,
            'sort'             => $order['key'],
            'direction'        => $order['direction'],
            'applied_filters'  => $filter['applied'],
        ];
    }

    /** Ubah string hasil PDO menjadi tipe numerik yang benar untuk JSON. */
    private function castRow(array $row): array
    {
        $intFields = ['id', 'is_syariah', 'piotroski_f_score'];
        $floatFields = [
            'market_cap', 'fundamental_score', 'roe', 'roa', 'der', 'per', 'pbv',
            'eps', 'bvps', 'dividend_yield', 'revenue_growth_yoy', 'net_income_growth_yoy',
            'net_profit_margin', 'gross_profit_margin', 'current_ratio', 'quick_ratio',
            'altman_z_score', 'beneish_m_score', 'graham_number',
        ];

        foreach ($intFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
        }
        foreach ($floatFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (float) $row[$field];
            }
        }

        return $row;
    }
}
