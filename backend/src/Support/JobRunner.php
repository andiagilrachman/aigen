<?php

declare(strict_types=1);

namespace Aigen\Support;

use Aigen\Core\App;
use Aigen\Core\Logger;

/**
 * Perkakas bersama untuk job CLI: argumen, keluaran, dan batas waktu aman.
 *
 * Job sinkronisasi memanggil vendor ratusan kali, jadi tidak mungkin selesai
 * dalam satu permintaan HTTP. Kelas ini menyediakan pola batch-resumable yang
 * sama untuk semua job: berhenti sebelum kehabisan waktu, lalu cetak perintah
 * persis untuk melanjutkan dari titik berhenti.
 */
final class JobRunner
{
    private float $startedAt;

    /** @var array<string,string> */
    private array $args;

    private int $maxRunSeconds;

    public function __construct(
        private readonly string $name,
        array $argv = [],
        int $maxRunSeconds = 0,
    ) {
        $this->startedAt = microtime(true);
        $this->args      = self::parseArgs($argv);

        // `--max-seconds` selalu boleh menimpa batas bawaan, termasuk dengan 0
        // untuk mematikan batas sama sekali saat dijalankan dari terminal.
        $override = $this->args['max-seconds'] ?? null;
        $this->maxRunSeconds = $override !== null && is_numeric($override)
            ? max(0, (int) $override)
            : $maxRunSeconds;
    }

    /**
     * Terima argumen gaya `--offset=100` maupun `?offset=100` (saat dipicu
     * lewat HTTP), sehingga job bisa dijalankan dari terminal atau browser.
     *
     * @param  array<int,string> $argv
     * @return array<string,string>
     */
    private static function parseArgs(array $argv): array
    {
        $result = [];

        foreach (array_slice($argv, 1) as $arg) {
            if (!str_starts_with($arg, '--')) {
                continue;
            }
            $pair = substr($arg, 2);
            if (str_contains($pair, '=')) {
                [$k, $v] = explode('=', $pair, 2);
                $result[$k] = $v;
            } else {
                $result[$pair] = '1';
            }
        }

        // Saat dipicu lewat HTTP, query string ikut dibaca.
        foreach ($_GET as $k => $v) {
            if (is_string($v)) {
                $result[(string) $k] = $v;
            }
        }

        return $result;
    }

    public function arg(string $key, ?string $default = null): ?string
    {
        return $this->args[$key] ?? $default;
    }

    public function intArg(string $key, int $default): int
    {
        $value = $this->args[$key] ?? null;
        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }

    public function boolArg(string $key): bool
    {
        $value = $this->args[$key] ?? null;
        return $value !== null && !in_array(strtolower($value), ['0', 'false', 'no'], true);
    }

    public function elapsed(): float
    {
        return microtime(true) - $this->startedAt;
    }

    /**
     * Sudah waktunya berhenti supaya tidak kena batas waktu eksekusi?
     *
     * Nilai 0 berarti tanpa batas — pilihan wajar untuk CLI, berbahaya untuk
     * HTTP karena Apache akan memutus di tengah jalan dan meninggalkan data
     * separuh jadi.
     */
    public function shouldStop(): bool
    {
        return $this->maxRunSeconds > 0 && $this->elapsed() >= $this->maxRunSeconds;
    }

    // ------------------------------------------------------------- keluaran

    public function line(string $message = ''): void
    {
        echo $message, "\n";
        if (function_exists('flush')) {
            @flush();
        }
    }

    public function step(string $message): void
    {
        $this->line(sprintf('  %s', $message));
    }

    public function header(string $message): void
    {
        $this->line();
        $this->line($message);
        $this->line(str_repeat('─', min(60, max(20, mb_strlen($message)))));
    }

    public function warn(string $message): void
    {
        $this->line('  ! ' . $message);
        Logger::warning("[$this->name] $message");
    }

    public function fail(string $message): void
    {
        $this->line('  x ' . $message);
        Logger::error("[$this->name] $message");
    }

    public function done(string $message): void
    {
        $this->line();
        $this->line(sprintf('%s (%.1f detik)', $message, $this->elapsed()));
    }

    /**
     * Cetak cara melanjutkan dari titik berhenti.
     *
     * Perintahnya ditulis lengkap agar operator bisa menyalin-tempel; menebak
     * offset sendiri adalah cara termudah melewatkan sebagian emiten.
     *
     * @param array<string,int|string> $params
     */
    public function resumeHint(string $script, array $params): void
    {
        $cli = 'php jobs/' . $script;
        foreach ($params as $k => $v) {
            $cli .= sprintf(' --%s=%s', $k, $v);
        }

        $this->line();
        $this->line('Lanjutkan dengan:');
        $this->line('  ' . $cli);

        if (PHP_SAPI !== 'cli') {
            $url = rtrim((string) App::config('app.url', ''), '/')
                . '/../jobs/' . $script . '?' . http_build_query($params);
            $this->line('  atau buka: ' . $url);
        }
    }

    /**
     * Pastikan job tidak dijalankan sembarang orang lewat HTTP.
     *
     * Dari CLI selalu boleh. Lewat HTTP hanya bila JOB_TOKEN diisi dan cocok —
     * kalau tidak, siapa pun yang menebak URL-nya bisa menghabiskan kuota
     * vendor Anda.
     */
    public static function guard(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');

        $expected = (string) App::config('security.job_token', '');

        if ($expected === '') {
            http_response_code(403);
            echo "Job hanya bisa dijalankan dari CLI.\n";
            echo "Isi JOB_TOKEN di .env kalau ingin memicunya lewat HTTP.\n";
            exit;
        }

        $given = $_GET['token'] ?? $_SERVER['HTTP_X_JOB_TOKEN'] ?? '';

        if (!is_string($given) || !hash_equals($expected, $given)) {
            http_response_code(403);
            echo "Token job tidak sah.\n";
            exit;
        }
    }
}
