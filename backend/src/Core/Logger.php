<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Pencatat log berbasis file harian.
 *
 * Sengaja tidak menulis ke tabel: kalau database yang bermasalah, log justru
 * paling dibutuhkan dan tidak boleh ikut gagal.
 */
final class Logger
{
    public static function exception(\Throwable $e): void
    {
        self::write('error', sprintf(
            "%s: %s @ %s:%d\n%s",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message . self::formatContext($context));
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message . self::formatContext($context));
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message . self::formatContext($context));
    }

    private static function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }
        return ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function write(string $level, string $message): void
    {
        $dir = App::storagePath('logs');

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);

        @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
