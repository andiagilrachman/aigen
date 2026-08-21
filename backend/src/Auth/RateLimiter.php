<?php

declare(strict_types=1);

namespace Aigen\Auth;

use Aigen\Core\Response;

/**
 * Pembatas percobaan berbasis file, untuk menahan brute-force login.
 *
 * Sengaja memakai file (bukan tabel) agar tetap bekerja walau database sedang
 * bermasalah, dan tidak menambah beban tulis ke DB pada serangan volume tinggi.
 */
final class RateLimiter
{
    public function __construct(
        private readonly string $storagePath,
        private readonly int $maxAttempts = 5,
        private readonly int $decayMinutes = 15
    ) {
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0775, true);
        }
    }

    private function file(string $key): string
    {
        return $this->storagePath . '/rl_' . sha1($key) . '.json';
    }

    /** @return array{attempts:int,expires_at:int} */
    private function read(string $key): array
    {
        $file = $this->file($key);

        if (!is_readable($file)) {
            return ['attempts' => 0, 'expires_at' => 0];
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || ($data['expires_at'] ?? 0) < time()) {
            return ['attempts' => 0, 'expires_at' => 0];
        }

        return [
            'attempts'   => (int) ($data['attempts'] ?? 0),
            'expires_at' => (int) ($data['expires_at'] ?? 0),
        ];
    }

    public function tooManyAttempts(string $key): bool
    {
        return $this->read($key)['attempts'] >= $this->maxAttempts;
    }

    public function hit(string $key): void
    {
        $current = $this->read($key);

        $expiresAt = $current['expires_at'] > time()
            ? $current['expires_at']
            : time() + ($this->decayMinutes * 60);

        @file_put_contents(
            $this->file($key),
            json_encode(['attempts' => $current['attempts'] + 1, 'expires_at' => $expiresAt]),
            LOCK_EX
        );
    }

    public function clear(string $key): void
    {
        @unlink($this->file($key));
    }

    public function secondsRemaining(string $key): int
    {
        return max(0, $this->read($key)['expires_at'] - time());
    }

    /** Hentikan request dengan 429 bila jatah percobaan habis. */
    public function guard(string $key): void
    {
        if ($this->tooManyAttempts($key)) {
            $minutes = (int) ceil($this->secondsRemaining($key) / 60);
            Response::error(
                "Terlalu banyak percobaan. Coba lagi dalam $minutes menit.",
                429,
                'too_many_attempts'
            );
        }
    }
}
