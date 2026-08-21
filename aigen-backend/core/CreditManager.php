<?php
// File: core/CreditManager.php

class CreditManager {

    /** Ambil saldo kredit user, bikin wallet kalau belum ada */
    public static function getBalance(int $userId): int {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT balance FROM credit_wallets WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['balance'];
        }
        $insert = $pdo->prepare('INSERT INTO credit_wallets (user_id, balance) VALUES (:id, 0)');
        $insert->execute(['id' => $userId]);
        return 0;
    }

    /** Tambah/kurangi kredit + catat transaksi. $amount positif = masuk, negatif = keluar */
    public static function adjust(int $userId, int $amount, string $type, string $note = '', ?string $refType = null, ?int $refId = null): int {
        $pdo = getDbConnection();
        $currentBalance = self::getBalance($userId);
        $newBalance = $currentBalance + $amount;

        $update = $pdo->prepare('UPDATE credit_wallets SET balance = :balance WHERE user_id = :id');
        $update->execute(['balance' => $newBalance, 'id' => $userId]);

        $log = $pdo->prepare(
            'INSERT INTO credit_transactions (user_id, type, amount, balance_after, reference_type, reference_id, note, created_at)
             VALUES (:user_id, :type, :amount, :balance_after, :ref_type, :ref_id, :note, NOW())'
        );
        $log->execute([
            'user_id'       => $userId,
            'type'          => $type,
            'amount'        => $amount,
            'balance_after' => $newBalance,
            'ref_type'      => $refType,
            'ref_id'        => $refId,
            'note'          => $note,
        ]);

        return $newBalance;
    }

    /** Potong kredit untuk suatu aksi (misal run_screening). Return false kalau saldo tidak cukup. */
    public static function chargeForAction(int $userId, string $actionKey): bool {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT cost FROM credit_costs WHERE action_key = :key AND is_active = 1');
        $stmt->execute(['key' => $actionKey]);
        $row = $stmt->fetch();
        $cost = $row ? (int)$row['cost'] : 0;

        if ($cost <= 0) {
            return true; // aksi ini gratis / tidak dikonfigurasi
        }
        if (self::getBalance($userId) < $cost) {
            return false;
        }
        self::adjust($userId, -$cost, 'usage', "Aksi: $actionKey", $actionKey);
        return true;
    }

    /** Refund otomatis kalau proses gagal setelah kredit terpotong */
    public static function refundForAction(int $userId, string $actionKey): void {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT cost FROM credit_costs WHERE action_key = :key');
        $stmt->execute(['key' => $actionKey]);
        $row = $stmt->fetch();
        $cost = $row ? (int)$row['cost'] : 0;
        if ($cost > 0) {
            self::adjust($userId, $cost, 'refund', "Refund aksi gagal: $actionKey", $actionKey);
        }
    }
}
