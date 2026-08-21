<?php

declare(strict_types=1);

namespace Aigen\Core;

use Aigen\Auth\Auth;
use Aigen\Auth\RateLimiter;

/**
 * Bootstrap aplikasi.
 *
 * Semua penyiapan global terkumpul di satu tempat. Di versi lama tiap file
 * endpoint melakukan require bootstrap + header CORS sendiri-sendiri, sehingga
 * mudah ada file yang lupa satu langkah.
 */
final class App
{
    private static array $config = [];
    private static bool $booted = false;

    public static function boot(?array $config = null): void
    {
        if (self::$booted) {
            return;
        }

        self::$config = $config ?? require dirname(__DIR__, 2) . '/config/config.php';

        date_default_timezone_set('Asia/Jakarta');

        Database::configure(self::$config['database']);
        Auth::configure(self::$config['session']);

        self::configureErrorHandling();

        self::$booted = true;
    }

    public static function config(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function isDebug(): bool
    {
        return (bool) self::config('app.debug', false);
    }

    public static function storagePath(string $sub = ''): string
    {
        $base = dirname(__DIR__, 2) . '/storage';
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }

    public static function rateLimiter(): RateLimiter
    {
        return new RateLimiter(
            self::storagePath('cache/ratelimit'),
            (int) self::config('security.login_max_attempts', 5),
            (int) self::config('security.login_decay_minutes', 15)
        );
    }

    /**
     * Kirim header CORS.
     *
     * Origin dicocokkan dengan daftar izin, lalu dipantulkan kembali satu per
     * satu. Tidak memakai "*" karena kredensial (cookie sesi) harus ikut
     * terkirim, dan browser menolak kombinasi "*" + credentials.
     */
    public static function applyCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = (array) self::config('app.cors_origins', []);

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Job-Token');
        header('Access-Control-Max-Age: 86400');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Error dan exception yang tidak tertangkap tetap dibalas JSON.
     *
     * Tanpa ini, fatal error mengirim HTML ke frontend yang menunggu JSON, dan
     * pengguna hanya melihat "Unexpected token < in JSON".
     */
    private static function configureErrorHandling(): void
    {
        $debug = self::isDebug();

        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        set_exception_handler(static function (\Throwable $e) use ($debug): void {
            // HaltException adalah alur normal Response di mode test.
            if ($e instanceof HaltException) {
                return;
            }

            Logger::exception($e);

            $message = $debug
                ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                : 'Terjadi kesalahan pada server. Silakan coba lagi.';

            Response::error($message, 500, 'server_error');
        });

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }
}
