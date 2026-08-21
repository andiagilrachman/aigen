<?php

declare(strict_types=1);

namespace Aigen\Credit;

use RuntimeException;

/**
 * Dilempar bila action_key tidak ditemukan di tabel credit_costs.
 *
 * Sengaja keras (bukan diam-diam menganggap gratis) supaya seed yang belum
 * lengkap ketahuan saat pengembangan, bukan setelah rilis.
 */
final class UnknownActionException extends RuntimeException
{
}
