<?php

declare(strict_types=1);

namespace Aigen\Controllers;

use Aigen\Auth\Auth;
use Aigen\Core\App;
use Aigen\Core\FeatureFlag;
use Aigen\Core\Request;
use Aigen\Core\Response;
use Aigen\Core\Settings;
use Aigen\Core\Validator;
use Aigen\Credit\CreditManager;
use Aigen\Support\ActivityLog;
use RuntimeException;

final class AuthController
{
    public function register(Request $request): void
    {
        if (!Settings::bool('allow_registration', true)) {
            Response::error('Pendaftaran sedang ditutup', 403, 'registration_closed');
        }
        FeatureFlag::guard('registration', 'Pendaftaran sedang tidak tersedia');

        $data = Validator::make($request->all())
            ->validate([
                'full_name'             => 'required|min:3|max:150',
                'email'                 => 'required|email|max:150',
                'password'              => 'required|min:8|max:100',
                'password_confirmation' => 'required|confirmed:password',
            ], [
                'full_name'             => 'Nama lengkap',
                'email'                 => 'Email',
                'password'              => 'Password',
                'password_confirmation' => 'Konfirmasi password',
            ])
            ->stopIfFails();

        // Rate limit per IP: mencegah pembuatan akun massal.
        $limiter = App::rateLimiter();
        $limiterKey = 'register:' . $request->ip();
        $limiter->guard($limiterKey);

        try {
            Auth::register($data['full_name'], $data['email'], $data['password']);
        } catch (RuntimeException $e) {
            $limiter->hit($limiterKey);
            Response::error($e->getMessage(), 422, 'registration_failed', ['email' => $e->getMessage()]);
            return;
        }

        // Langsung login supaya pengguna tidak perlu mengetik ulang kredensial.
        $user = Auth::login($data['email'], $data['password']);
        $limiter->clear($limiterKey);

        ActivityLog::record($user['id'], 'register', 'Pendaftaran akun baru', $request->ip());

        Response::success([
            'user'    => $user,
            'wallet'  => ['balance' => CreditManager::balance((int) $user['id'])],
            'message' => 'Pendaftaran berhasil. Selamat datang di AIGen.',
        ], [], 201);
    }

    public function login(Request $request): void
    {
        $data = Validator::make($request->all())
            ->validate([
                'email'    => 'required|email',
                'password' => 'required',
            ], [
                'email'    => 'Email',
                'password' => 'Password',
            ])
            ->stopIfFails();

        // Kunci gabungan email+IP: satu penyerang tidak bisa mengunci akun
        // orang lain hanya dengan menebak dari IP berbeda-beda.
        $limiter = App::rateLimiter();
        $limiterKey = 'login:' . strtolower($data['email']) . '|' . $request->ip();
        $limiter->guard($limiterKey);

        try {
            $user = Auth::login($data['email'], $data['password']);
        } catch (RuntimeException $e) {
            $limiter->hit($limiterKey);
            Response::error($e->getMessage(), 401, 'invalid_credentials');
            return;
        }

        $limiter->clear($limiterKey);
        ActivityLog::record($user['id'], 'login', 'Login berhasil', $request->ip());

        Response::success([
            'user'   => $user,
            'wallet' => ['balance' => CreditManager::balance((int) $user['id'])],
        ]);
    }

    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user !== null) {
            ActivityLog::record((int) $user['id'], 'logout', 'Logout', $request->ip());
        }

        Auth::logout();

        Response::success(['message' => 'Anda telah keluar']);
    }

    /**
     * Sesi saat ini. Dipakai frontend ketika halaman dimuat ulang untuk
     * memulihkan status login tanpa menyimpan token di localStorage.
     */
    public function me(Request $request): void
    {
        $user = $request->user();
        $userId = (int) $user['id'];

        Response::success([
            'user'   => $user,
            'wallet' => ['balance' => CreditManager::balance($userId)],
        ]);
    }
}
