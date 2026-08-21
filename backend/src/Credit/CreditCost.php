<?php

declare(strict_types=1);

namespace Aigen\Credit;

use Aigen\Core\Database;

/**
 * Biaya kredit per aksi, dibaca dari tabel credit_costs (NO HARDCODE).
 */
final class CreditCost
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
            ->query('SELECT action_key, action_name, cost, is_active FROM credit_costs')
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['action_key']] = [
                'name'      => $row['action_name'],
                'cost'      => (int) $row['cost'],
                'is_active' => (bool) (int) $row['is_active'],
            ];
        }

        return self::$cache = $result;
    }

    /**
     * Biaya sebuah aksi.
     *
     * Aksi yang tidak terdaftar di tabel mengembalikan null (bukan 0), supaya
     * pemanggil bisa membedakan "gratis" dari "belum dikonfigurasi".
     * Versi lama mengembalikan 0 untuk keduanya, sehingga aksi berbayar bisa
     * diam-diam menjadi gratis kalau seed-nya lupa diisi.
     */
    public static function for(string $actionKey): ?int
    {
        $entry = self::load()[$actionKey] ?? null;

        if ($entry === null) {
            return null;
        }
        if (!$entry['is_active']) {
            return 0;
        }

        return $entry['cost'];
    }

    public static function isFree(string $actionKey): bool
    {
        return self::for($actionKey) === 0;
    }

    /** @return array<string,array{name:string,cost:int,is_active:bool}> */
    public static function all(): array
    {
        return self::load();
    }
}
