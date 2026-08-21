<?php

declare(strict_types=1);

namespace Aigen\Tests;

use Aigen\Core\Database;
use Aigen\Core\FeatureFlag;
use Aigen\Core\HaltException;
use Aigen\Core\Request;
use Aigen\Core\Response;
use Aigen\Core\Router;
use Aigen\Core\Settings;
use Aigen\Credit\CreditCost;
use PDO;
use Throwable;

/**
 * Kerangka test minimal tanpa PHPUnit.
 *
 * Composer belum dipakai di proyek ini, jadi runner ditulis sendiri agar test
 * tetap bisa dijalankan hanya dengan `php tests/run.php`.
 */
final class TestCase
{
    private static int $passed = 0;
    private static int $failed = 0;
    /** @var array<int,string> */
    private static array $failures = [];
    private static string $currentGroup = '';

    public static PDO $pdo;

    public static function group(string $name): void
    {
        self::$currentGroup = $name;
        echo "\n\033[1m$name\033[0m\n";
    }

    public static function test(string $description, callable $body): void
    {
        try {
            $body();
            self::$passed++;
            echo "  \033[32m✓\033[0m $description\n";
        } catch (Throwable $e) {
            self::$failed++;
            $label = self::$currentGroup . ' › ' . $description;
            self::$failures[] = $label . "\n      " . $e->getMessage()
                . "\n      " . $e->getFile() . ':' . $e->getLine();
            echo "  \033[31m✗\033[0m $description\n      \033[31m" . $e->getMessage() . "\033[0m\n";
        }
    }

    public static function assertTrue(bool $condition, string $message = 'Diharapkan true'): void
    {
        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed(sprintf(
                '%sDiharapkan %s, diterima %s',
                $message !== '' ? $message . ' — ' : '',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            throw new AssertionFailed(sprintf(
                '%sDiharapkan %s, diterima %s',
                $message !== '' ? $message . ' — ' : '',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    public static function assertCount(int $expected, array $actual, string $message = ''): void
    {
        if (count($actual) !== $expected) {
            throw new AssertionFailed(sprintf(
                '%sDiharapkan %d elemen, diterima %d',
                $message !== '' ? $message . ' — ' : '',
                $expected,
                count($actual)
            ));
        }
    }

    public static function assertThrows(string $exceptionClass, callable $body, string $message = ''): Throwable
    {
        try {
            $body();
        } catch (Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return $e;
            }
            throw new AssertionFailed(sprintf(
                '%sDiharapkan %s, dilempar %s (%s)',
                $message !== '' ? $message . ' — ' : '',
                $exceptionClass,
                $e::class,
                $e->getMessage()
            ));
        }

        throw new AssertionFailed(
            ($message !== '' ? $message . ' — ' : '') . "Diharapkan $exceptionClass dilempar, tapi tidak ada"
        );
    }

    /**
     * Jalankan sebuah rute dan kembalikan payload responsnya.
     *
     * Response dalam mode test melempar HaltException alih-alih exit(), jadi
     * pemanggilan bisa ditangkap dan payloadnya diperiksa.
     *
     * @return array{status:int,body:array}
     */
    public static function call(
        Router $router,
        string $method,
        string $path,
        array $body = [],
        array $query = []
    ): array {
        $request = new Request($method, $path, $query, $body);

        try {
            $router->dispatch($request);
        } catch (HaltException) {
            // normal
        }

        return ['status' => Response::lastStatus(), 'body' => Response::lastPayload()];
    }

    /** Kosongkan seluruh cache statis antar-test agar tidak saling bocor. */
    public static function flushCaches(): void
    {
        Settings::flush();
        FeatureFlag::flush();
        CreditCost::flush();
    }

    public static function summary(): int
    {
        $total = self::$passed + self::$failed;

        echo "\n" . str_repeat('─', 60) . "\n";

        if (self::$failed === 0) {
            echo "\033[32m✓ Semua test lolos\033[0m — $total assertion\n";
            return 0;
        }

        echo "\033[31m✗ " . self::$failed . " dari $total test gagal\033[0m\n\n";
        foreach (self::$failures as $failure) {
            echo "  • $failure\n\n";
        }

        return 1;
    }
}

final class AssertionFailed extends \RuntimeException
{
}
