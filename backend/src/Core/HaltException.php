<?php

declare(strict_types=1);

namespace Aigen\Core;

use RuntimeException;

/**
 * Dilempar Response::json() saat mode test aktif, sebagai pengganti exit().
 * Memungkinkan test memeriksa status & payload tanpa mematikan proses.
 */
final class HaltException extends RuntimeException
{
    public function __construct(public readonly int $status)
    {
        parent::__construct('Response halted with status ' . $status);
    }
}
