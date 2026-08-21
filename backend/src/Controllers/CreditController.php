<?php

declare(strict_types=1);

namespace Aigen\Controllers;

use Aigen\Core\Database;
use Aigen\Core\Request;
use Aigen\Core\Response;
use Aigen\Credit\CreditCost;
use Aigen\Credit\CreditManager;
use Aigen\Credit\SubscriptionQuota;

final class CreditController
{
    /**
     * Saldo, kuota, dan daftar tarif. Gratis sesuai kesepakatan model kredit.
     */
    public function balance(Request $request): void
    {
        $userId = $request->userId();

        $costs = [];
        foreach (CreditCost::all() as $key => $info) {
            if ($info['is_active']) {
                $costs[$key] = ['name' => $info['name'], 'cost' => $info['cost']];
            }
        }

        Response::success([
            'balance'      => CreditManager::balance($userId),
            'costs'        => $costs,
            'subscription' => SubscriptionQuota::summary($userId),
        ]);
    }

    /** Riwayat mutasi kredit. */
    public function history(Request $request): void
    {
        $userId = $request->userId();
        $limit = max(1, min($request->int('limit', 25) ?? 25, 100));
        $page = max(1, $request->int('page', 1) ?? 1);
        $offset = ($page - 1) * $limit;

        $items = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['amount'] = (int) $row['amount'];
            $row['balance_after'] = (int) $row['balance_after'];
            return $row;
        }, CreditManager::history($userId, $limit, $offset));

        $total = CreditManager::countHistory($userId);

        Response::success(
            ['items' => $items],
            [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int) ceil($total / $limit),
            ]
        );
    }

    /** Daftar paket top-up dan tier langganan. Informatif, belum ada pembayaran. */
    public function packages(Request $request): void
    {
        $pdo = Database::connection();

        $packages = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['credit_amount'] = (int) $row['credit_amount'];
            $row['bonus_credit'] = (int) $row['bonus_credit'];
            $row['price'] = (float) $row['price'];
            return $row;
        }, $pdo->query(
            'SELECT id, name, credit_amount, bonus_credit, price
               FROM credit_packages WHERE is_active = 1
              ORDER BY sort_order ASC, id ASC'
        )->fetchAll());

        $tiers = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['price'] = (float) $row['price'];
            $row['screening_quota'] = $row['screening_quota'] === null ? null : (int) $row['screening_quota'];
            $row['bonus_credit'] = (int) $row['bonus_credit'];
            $row['features'] = is_string($row['features']) ? json_decode($row['features'], true) : [];
            return $row;
        }, $pdo->query(
            'SELECT id, tier_key, name, price, billing_period, screening_quota, bonus_credit, features
               FROM subscription_tiers WHERE is_active = 1
              ORDER BY sort_order ASC, id ASC'
        )->fetchAll());

        Response::success(['packages' => $packages, 'tiers' => $tiers]);
    }
}
