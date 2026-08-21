<?php

/**
 * Menyiapkan database SQLite untuk pratinjau lokal.
 *
 * Environment pengembangan ini tidak memiliki server MySQL, sedangkan target
 * produksi adalah XAMPP/MariaDB. Skrip ini menerjemahkan schema.sql + seed.sql
 * yang SAMA PERSIS dengan yang dipakai produksi ke SQLite, lalu menambahkan
 * beberapa emiten contoh agar screener punya sesuatu untuk ditampilkan.
 *
 * Berkas ini murni alat bantu pengembangan — tidak pernah dipanggil aplikasi.
 *
 * Jalankan:  php tools/setup-preview-db.php storage/preview.sqlite
 */

declare(strict_types=1);

use Aigen\Core\Autoloader;
use Aigen\Tests\TestSchema;

require dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__) . '/src');
require dirname(__DIR__) . '/tests/TestSchema.php';

$target = $argv[1] ?? (dirname(__DIR__) . '/storage/preview.sqlite');
if ($target[0] !== '/') {
    $target = dirname(__DIR__) . '/' . $target;
}

if (file_exists($target)) {
    unlink($target);
}

@mkdir(dirname($target), 0777, true);

echo "Menyiapkan skema di $target\n";
$pdo = TestSchema::bootSqlite($target);
TestSchema::loadSeed($pdo);

// ---------------------------------------------------------------------------
// Data contoh: cukup untuk menguji screener dan halaman detail.
// ---------------------------------------------------------------------------

$sectors = [
    ['Financials', 'Banks'],
    ['Consumer Non-Cyclicals', 'Food & Beverage'],
    ['Basic Materials', 'Metals & Minerals'],
    ['Infrastructures', 'Telecommunication'],
    ['Energy', 'Coal'],
];

$insertSector = $pdo->prepare(
    'INSERT INTO sectors (name, sub_sector, created_at) VALUES (:name, :sub, CURRENT_TIMESTAMP)'
);
$sectorIds = [];
foreach ($sectors as [$name, $sub]) {
    $insertSector->execute(['name' => $name, 'sub' => $sub]);
    $sectorIds[$name] = (int) $pdo->lastInsertId();
}

/**
 * symbol, nama, sektor, syariah, kapitalisasi, lalu metrik fundamental.
 */
