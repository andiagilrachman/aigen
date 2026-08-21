<?php

/**
 * Sinkronisasi master emiten dari vendor ke tabel `stocks` dan `sectors`.
 *
 * Jalankan ini LEBIH DULU sebelum sync_fundamental.php — job fundamental
 * bekerja per emiten yang sudah terdaftar di sini.
 *
 *   php jobs/sync_stocks.php
 *   php jobs/sync_stocks.php --dry-run     # lihat rencananya tanpa menulis
 *
 * Cukup dijalankan sesekali (emiten baru IPO tidak setiap hari).
 */

declare(strict_types=1);

use Aigen\Core\Database;
use Aigen\Support\JobRunner;
use Aigen\Vendor\InvezgoClient;
use Aigen\Vendor\VendorException;

require __DIR__ . '/bootstrap.php';

JobRunner::guard();

$job    = new JobRunner('sync_stocks', $argv ?? []);
$dryRun = $job->boolArg('dry-run');

$job->header('Sinkronisasi master emiten');

if ($dryRun) {
    $job->line('Mode uji coba — tidak ada yang ditulis ke database.');
}

try {
    $client = new InvezgoClient();
} catch (VendorException $e) {
    $job->fail($e->getMessage());
    exit(1);
}

try {
    $job->step('Mengambil daftar emiten dari vendor...');
    $rows = $client->stockList();
} catch (VendorException $e) {
    $job->fail($e->getMessage());
    $job->line(match (true) {
        $e->isQuotaExhausted() => '  Kuota harian sudah terpakai habis. Jalankan lagi besok.',
        $e->isAuthProblem()    => '  Periksa API key / status langganan sebelum mencoba lagi.',
        default                => '  Gangguan sementara. Coba ulang beberapa saat lagi.',
    });
    exit(1);
}

if ($rows === []) {
    $job->warn('Vendor tidak mengembalikan satu pun emiten. Tidak ada yang diubah.');
    exit(0);
}

$job->step(sprintf('Diterima %d emiten.', count($rows)));

$pdo = Database::connection();

/**
 * Cari atau buat sektor, hasilnya diingat agar tidak menanyakan hal yang sama
 * ratusan kali.
 *
 * @param array<string,int> $cache
 */
$resolveSector = static function (?string $name, ?string $subSector, array &$cache, bool $dryRun) use ($pdo): ?int {
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }

    // sub_sector NOT NULL DEFAULT '' — string kosong, bukan NULL, supaya
    // UNIQUE(name, sub_sector) benar-benar menahan duplikat. Di MySQL dua baris
    // dengan NULL yang sama tetap dianggap berbeda.
    $sub      = trim((string) $subSector);
    $cacheKey = $name . "\0" . $sub;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare('SELECT id FROM sectors WHERE name = :n AND sub_sector = :s');
    $stmt->execute(['n' => $name, 's' => $sub]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        return $cache[$cacheKey] = (int) $id;
    }

    if ($dryRun) {
        return null;
    }

    $insert = $pdo->prepare(
        'INSERT INTO sectors (name, sub_sector, created_at) VALUES (:n, :s, ' . nowExpr() . ')'
    );
    $insert->execute(['n' => $name, 's' => $sub]);

    return $cache[$cacheKey] = (int) $pdo->lastInsertId();
};

/** Ambil nilai pertama yang ada dari beberapa kemungkinan nama field. */
function pick(array $row, array $keys): ?string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
            return trim((string) $row[$key]);
        }
    }
    return null;
}

function nowExpr(): string
{
    return Database::driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
}

$sectorCache = [];
$inserted    = 0;
$updated     = 0;
$skipped     = 0;
$newSectors  = 0;

$sectorsBefore = (int) $pdo->query('SELECT COUNT(*) FROM sectors')->fetchColumn();

