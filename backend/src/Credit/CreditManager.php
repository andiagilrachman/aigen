<?php

declare(strict_types=1);

namespace Aigen\Credit;

use Aigen\Core\Database;
use RuntimeException;

/**
 * Dompet kredit.
 *
 * PERBAIKAN KRITIKAL dari versi lama:
 * versi lama memakai pola read-then-write —
 *     $saldo = getBalance($id);          // baca
 *     $baru  = $saldo + $amount;         // hitung di PHP
 *     UPDATE ... SET balance = $baru;    // tulis
 * Dua request bersamaan bisa membaca saldo sama dan saling menimpa, sehingga
 * kredit terpakai ganda atau saldo jadi minus.
 *
 * Versi ini memakai UPDATE kondisional atomik:
 *     UPDATE credit_wallets
 *        SET balance = balance - :cost
 *      WHERE user_id = :id AND balance >= :cost
 * lalu memeriksa rowCount(). Database yang menjamin saldo tidak pernah minus,
 * bukan PHP.
 */
final class CreditManager
{
    /** Pastikan baris dompet ada untuk user ini. */
    public static function ensureWallet(int $userId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT 1 FROM credit_wallets WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO credit_wallets (user_id, balance) VALUES (:id, 0)'
        );
        try {
            $insert->execute(['id' => $userId]);
        } catch (\PDOException $e) {
            // 23000 = duplicate key: proses lain sudah membuatnya lebih dulu. Aman.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    public static function balance(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT balance FROM credit_wallets WHERE user_id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $balance = $stmt->fetchColumn();

        return $balance === false ? 0 : (int) $balance;
    }

    /**
     * Tambah kredit (topup / bonus / trial / refund).
     *
     * @return int Saldo setelah penambahan.
     */
    public static function credit(
        int $userId,
        int $amount,
        string $type,
        string $note = '',
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        if ($amount <= 0) {
            throw new RuntimeException('Jumlah kredit harus lebih besar dari nol');
        }

        return Database::transaction(static function ($pdo) use (
            $userId, $amount, $type, $note, $referenceType, $referenceId
        ): int {
            self::ensureWallet($userId);

            $update = $pdo->prepare(
                'UPDATE credit_wallets SET balance = balance + :amount WHERE user_id = :id'
            );
            $update->execute(['amount' => $amount, 'id' => $userId]);

            $balance = self::balance($userId);
            self::log($userId, $type, $amount, $balance, $note, $referenceType, $referenceId);

            return $balance;
        });
    }

    /**
     * Potong kredit secara atomik.
     *
     * @throws InsufficientCreditException bila saldo tidak cukup.
     * @return int Saldo setelah pemotongan.
     */
    public static function debit(
        int $userId,
        int $amount,
        string $note = '',
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        if ($amount < 0) {
            throw new RuntimeException('Jumlah potongan tidak boleh negatif');
        }
        if ($amount === 0) {
            return self::balance($userId);
        }

        return Database::transaction(static function ($pdo) use (
            $userId, $amount, $note, $referenceType, $referenceId
        ): int {
            self::ensureWallet($userId);

            // Inti perbaikan: cek saldo dan pengurangan terjadi dalam SATU pernyataan.
            $update = $pdo->prepare(
                'UPDATE credit_wallets
                    SET balance = balance - :amount
                  WHERE user_id = :id
                    AND balance >= :required'
            );
            $update->execute([
                'amount'   => $amount,
                'id'       => $userId,
                'required' => $amount,
            ]);

            if ($update->rowCount() === 0) {
                throw new InsufficientCreditException(
                    'Kredit Anda tidak mencukupi untuk aksi ini',
                    self::balance($userId),
                    $amount
                );
            }

            $balance = self::balance($userId);
            self::log($userId, 'usage', -$amount, $balance, $note, $referenceType, $referenceId);

            return $balance;
        });
    }

    /** Kembalikan kredit yang sudah dipotong ketika proses gagal di tengah jalan. */
    public static function refund(
        int $userId,
        int $amount,
        string $note = '',
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        return self::credit($userId, $amount, 'refund', $note, $referenceType, $referenceId);
    }

    private static function log(
        int $userId,
        string $type,
        int $amount,
        int $balanceAfter,
        string $note,
        ?string $referenceType,
        ?int $referenceId
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO credit_transactions
                (user_id, type, amount, balance_after, reference_type, reference_id, note, created_at)
             VALUES
                (:user_id, :type, :amount, :balance_after, :reference_type, :reference_id, :note, ' . self::now() . ')'
        );
        $stmt->execute([
            'user_id'        => $userId,
            'type'           => $type,
            'amount'         => $amount,
            'balance_after'  => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'note'           => $note !== '' ? $note : null,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function history(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        $stmt = Database::connection()->prepare(
            "SELECT id, type, amount, balance_after, reference_type, note, created_at
               FROM credit_transactions
              WHERE user_id = :id
              ORDER BY id DESC
              LIMIT $limit OFFSET $offset"
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function countHistory(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM credit_transactions WHERE user_id = :id'
        );
        $stmt->execute(['id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /** SQLite tidak punya NOW(); dipakai agar test suite jalan di kedua driver. */
    private static function now(): string
    {
        return Database::driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
