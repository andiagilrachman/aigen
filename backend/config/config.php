<?php
/**
 * Konfigurasi aplikasi.
 *
 * SATU-SATUNYA tempat hardcode yang diizinkan blueprint: kredensial koneksi
 * database — karena dibutuhkan sebelum koneksi ke DB terbuka.
 *
 * Semua nilai dibaca dari environment (.env) supaya kredensial tidak pernah
 * masuk ke Git. Lihat .env.example.
 */

declare(strict_types=1);

/** Baca file .env sederhana (tanpa dependency Composer). */
function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Buang tanda kutip pembungkus bila ada
        if (strlen($value) >= 2) {
            $first = $value[0];
            if (($first === '"' || $first === "'") && str_ends_with($value, $first)) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

/** Ambil nilai environment dengan fallback dan konversi tipe otomatis. */
function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return match (strtolower($value)) {
        'true'  => true,
        'false' => false,
        'null'  => null,
        default => $value,
    };
}

loadEnvFile(dirname(__DIR__) . '/.env');

return [
    'app' => [
        'env'     => env('APP_ENV', 'production'),
        'debug'   => (bool) env('APP_DEBUG', false),
        'url'     => env('APP_URL', 'http://localhost'),
        // Origin frontend yang diizinkan CORS, dipisah koma.
        // Bukan hardcode nilai tunggal seperti versi lama.
        'cors_origins' => array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ORIGINS', 'http://localhost:5173'))
        )),
    ],

    'database' => [
        'driver'   => env('DB_DRIVER', 'mysql'),
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => (int) env('DB_PORT', 3306),
        'name'     => env('DB_NAME', 'aigen_db'),
        'user'     => env('DB_USER', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
        // Khusus driver sqlite (dipakai test suite)
        'path'     => env('DB_PATH', ''),
    ],

    'session' => [
        'name'          => env('SESSION_NAME', 'aigen_session'),
        'lifetime'      => (int) env('SESSION_LIFETIME', 0),
        // Wajib None+Secure kalau frontend beda domain dengan backend.
        'same_site'     => env('SESSION_SAMESITE', 'Lax'),
        'secure'        => (bool) env('SESSION_SECURE', false),
    ],

    'security' => [
        // Kunci enkripsi api_key vendor. WAJIB diisi di produksi.
        'app_key'            => env('APP_KEY', ''),
        // Token untuk memicu job lewat HTTP (opsional; kosong = CLI-only).
        'job_token'          => env('JOB_TOKEN', ''),
        'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_minutes'=> (int) env('LOGIN_DECAY_MINUTES', 15),
    ],
];
