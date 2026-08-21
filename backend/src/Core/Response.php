<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Penulis respons JSON dengan bentuk yang konsisten.
 *
 * Semua respons memakai amplop yang sama supaya frontend tidak perlu menebak:
 *   sukses -> { "success": true,  "data": ..., "meta": ... }
 *   gagal  -> { "success": false, "error": { "message": ..., "code": ..., "fields": ... } }
 */
final class Response
{
    private static bool $testMode = false;
    private static array $lastPayload = [];
    private static int $lastStatus = 200;

    /** Mode test: jangan panggil exit(), simpan payload untuk diperiksa. */
    public static function enableTestMode(bool $enabled = true): void
    {
        self::$testMode = $enabled;
    }

    public static function lastPayload(): array
    {
        return self::$lastPayload;
    }

    public static function lastStatus(): int
    {
        return self::$lastStatus;
    }

    public static function json(array $payload, int $status = 200): void
    {
        self::$lastPayload = $payload;
        self::$lastStatus = $status;

        if (self::$testMode) {
            throw new HaltException($status);
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, array $meta = [], int $status = 200): void
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        self::json($payload, $status);
    }

    /**
     * @param array<string,string> $fields Error per-field untuk form frontend.
     */
    public static function error(
        string $message,
        int $status = 400,
        string $code = 'error',
        array $fields = []
    ): void {
        $error = ['message' => $message, 'code' => $code];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }
        self::json(['success' => false, 'error' => $error], $status);
    }
}
