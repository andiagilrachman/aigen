<?php

declare(strict_types=1);

namespace Aigen\Support;

/**
 * Penerjemah filter screener menjadi potongan SQL yang aman.
 *
 * Nilai selalu lewat parameter terikat, dan nama kolom TIDAK PERNAH diambil
 * langsung dari input pengguna — hanya kunci yang terdaftar di daftar putih
 * di bawah yang bisa menjadi nama kolom. Tanpa daftar putih ini, parameter
 * seperti ?sort=roe;DROP+TABLE akan menjadi celah SQL injection, karena nama
 * kolom tidak bisa diparameterisasi PDO.
 */
final class ScreenerFilter
{
    /**
     * Metrik yang boleh difilter dan diurutkan.
     * label dipakai frontend untuk membangun form filter secara dinamis.
     *
     * @var array<string,array{label:string,unit:string,higher_is_better:bool}>
     */
    private const METRICS = [
        'fundamental_score'     => ['label' => 'Skor Fundamental',        'unit' => 'skor',  'higher_is_better' => true],
        'roe'                   => ['label' => 'ROE',                     'unit' => '%',     'higher_is_better' => true],
        'roa'                   => ['label' => 'ROA',                     'unit' => '%',     'higher_is_better' => true],
        'der'                   => ['label' => 'DER',                     'unit' => 'x',     'higher_is_better' => false],
        'per'                   => ['label' => 'PER',                     'unit' => 'x',     'higher_is_better' => false],
        'pbv'                   => ['label' => 'PBV',                     'unit' => 'x',     'higher_is_better' => false],
        'eps'                   => ['label' => 'EPS',                     'unit' => 'Rp',    'higher_is_better' => true],
        'bvps'                  => ['label' => 'BVPS',                    'unit' => 'Rp',    'higher_is_better' => true],
        'dividend_yield'        => ['label' => 'Dividend Yield',          'unit' => '%',     'higher_is_better' => true],
        'revenue_growth_yoy'    => ['label' => 'Pertumbuhan Pendapatan',  'unit' => '%',     'higher_is_better' => true],
        'net_income_growth_yoy' => ['label' => 'Pertumbuhan Laba Bersih', 'unit' => '%',     'higher_is_better' => true],
        'net_profit_margin'     => ['label' => 'Net Profit Margin',       'unit' => '%',     'higher_is_better' => true],
        'gross_profit_margin'   => ['label' => 'Gross Profit Margin',     'unit' => '%',     'higher_is_better' => true],
        'current_ratio'         => ['label' => 'Current Ratio',           'unit' => 'x',     'higher_is_better' => true],
        'quick_ratio'           => ['label' => 'Quick Ratio',             'unit' => 'x',     'higher_is_better' => true],
        'altman_z_score'        => ['label' => 'Altman Z-Score',          'unit' => 'skor',  'higher_is_better' => true],
        'beneish_m_score'       => ['label' => 'Beneish M-Score',         'unit' => 'skor',  'higher_is_better' => false],
        'piotroski_f_score'     => ['label' => 'Piotroski F-Score',       'unit' => 'skor',  'higher_is_better' => true],
        'graham_number'         => ['label' => 'Graham Number',           'unit' => 'Rp',    'higher_is_better' => true],
    ];

    /** Kolom non-metrik yang juga boleh dipakai mengurutkan. */
    private const SORTABLE_EXTRA = [
        'symbol'       => 's.symbol',
        'company_name' => 's.company_name',
        'market_cap'   => 's.market_cap',
    ];

    /** @return array<string,array{label:string,unit:string,higher_is_better:bool}> */
    public static function metrics(): array
    {
        return self::METRICS;
    }

    public static function isMetric(string $key): bool
    {
        return isset(self::METRICS[$key]);
    }

    /**
     * Bangun klausa WHERE dari input filter.
     *
     * Format yang diterima (semua opsional):
     *   roe_min, roe_max, per_min, per_max, ... untuk setiap metrik
     *   sector_id, is_syariah, search
     *
     * @param  array<string,mixed> $input
     * @return array{sql:string,bindings:array<string,mixed>,applied:array<int,array<string,mixed>>}
     */
    public static function build(array $input): array
    {
        $conditions = [];
        $bindings = [];
        $applied = [];

        foreach (self::METRICS as $key => $meta) {
            foreach (['min' => '>=', 'max' => '<='] as $suffix => $operator) {
                $field = $key . '_' . $suffix;
                $value = $input[$field] ?? null;

                if ($value === null || $value === '' || !is_numeric($value)) {
                    continue;
                }

                $placeholder = 'f_' . $field;
                $conditions[] = "i.`$key` $operator :$placeholder";
                $bindings[$placeholder] = (float) $value;

                $applied[] = [
                    'metric'   => $key,
                    'label'    => $meta['label'],
                    'operator' => $suffix === 'min' ? '>=' : '<=',
                    'value'    => (float) $value,
                ];
            }
        }

        // Filter sektor
        $sectorId = $input['sector_id'] ?? null;
        if ($sectorId !== null && $sectorId !== '' && is_numeric($sectorId)) {
            $conditions[] = 's.sector_id = :f_sector_id';
            $bindings['f_sector_id'] = (int) $sectorId;
            $applied[] = ['metric' => 'sector_id', 'label' => 'Sektor', 'operator' => '=', 'value' => (int) $sectorId];
        }

        // Filter saham syariah
        $syariah = $input['is_syariah'] ?? null;
        if ($syariah !== null && $syariah !== '' && in_array((string) $syariah, ['0', '1'], true)) {
            $conditions[] = 's.is_syariah = :f_is_syariah';
            $bindings['f_is_syariah'] = (int) $syariah;
            $applied[] = ['metric' => 'is_syariah', 'label' => 'Saham Syariah', 'operator' => '=', 'value' => (int) $syariah];
        }

        // Pencarian kode atau nama emiten
        $search = trim((string) ($input['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(s.symbol LIKE :f_search OR s.company_name LIKE :f_search)';
            $bindings['f_search'] = '%' . $search . '%';
            $applied[] = ['metric' => 'search', 'label' => 'Pencarian', 'operator' => 'LIKE', 'value' => $search];
        }

        return [
            'sql'      => $conditions === [] ? '' : ' AND ' . implode(' AND ', $conditions),
            'bindings' => $bindings,
            'applied'  => $applied,
        ];
    }

    /**
     * Terjemahkan permintaan pengurutan menjadi klausa ORDER BY yang aman.
     *
     * @return array{sql:string,key:string,direction:string}
     */
    public static function orderBy(?string $sort, ?string $direction): array
    {
        $sort = (string) $sort;
        $direction = strtoupper((string) $direction) === 'ASC' ? 'ASC' : 'DESC';

        if (isset(self::METRICS[$sort])) {
            $column = "i.`$sort`";
        } elseif (isset(self::SORTABLE_EXTRA[$sort])) {
            $column = self::SORTABLE_EXTRA[$sort];
        } else {
            $sort = 'fundamental_score';
            $column = 'i.`fundamental_score`';
        }

        // NULL selalu ditaruh di akhir. Tanpa ini, emiten yang datanya belum
        // lengkap justru menempati puncak daftar saat mengurutkan menaik.
        $sql = " ORDER BY ($column IS NULL) ASC, $column $direction, s.symbol ASC";

        return ['sql' => $sql, 'key' => $sort, 'direction' => $direction];
    }
}
