<?php

declare(strict_types=1);

namespace Aigen\Vendor;

use RuntimeException;

/**
 * Kegagalan saat memanggil vendor data.
 *
 * Membedakan gangguan sementara dari masalah permanen, karena keduanya menuntut
 * tindakan berbeda: 502/503 cukup dicoba ulang, sedangkan 401/402 berarti API
 * key atau langganan bermasalah dan mengulang hanya membuang waktu.
 */
final class VendorException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly string $endpoint = '',
        public readonly bool $quotaExhausted = false,
    ) {
        parent::__construct($message);
    }

    /**
     * Kuota harian kita sendiri sudah habis — permintaan bahkan tidak dikirim.
     *
     * Berbeda dari 429 milik vendor: menunggu sebentar tidak menolong, dan
     * emiten berikutnya pasti bernasib sama, jadi job harus langsung berhenti
     * alih-alih menghabiskan waktu pada kegagalan yang sudah pasti.
     */
    public function isQuotaExhausted(): bool
    {
        return $this->quotaExhausted;
    }

    /** Layak dicoba ulang: gangguan jaringan atau server vendor sedang sakit. */
    public function isTransient(): bool
    {
        return $this->httpStatus === 0          // gagal konek / timeout
            || $this->httpStatus === 408
            || $this->httpStatus === 429
            || $this->httpStatus >= 500;
    }

    /** Masalah kredensial atau langganan — percuma diulang tanpa campur tangan manusia. */
    public function isAuthProblem(): bool
    {
        return in_array($this->httpStatus, [401, 402, 403], true);
    }
}
