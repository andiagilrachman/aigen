<?php

declare(strict_types=1);

namespace Aigen\Core;

use Aigen\Auth\Auth;

/**
 * Kumpulan middleware siap pakai untuk Router::group().
 *
 * Guard dipasang di definisi rute, bukan di dalam tiap handler. Dengan begitu
 * menambah endpoint admin baru otomatis terlindungi — kelemahan versi lama
 * adalah guard harus diingat manual per file, dan memang terlewat.
 */
final class Middleware
{
    /** Wajib login. */
    public static function auth(): callable
    {
        return static function (Request $request): void {
            $user = Auth::requireLogin();
            $request->setUser($user);
        };
    }

    /** Wajib login dengan salah satu peran tertentu. */
    public static function role(string ...$roles): callable
    {
        return static function (Request $request) use ($roles): void {
            $user = Auth::requireRole(...$roles);
            $request->setUser($user);
        };
    }

    public static function admin(): callable
    {
        return self::role('super_admin', 'admin');
    }

    /** Endpoint hanya aktif bila feature flag menyala. */
    public static function feature(string $key): callable
    {
        return static function (Request $request) use ($key): void {
            FeatureFlag::guard($key);
        };
    }

    /**
     * Lindungi endpoint pemicu job dengan token statis.
     * Kalau JOB_TOKEN kosong, endpoint ditolak sepenuhnya (job jadi CLI-only),
     * bukan malah terbuka bebas.
     */
    public static function jobToken(): callable
    {
        return static function (Request $request): void {
            $expected = (string) App::config('security.job_token', '');

            if ($expected === '') {
                Response::error(
                    'Pemicu job lewat HTTP dinonaktifkan. Jalankan lewat CLI.',
                    403,
                    'job_http_disabled'
                );
            }

            $given = $request->header('X-Job-Token') ?? '';

            if (!hash_equals($expected, $given)) {
                Response::error('Token job tidak valid', 403, 'invalid_job_token');
            }
        };
    }
}
