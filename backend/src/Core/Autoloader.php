<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Autoloader PSR-4 minimal untuk namespace Aigen\.
 * Proyek ini sengaja tanpa Composer (blueprint: PHP native).
 */
final class Autoloader
{
    public static function register(string $baseDir, string $prefix = 'Aigen\\'): void
    {
        spl_autoload_register(static function (string $class) use ($baseDir, $prefix): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = rtrim($baseDir, '/') . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
