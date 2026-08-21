<?php

declare(strict_types=1);

namespace Aigen\Credit;

use RuntimeException;

/** Dilempar saat saldo kredit tidak cukup untuk sebuah aksi. */
final class InsufficientCreditException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $balance,
        public readonly int $required
    ) {
        parent::__construct($message);
    }
}
