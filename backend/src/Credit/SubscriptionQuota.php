<?php

declare(strict_types=1);

namespace Aigen\Credit;

use Aigen\Core\Database;
use Aigen\Core\Settings;

/**
 * Kuota harian dari tier langganan aktif (atau tier default untuk user tanpa
 * langganan). Memakai tabel trial_usage sebagai penghitung pemakaian harian.
 *
 * Aturan:
 *   - screening_quota NULL  => unlimited, selalu lolos tanpa potong kredit
 *   - screening_quota angka => dibatasi per hari
 *   - kuota habis           => kembalikan null, pemanggil beralih ke kredit
 */
final class SubscriptionQuota
{
    /** Hanya aksi ini yang dihitung terhadap kuota tier. */
    private const QUOTA_ACTIONS = ['run_screening'];

    /**
     * Coba pakai satu jatah kuota.
     *
     * @return int|null Sisa kuota (PHP_INT_MAX bila unlimited), atau null bila
     *                  kuota tidak berlaku/habis.
     */
    public static function consume(int $userId, string $actionKey): ?int
    {
        if (!in_array($actionKey, self::QUOTA_ACTIONS, true)) {
            return null;
        }

        $tier = self::activeTier($userId);
        if ($tier === null) {
            return null;
        }

        // NULL = unlimited
        if ($tier['screening_quota'] === null) {
            self::increment($userId);
            return PHP_INT_MAX;
        }

        $quota = (int) $tier['screening_quota'];
        if ($quota <= 0) {
            return null;
        }

        $used = self::usedToday($userId);
        if ($used >= $quota) {
            return null;
        }

        self::increment($userId);

        return $quota - $used - 1;
    }

    /** Kembalikan satu jatah kuota saat proses gagal. */
    public static function giveBack(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE trial_usage
                SET usage_count = usage_count - 1
              WHERE user_id = :id AND usage_date = ' . self::today() . '
                AND usage_count > 0'
        );
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Tier yang berlaku untuk user: langganan aktif, atau tier default
     * (tier_key dari setting default_tier_key) bila tidak berlangganan.
     *
     * @return array{id:int,tier_key:string,name:string,screening_quota:?int}|null
     */
    public static function activeTier(int $userId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT t.id, t.tier_key, t.name, t.screening_quota
               FROM user_subscriptions s
               JOIN subscription_tiers t ON t.id = s.tier_id
              WHERE s.user_id = :id
                AND s.status = \'active\'
                AND s.ends_at > ' . self::now() . '
              ORDER BY s.ends_at DESC
              LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        if ($row !== false) {
            return self::normalize($row);
        }

        // Tanpa langganan aktif -> pakai tier default
        $defaultKey = Settings::string('default_tier_key', 'free');
        if ($defaultKey === '') {
            return null;
        }

        $fallback = $pdo->prepare(
            'SELECT id, tier_key, name, screening_quota
               FROM subscription_tiers
              WHERE tier_key = :k AND is_active = 1
              LIMIT 1'
        );
        $fallback->execute(['k' => $defaultKey]);
        $row = $fallback->fetch();

        return $row === false ? null : self::normalize($row);
    }

    private static function normalize(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'tier_key'        => (string) $row['tier_key'],
            'name'            => (string) $row['name'],
            'screening_quota' => $row['screening_quota'] === null
                ? null
                : (int) $row['screening_quota'],
        ];
    }

    public static function usedToday(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT usage_count FROM trial_usage
              WHERE user_id = :id AND usage_date = ' . self::today()
        );
        $stmt->execute(['id' => $userId]);
        $count = $stmt->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    private static function increment(int $userId): void
    {
        $pdo = Database::connection();

        // trial_ends_at NOT NULL di skema -> isi dengan akhir hari ini.
        $endOfDay = Database::driver() === 'sqlite'
            ? "datetime('now', '+1 day')"
            : 'DATE_ADD(NOW(), INTERVAL 1 DAY)';

        $sql = Database::driver() === 'sqlite'
            ? 'INSERT INTO trial_usage (user_id, usage_date, usage_count, trial_ends_at)
               VALUES (:id, ' . self::today() . ', 1, ' . $endOfDay . ')
               ON CONFLICT(user_id, usage_date)
               DO UPDATE SET usage_count = usage_count + 1'
            : 'INSERT INTO trial_usage (user_id, usage_date, usage_count, trial_ends_at)
               VALUES (:id, ' . self::today() . ', 1, ' . $endOfDay . ')
               ON DUPLICATE KEY UPDATE usage_count = usage_count + 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $userId]);
    }

    /** Ringkasan kuota untuk ditampilkan di UI. */
    public static function summary(int $userId): array
    {
        $tier = self::activeTier($userId);

        if ($tier === null) {
            return [
                'tier_key'  => null,
                'tier_name' => null,
                'quota'     => 0,
                'used'      => 0,
                'remaining' => 0,
                'unlimited' => false,
            ];
        }

        $used = self::usedToday($userId);
        $unlimited = $tier['screening_quota'] === null;

        return [
            'tier_key'  => $tier['tier_key'],
            'tier_name' => $tier['name'],
            'quota'     => $tier['screening_quota'],
            'used'      => $used,
            'remaining' => $unlimited ? null : max(0, (int) $tier['screening_quota'] - $used),
            'unlimited' => $unlimited,
        ];
    }

    private static function today(): string
    {
        return Database::driver() === 'sqlite' ? "date('now')" : 'CURDATE()';
    }

    private static function now(): string
    {
        return Database::driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
