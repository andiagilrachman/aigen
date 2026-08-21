<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Saklar fitur dari tabel feature_flags.
 *
 * Catatan audit: versi lama punya helper ini tapi TIDAK PERNAH dipanggil di
 * endpoint mana pun. Di versi ini, guard() dipasang di controller yang relevan.
 */
final class FeatureFlag
{
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = Database::connection()
            ->query('SELECT feature_key, is_active FROM feature_flags')
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['feature_key']] = (bool) (int) $row['is_active'];
        }

        return self::$cache = $result;
    }

    /** Flag yang belum terdaftar dianggap AKTIF, agar fitur baru tidak diam-diam mati. */
    public static function isActive(string $key, bool $default = true): bool
    {
        return self::load()[$key] ?? $default;
    }

    /** Hentikan request dengan 403 bila fitur dimatikan. */
    public static function guard(string $key, string $message = 'Fitur ini sedang tidak tersedia'): void
    {
        if (!self::isActive($key)) {
            Response::error($message, 403, 'feature_disabled');
        }
    }

    /** @return array<string,bool> */
    public static function all(): array
    {
        return self::load();
    }
}
