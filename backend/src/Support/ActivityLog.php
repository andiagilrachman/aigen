<?php

declare(strict_types=1);

namespace Aigen\Support;

use Aigen\Core\Database;
use Aigen\Core\Logger;
use Throwable;

/**
 * Penulis tabel activity_logs.
 *
 * Pencatatan aktivitas TIDAK BOLEH menggagalkan permintaan pengguna. Kalau
 * penulisan log bermasalah, kesalahan hanya dicatat ke file log lalu diabaikan.
 */
final class ActivityLog
{
    public static function record(
        ?int $userId,
        string $action,
        string $description = '',
        ?string $ip = null,
        array $metadata = []
    ): void {
        try {
            $now = Database::driver() === 'sqlite' ? "datetime('now')" : 'NOW()';

            $stmt = Database::connection()->prepare(
                "INSERT INTO activity_logs (user_id, action, description, ip_address, metadata, created_at)
                 VALUES (:user_id, :action, :description, :ip, :metadata, $now)"
            );

            $stmt->execute([
                'user_id'     => $userId,
                'action'      => $action,
                'description' => $description !== '' ? mb_substr($description, 0, 255) : null,
                'ip'          => $ip,
                'metadata'    => $metadata === []
                    ? null
                    : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            Logger::warning('Gagal menulis activity_logs: ' . $e->getMessage(), [
                'action'  => $action,
                'user_id' => $userId,
            ]);
        }
    }
}