$stocks = [
    ['BBCA', 'PT Bank Central Asia Tbk', 'Bank BCA', 'Financials', 0, 1_180_000_000_000_000, [
        'roe' => 21.4, 'roa' => 3.6, 'der' => 4.8, 'per' => 24.1, 'pbv' => 4.9,
        'eps' => 395.0, 'bvps' => 1_940.0, 'dividend_yield' => 2.4,
        'revenue_growth_yoy' => 9.8, 'net_income_growth_yoy' => 12.6,
        'net_profit_margin' => 43.2, 'gross_profit_margin' => 71.5,
        'current_ratio' => 1.28, 'quick_ratio' => 1.12,
        'altman_z_score' => 2.1, 'beneish_m_score' => -2.7, 'piotroski_f_score' => 8,
        'graham_number' => 4_150.0, 'fundamental_score' => 88.5, 'rating' => 'Sangat Baik',
    ]],
    ['ICBP', 'PT Indofood CBP Sukses Makmur Tbk', 'Indofood CBP', 'Consumer Non-Cyclicals', 1, 128_000_000_000_000, [
        'roe' => 17.9, 'roa' => 7.1, 'der' => 1.02, 'per' => 14.8, 'pbv' => 2.6,
        'eps' => 745.0, 'bvps' => 4_210.0, 'dividend_yield' => 2.9,
        'revenue_growth_yoy' => 6.4, 'net_income_growth_yoy' => 18.2,
        'net_profit_margin' => 11.8, 'gross_profit_margin' => 35.4,
        'current_ratio' => 2.14, 'quick_ratio' => 1.68,
        'altman_z_score' => 3.4, 'beneish_m_score' => -2.9, 'piotroski_f_score' => 7,
        'graham_number' => 8_400.0, 'fundamental_score' => 79.2, 'rating' => 'Baik',
    ]],
    ['ANTM', 'PT Aneka Tambang Tbk', 'Antam', 'Basic Materials', 1, 38_500_000_000_000, [
        'roe' => 11.2, 'roa' => 7.4, 'der' => 0.48, 'per' => 12.9, 'pbv' => 1.4,
        'eps' => 124.0, 'bvps' => 1_140.0, 'dividend_yield' => 4.1,
        'revenue_growth_yoy' => -3.2, 'net_income_growth_yoy' => -8.7,
        'net_profit_margin' => 8.1, 'gross_profit_margin' => 16.9,
        'current_ratio' => 2.42, 'quick_ratio' => 1.71,
        'altman_z_score' => 3.9, 'beneish_m_score' => -2.4, 'piotroski_f_score' => 6,
        'graham_number' => 1_780.0, 'fundamental_score' => 68.4, 'rating' => 'Cukup',
    ]],
    ['TLKM', 'PT Telkom Indonesia (Persero) Tbk', 'Telkom', 'Infrastructures', 1, 297_000_000_000_000, [
        'roe' => 16.8, 'roa' => 8.2, 'der' => 0.94, 'per' => 13.2, 'pbv' => 2.2,
        'eps' => 236.0, 'bvps' => 1_410.0, 'dividend_yield' => 5.6,
        'revenue_growth_yoy' => 3.7, 'net_income_growth_yoy' => 1.9,
        'net_profit_margin' => 16.4, 'gross_profit_margin' => 52.1,
        'current_ratio' => 0.94, 'quick_ratio' => 0.88,
        'altman_z_score' => 2.8, 'beneish_m_score' => -3.1, 'piotroski_f_score' => 7,
        'graham_number' => 2_730.0, 'fundamental_score' => 76.1, 'rating' => 'Baik',
    ]],
    ['PTBA', 'PT Bukit Asam Tbk', 'Bukit Asam', 'Energy', 1, 31_200_000_000_000, [
        'roe' => 22.6, 'roa' => 16.4, 'der' => 0.36, 'per' => 6.1, 'pbv' => 1.3,
        'eps' => 442.0, 'bvps' => 2_080.0, 'dividend_yield' => 11.8,
        'revenue_growth_yoy' => -12.4, 'net_income_growth_yoy' => -24.1,
        'net_profit_margin' => 14.9, 'gross_profit_margin' => 27.8,
        'current_ratio' => 2.86, 'quick_ratio' => 2.31,
        'altman_z_score' => 4.6, 'beneish_m_score' => -2.6, 'piotroski_f_score' => 6,
        'graham_number' => 4_540.0, 'fundamental_score' => 72.8, 'rating' => 'Baik',
    ]],
    ['GOTO', 'PT GoTo Gojek Tokopedia Tbk', 'GoTo', 'Infrastructures', 0, 68_400_000_000_000, [
        'roe' => -18.4, 'roa' => -11.2, 'der' => 0.21, 'per' => null, 'pbv' => 1.1,
        'eps' => -12.0, 'bvps' => 52.0, 'dividend_yield' => 0.0,
        'revenue_growth_yoy' => 24.6, 'net_income_growth_yoy' => 31.4,
        'net_profit_margin' => -46.2, 'gross_profit_margin' => 38.7,
        'current_ratio' => 3.12, 'quick_ratio' => 3.02,
        'altman_z_score' => 1.2, 'beneish_m_score' => -1.9, 'piotroski_f_score' => 3,
        'graham_number' => null, 'fundamental_score' => 34.6, 'rating' => 'Lemah',
    ]],
];

$insertStock = $pdo->prepare(
    'INSERT INTO stocks (symbol, company_name, company_name_short, sector_id, exchange,
                         listing_date, website, logo_url, address, is_syariah, is_active,
                         market_cap, created_at, updated_at)
     VALUES (:symbol, :name, :short, :sector, :exchange, :listing, :website, NULL, :address,
             :syariah, 1, :cap, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
);

$metricColumns = [
    'roe', 'roa', 'der', 'per', 'pbv', 'eps', 'bvps', 'dividend_yield',
    'revenue_growth_yoy', 'net_income_growth_yoy', 'net_profit_margin',
    'gross_profit_margin', 'current_ratio', 'quick_ratio', 'altman_z_score',
    'beneish_m_score', 'piotroski_f_score', 'graham_number', 'fundamental_score',
];

$insertSnapshot = $pdo->prepare(
    'INSERT INTO indicator_snapshot_fundamental
        (stock_id, snapshot_date, ' . implode(', ', $metricColumns) . ', rating, created_at)
     VALUES (:stock_id, :snapshot_date, :' . implode(', :', $metricColumns) . ', :rating, CURRENT_TIMESTAMP)'
);

$insertHistory = $pdo->prepare(
    'INSERT INTO indicator_history_fundamental
        (stock_id, period_label, period_type, fiscal_year, account_name,
         statement_type, account_level, amount, created_at)
     VALUES (:stock_id, :period, :ptype, :year, :account, :stype, :level, :amount, CURRENT_TIMESTAMP)'
);

$insertHolder = $pdo->prepare(
    'INSERT INTO shareholder_composition
        (stock_id, holder_name, percentage, badge, source, snapshot_date, created_at)
     VALUES (:stock_id, :holder, :pct, :badge, :source, :date, CURRENT_TIMESTAMP)'
);

