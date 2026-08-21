<?php

declare(strict_types=1);

namespace Aigen\Support;

use Aigen\Core\Database;

/**
 * Skor komposit fundamental 0–100 berdasarkan tabel formula_config.
 *
 * Bobot dan ambang batas tidak pernah ditulis di kode: mengubah pandangan soal
 * "ROE berapa yang bagus" cukup lewat satu UPDATE, tanpa deploy.
 *
 * Arah metrik ikut urutan ambang, bukan bendera terpisah. Untuk ROE
 * threshold_good (20) > threshold_bad (5); untuk DER justru good (0.5) <
 * bad (2.0). Rumus interpolasi yang sama menangani keduanya, jadi tidak ada
 * kolom higher_is_better yang bisa lupa diisi dan diam-diam membalik makna.
 */
final class FundamentalScore
{
    /**
     * Peta formula_key -> kolom di indicator_snapshot_fundamental.
     *
     * Pemetaan ini WAJIB eksplisit. Versi lama menyerahkan pencocokan pada
     * kesamaan nama, padahal seed memakai 'npm' dan 'cr' sedangkan kolomnya
     * bernama net_profit_margin dan current_ratio. Akibatnya dua formula itu
     * tidak pernah cocok, diam-diam dilewati, dan skor terbentuk hanya dari
     * lima metrik — tanpa satu pun pesan galat.
     */
    private const KEY_TO_COLUMN = [
        'roe'            => 'roe',
        'roa'            => 'roa',
        'der'            => 'der',
        'per'            => 'per',
        'pbv'            => 'pbv',
        'eps'            => 'eps',
        'bvps'           => 'bvps',
        'npm'            => 'net_profit_margin',
        'gpm'            => 'gross_profit_margin',
        'cr'             => 'current_ratio',
        'qr'             => 'quick_ratio',
        'dividend_yield' => 'dividend_yield',
        'revenue_growth' => 'revenue_growth_yoy',
        'income_growth'  => 'net_income_growth_yoy',
        'altman_z'       => 'altman_z_score',
        'piotroski_f'    => 'piotroski_f_score',
    ];

    /** @var array<int,array<string,mixed>>|null */
    private static ?array $configCache = null;

    public static function flush(): void
    {
        self::$configCache = null;
    }

    /** @return array<int,array<string,mixed>> */
    private static function config(): array
    {
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        $rows = Database::connection()->query(
            'SELECT formula_key, weight, threshold_good, threshold_bad
               FROM formula_config
              WHERE is_active = 1'
        )->fetchAll();

        return self::$configCache = $rows;
    }

    /**
     * Formula aktif apa adanya — dipakai job untuk melaporkan dasar penilaian
     * yang sedang berlaku sebelum menulis apa pun.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function activeFormulas(): array
    {
        return self::config();
    }

    /**
     * Nama formula_key aktif yang tidak dikenali pemetaan.
     *
     * Dipakai job untuk memperingatkan operator kalau seseorang menambah
     * formula lewat SQL tapi lupa menambahkan kolomnya di sini — daripada
     * formula itu diabaikan tanpa suara.
     *
     * @return array<int,string>
     */
    public static function unmappedKeys(): array
    {
        $unknown = [];
        foreach (self::config() as $cfg) {
            $key = (string) $cfg['formula_key'];
            if (!isset(self::KEY_TO_COLUMN[$key])) {
                $unknown[] = $key;
            }
        }
        return $unknown;
    }

    /**
     * Hitung skor dari satu baris snapshot.
     *
     * @param array<string,mixed> $snapshot Baris indicator_snapshot_fundamental
     *                                      (boleh sebagian kolom saja).
     * @return array{score: float|null, used: int, total: int}
     *         `used` = jumlah metrik yang benar-benar berkontribusi. Berguna
     *         untuk menilai seberapa layak dipercaya skornya.
     */
    public static function compute(array $snapshot): array
    {
        $configs = self::config();
        if ($configs === []) {
            return ['score' => null, 'used' => 0, 'total' => 0];
        }

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $used        = 0;

        foreach ($configs as $cfg) {
            $key    = (string) $cfg['formula_key'];
            $column = self::KEY_TO_COLUMN[$key] ?? null;

            if ($column === null) {
                continue;
            }

            $value = $snapshot[$column] ?? null;
            $good  = $cfg['threshold_good'];
            $bad   = $cfg['threshold_bad'];

            if ($value === null || $good === null || $bad === null || !is_numeric($value)) {
                continue;
            }

            $normalized = self::normalize((float) $value, (float) $good, (float) $bad);
            $weight     = (float) $cfg['weight'];

            $weightedSum += $normalized * $weight;
            $weightTotal += $weight;
            $used++;
        }

        return [
            'score' => $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null,
            'used'  => $used,
            'total' => count($configs),
        ];
    }

    /**
     * Petakan satu nilai ke skala 0–100 dengan interpolasi linier.
     *
     * Nilai di titik "bad" jadi 0, di titik "good" jadi 100, di luar rentang
     * dijepit. Arah metrik terbaca sendiri dari posisi good relatif ke bad.
     */
    private static function normalize(float $value, float $good, float $bad): float
    {
        $range = $good - $bad;

        // Ambang yang identik tidak bisa membedakan apa pun. Beri nilai tengah
        // daripada membagi nol.
        if (abs($range) < 1e-9) {
            return 50.0;
        }

        $normalized = (($value - $bad) / $range) * 100;

        return max(0.0, min(100.0, $normalized));
    }

    /**
     * Label ringkas untuk pengguna, ambangnya dari system_settings.
     *
     * Sengaja mengembalikan null saat skor null: emiten tanpa data cukup tidak
     * boleh terlihat seolah sudah dinilai.
     */
    public static function rating(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 80 => 'Sangat Baik',
            $score >= 65 => 'Baik',
            $score >= 50 => 'Cukup',
            $score >= 35 => 'Lemah',
            default      => 'Sangat Lemah',
        };
    }
}
