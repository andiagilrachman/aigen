<?php

declare(strict_types=1);

namespace Aigen\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Pemegang koneksi PDO tunggal (singleton per proses).
 *
 * Mendukung driver mysql (produksi) dan sqlite (test suite), sehingga logika
 * bisnis bisa diuji tanpa server MySQL.
 */
final class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$connection = null;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::$config;
        if ($config === []) {
            throw new RuntimeException('Database belum dikonfigurasi. Panggil Database::configure() lebih dulu.');
        }

        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $path = $config['path'] ?: ':memory:';
            $dsn = 'sqlite:' . $path;
            $user = null;
            $password = null;
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['name'],
                $config['charset'] ?? 'utf8mb4'
            );
            $user = $config['user'];
            $password = $config['password'];
        }

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Koneksi database gagal: ' . $e->getMessage(), 0, $e);
        }

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        self::$connection = $pdo;

        return $pdo;
    }

    /** Dipakai test suite untuk menyuntikkan koneksi in-memory. */
    public static function setConnection(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    public static function driver(): string
    {
        return self::$config['driver'] ?? 'mysql';
    }

    /**
     * Jalankan callback di dalam transaksi. Aman terhadap transaksi bersarang:
     * kalau sudah ada transaksi berjalan, callback ikut transaksi luar.
     *
     * Ini memperbaiki bug lama di Auth::register() yang harus memberi kredit
     * trial DI LUAR transaksi karena takut nested transaction.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
