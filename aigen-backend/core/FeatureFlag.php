<?php
// File: core/FeatureFlag.php

class FeatureFlag {
    private static ?array $cache = null;

    public static function isActive(string $key): bool {
        if (self::$cache === null) {
            $pdo = getDbConnection();
            $stmt = $pdo->query('SELECT feature_key, is_active FROM feature_flags');
            self::$cache = [];
            foreach ($stmt->fetchAll() as $row) {
                self::$cache[$row['feature_key']] = (bool)$row['is_active'];
            }
        }
        return self::$cache[$key] ?? false;
    }
}
