<?php

declare(strict_types=1);

namespace Aigen\Credit;

use Aigen\Core\Database;
use Aigen\Core\Settings;

/**
 * Gerbang pemakaian fitur berbayar.
 *
 * Mengimplementasikan alur blueprint yang di versi lama DILEWATI sepenuhnya —
 * kode lama langsung memotong kredit tanpa pernah melihat kuota tier:
 *
 *   1. Aksi gratis?                       -> lolos, tanpa biaya
 *   2. Masih punya kuota harian tier?     -> pakai kuota, kredit tidak dipotong
 *   3. Kuota habis / tanpa kuota?         -> potong kredit
 *   4. Proses gagal setelah dipotong?     -> refund otomatis
 *
 * Pemakaian:
 *     $gate = UsageGate::open($userId, 'run_screening');
 *     try {
 *         $hasil = ...;            // pekerjaan sesungguhnya
 *         $gate->commit();
 *     } catch (Throwable $e) {
 *         $gate->rollback();       // kredit dikembalikan bila sempat dipotong
 *         throw $e;
 *     }
 */
final class UsageGate
{
    public const CHARGE_FREE  = 'free';
    public const CHARGE_QUOTA = 'quota';
    public const CHARGE_CREDIT = 'credit';

    private bool $settled = false;

    private function __construct(
        public readonly int $userId,
        public readonly string $actionKey,
        public readonly string $chargeType,
        public readonly int $creditsCharged,
        public readonly int $balanceAfter,
        public readonly ?int $quotaRemaining
    ) {
    }

    /**
     * @throws InsufficientCreditException bila kuota habis dan kredit kurang.
     * @throws UnknownActionException      bila action_key belum ada di credit_costs.
     */
    public static function open(int $userId, string $actionKey): self
    {
        $cost = CreditCost::for($actionKey);

        if ($cost === null) {
            throw new UnknownActionException(
                "Aksi '$actionKey' belum terdaftar di tabel credit_costs"
            );
        }

        // 1. Aksi gratis
        if ($cost === 0) {
            return new self(
                $userId,
                $actionKey,
                self::CHARGE_FREE,
                0,
                CreditManager::balance($userId),
                null
            );
        }

        // 2. Coba pakai kuota harian dari tier langganan
        $quota = SubscriptionQuota::consume($userId, $actionKey);
        if ($quota !== null) {
            return new self(
                $userId,
                $actionKey,
                self::CHARGE_QUOTA,
                0,
                CreditManager::balance($userId),
                $quota
            );
        }

        // 3. Kuota habis atau tier tanpa kuota -> potong kredit
        $balance = CreditManager::debit(
            $userId,
            $cost,
            CreditCost::all()[$actionKey]['name'] ?? $actionKey,
            $actionKey
        );

        return new self($userId, $actionKey, self::CHARGE_CREDIT, $cost, $balance, null);
    }

    /** Tandai pemakaian berhasil. Tidak ada yang dikembalikan. */
    public function commit(): void
    {
        $this->settled = true;
    }

    /** Proses gagal: kembalikan kredit yang sempat dipotong. */
    public function rollback(string $reason = 'Proses gagal, kredit dikembalikan'): void
    {
        if ($this->settled) {
            return;
        }
        $this->settled = true;

        if ($this->chargeType === self::CHARGE_CREDIT && $this->creditsCharged > 0) {
            CreditManager::refund(
                $this->userId,
                $this->creditsCharged,
                $reason,
                $this->actionKey
            );
        }

        if ($this->chargeType === self::CHARGE_QUOTA) {
            SubscriptionQuota::giveBack($this->userId);
        }
    }

    /** Ringkasan untuk dikirim ke frontend. */
    public function meta(): array
    {
        return [
            'charge_type'     => $this->chargeType,
            'credits_charged' => $this->creditsCharged,
            'credit_balance'  => $this->chargeType === self::CHARGE_CREDIT
                ? $this->balanceAfter
                : CreditManager::balance($this->userId),
            'quota_remaining' => $this->quotaRemaining,
        ];
    }
}
