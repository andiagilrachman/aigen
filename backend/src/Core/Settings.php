<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Akses ke tabel system_settings — tulang punggung prinsip NO HARDCODE.
 *
 * Nilai dikonversi otomatis sesuai kolom value_type, jadi pemanggil menerima
 * bool/int/array yang benar, bukan string mentah.
 */
final class Settings
{
    /** @var array<string,mixed>|null Cache per-request. */
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
            ->query('SELECT setting_key, setting_value, value_type FROM system_settings')
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = self::cast($row['setting_value'], $row['value_type']);
        }

        return self::$cache = $result;
    }

    private static function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'number'  => str_contains($value, '.') ? (float) $value : (int) $value,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'    => json_decode($value, true) ?? [],
            default   => $value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::load()[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        return is_bool($value) ? $value : $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    /** @return array<string,mixed> Semua setting dalam satu grup. */
    public static function group(string $group): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT setting_key, setting_value, value_type
               FROM system_settings
              WHERE setting_group = :g
              ORDER BY setting_key'
        );
        $stmt->execute(['g' => $group]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['setting_key']] = self::cast($row['setting_value'], $row['value_type']);
        }
        return $result;
    }

    public static function set(string $key, mixed $value): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT value_type FROM system_settings WHERE setting_key = :k'
        );
        $stmt->execute(['k' => $key]);
        $type = $stmt->fetchColumn();

        if ($type === false) {
            throw new \RuntimeException("Setting '$key' tidak dikenal. Tambahkan lewat migrasi/seed, bukan runtime.");
        }

        $encoded = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json'    => json_encode($value, JSON_UNESCAPED_UNICODE),
            default   => (string) $value,
        };

        $update = Database::connection()->prepare(
            'UPDATE system_settings SET setting_value = :v WHERE setting_key = :k'
        );
        $update->execute(['v' => $encoded, 'k' => $key]);

        self::flush();
    }
}