$accounts = [
    'IS' => [
        ['Pendapatan', 1, 1.0],
        ['Beban Pokok Pendapatan', 2, -0.62],
        ['Laba Kotor', 1, 0.38],
        ['Beban Usaha', 2, -0.19],
        ['Laba Usaha', 1, 0.19],
        ['Laba Bersih', 1, 0.14],
    ],
    'BS' => [
        ['Total Aset', 1, 3.4],
        ['Aset Lancar', 2, 1.5],
        ['Total Liabilitas', 1, 1.7],
        ['Liabilitas Jangka Pendek', 2, 0.8],
        ['Total Ekuitas', 1, 1.7],
    ],
    'CF' => [
        ['Arus Kas Operasi', 1, 0.22],
        ['Arus Kas Investasi', 1, -0.12],
        ['Arus Kas Pendanaan', 1, -0.06],
        ['Kenaikan Kas Bersih', 1, 0.04],
    ],
];

$snapshotDate = date('Y-m-d');

foreach ($stocks as [$symbol, $name, $short, $sectorName, $syariah, $cap, $metrics]) {
    $insertStock->execute([
        'symbol'   => $symbol,
        'name'     => $name,
        'short'    => $short,
        'sector'   => $sectorIds[$sectorName],
        'exchange' => 'IDX',
        'listing'  => '2010-01-15',
        'website'  => 'https://www.idx.co.id',
        'address'  => 'Jakarta, Indonesia',
        'syariah'  => $syariah,
        'cap'      => $cap,
    ]);
    $stockId = (int) $pdo->lastInsertId();

    $params = ['stock_id' => $stockId, 'snapshot_date' => $snapshotDate, 'rating' => $metrics['rating']];
    foreach ($metricColumns as $column) {
        $params[$column] = $metrics[$column] ?? null;
    }
    $insertSnapshot->execute($params);

    // Riwayat laporan keuangan tiga tahun terakhir, diturunkan dari kapitalisasi
    // supaya angkanya proporsional dan enak dibaca saat pratinjau.
    $revenue = $cap * 0.28;
    foreach ([2024, 2023, 2022] as $offset => $year) {
        $factor = 1 - ($offset * 0.08);
        foreach ($accounts as $statementType => $lines) {
            foreach ($lines as [$account, $level, $ratio]) {
                $insertHistory->execute([
                    'stock_id' => $stockId,
                    'period'   => "FY$year",
                    'ptype'    => 'FY',
                    'year'     => $year,
                    'account'  => $account,
                    'stype'    => $statementType,
                    'level'    => $level,
                    'amount'   => round($revenue * $ratio * $factor),
                ]);
            }
        }
    }

    foreach ([
        ['Pemegang Saham Pengendali', 54.2, 'Pengendali'],
        ['Masyarakat (Publik)', 31.6, 'Publik'],
        ['Investor Institusi Asing', 9.4, 'Institusi'],
        ['Manajemen & Karyawan', 4.8, 'Afiliasi'],
    ] as [$holder, $pct, $badge]) {
        $insertHolder->execute([
            'stock_id' => $stockId,
            'holder'   => $holder,
            'pct'      => $pct,
            'badge'    => $badge,
            'source'   => 'preview-seed',
            'date'     => $snapshotDate,
        ]);
    }
}

// ---------------------------------------------------------------------------
// Akun demo, lengkap dengan dompet kredit.
// ---------------------------------------------------------------------------

$email = 'demo@aigen.test';
$password = 'demo1234';

$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, role, status, created_at, updated_at)
     VALUES (:name, :email, :hash, :role, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
);
$stmt->execute([
    'name'   => 'Pengguna Demo',
    'email'  => $email,
    'hash'   => password_hash($password, PASSWORD_DEFAULT),
    'role'   => 'user',
    'status' => 'active',
]);
$userId = (int) $pdo->lastInsertId();

$pdo->prepare(
    'INSERT INTO credit_wallets (user_id, balance, updated_at)
     VALUES (:id, 100, CURRENT_TIMESTAMP)'
)->execute(['id' => $userId]);

$pdo->prepare(
    'INSERT INTO credit_transactions (user_id, type, amount, balance_after, note, created_at)
     VALUES (:id, :type, 100, 100, :note, CURRENT_TIMESTAMP)'
)->execute([
    'id'   => $userId,
    'type' => 'trial',
    'note' => 'Bonus kredit masa uji coba',
]);

$counts = [];
foreach (['stocks', 'sectors', 'indicator_snapshot_fundamental', 'indicator_history_fundamental',
          'shareholder_composition', 'nav_menu', 'theme_presets', 'users'] as $table) {
    $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
}

echo "Selesai.\n";
foreach ($counts as $table => $count) {
    printf("  %-34s %d\n", $table, $count);
}
echo "\nAkun demo: $email / $password\n";