$upsertSql = Database::driver() === 'sqlite'
    ? 'INSERT INTO stocks (symbol, company_name, company_name_short, sector_id, exchange,
                           listing_date, website, logo_url, is_syariah, is_active, created_at, updated_at)
       VALUES (:symbol, :name, :short, :sector, :exchange,
               :listing, :website, :logo, :syariah, 1, datetime(\'now\'), datetime(\'now\'))
       ON CONFLICT (symbol) DO UPDATE SET
           company_name       = excluded.company_name,
           company_name_short = excluded.company_name_short,
           sector_id          = excluded.sector_id,
           exchange           = excluded.exchange,
           listing_date       = COALESCE(excluded.listing_date, stocks.listing_date),
           website            = COALESCE(excluded.website, stocks.website),
           logo_url           = COALESCE(excluded.logo_url, stocks.logo_url),
           is_syariah         = excluded.is_syariah,
           updated_at         = datetime(\'now\')'
    : 'INSERT INTO stocks (symbol, company_name, company_name_short, sector_id, exchange,
                           listing_date, website, logo_url, is_syariah, is_active, created_at, updated_at)
       VALUES (:symbol, :name, :short, :sector, :exchange,
               :listing, :website, :logo, :syariah, 1, NOW(), NOW())
       ON DUPLICATE KEY UPDATE
           company_name       = VALUES(company_name),
           company_name_short = VALUES(company_name_short),
           sector_id          = VALUES(sector_id),
           exchange           = VALUES(exchange),
           listing_date       = COALESCE(VALUES(listing_date), listing_date),
           website            = COALESCE(VALUES(website), website),
           logo_url           = COALESCE(VALUES(logo_url), logo_url),
           is_syariah         = VALUES(is_syariah),
           updated_at         = NOW()';

$existingStmt = $pdo->prepare('SELECT id FROM stocks WHERE symbol = :s');
$upsertStmt   = $pdo->prepare($upsertSql);

foreach ($rows as $row) {
    $symbol = pick($row, ['code', 'symbol', 'ticker', 'stock_code']);
    $name   = pick($row, ['name', 'company_name', 'companyName', 'fullName']);

    if ($symbol === null || $name === null) {
        $skipped++;
        continue;
    }

    $symbol = strtoupper($symbol);

    $sectorId = $resolveSector(
        pick($row, ['sector', 'sector_name', 'sectorName']),
        pick($row, ['sub_sector', 'subSector', 'industry']),
        $sectorCache,
        $dryRun
    );

    $listing = pick($row, ['listing_date', 'listingDate', 'ipo_date']);
    if ($listing !== null && !preg_match('/^\d{4}-\d{2}-\d{2}/', $listing)) {
        $listing = null;   // format tak terduga lebih baik dikosongkan
    } elseif ($listing !== null) {
        $listing = substr($listing, 0, 10);
    }

    $syariahRaw = $row['is_syariah'] ?? $row['syariah'] ?? $row['isSyariah'] ?? false;
    $syariah    = in_array(
        is_string($syariahRaw) ? strtolower($syariahRaw) : $syariahRaw,
        [true, 1, '1', 'true', 'ya', 'yes'],
        true
    ) ? 1 : 0;

    $existingStmt->execute(['s' => $symbol]);
    $exists = $existingStmt->fetchColumn() !== false;

    if ($dryRun) {
        $exists ? $updated++ : $inserted++;
        continue;
    }

    $upsertStmt->execute([
        'symbol'   => $symbol,
        'name'     => mb_substr($name, 0, 150),
        'short'    => mb_substr(pick($row, ['short_name', 'shortName', 'company_name_short']) ?? $name, 0, 80),
        'sector'   => $sectorId,
        'exchange' => pick($row, ['exchange', 'market']) ?? 'IDX',
        'listing'  => $listing,
        'website'  => pick($row, ['website', 'url']),
        'logo'     => pick($row, ['logo', 'logo_url', 'logoUrl']),
        'syariah'  => $syariah,
    ]);

    $exists ? $updated++ : $inserted++;
}

if (!$dryRun) {
    $newSectors = (int) $pdo->query('SELECT COUNT(*) FROM sectors')->fetchColumn() - $sectorsBefore;
}

$job->line();
$job->step("Ditambahkan   : $inserted");
$job->step("Diperbarui    : $updated");
if ($skipped > 0) {
    $job->step("Dilewati      : $skipped (tanpa kode atau nama)");
}
if ($newSectors > 0) {
    $job->step("Sektor baru   : $newSectors");
}
$job->step('Permintaan ke vendor: ' . $client->requestCount());

$job->done($dryRun ? 'Uji coba selesai — tidak ada perubahan.' : 'Sinkronisasi master emiten selesai.');

if (!$dryRun && ($inserted + $updated) > 0) {
    $job->line();
    $job->line('Berikutnya: php jobs/sync_fundamental.php');
}
