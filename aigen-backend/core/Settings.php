<?php
// File: core/Settings.php
// Prinsip no-hardcode: nilai yang bisa berubah dibaca dari sini, bukan ditulis di kode

class Settings {
    private static ?array $cache = null;

    private static function loadAll(): array {
        if (self::$cache === null) {
            $pdo = getDbConnection();
            $stmt = $pdo->query('SELECT setting_key, setting_value, value_type FROM system_settings');
            self::$cache = [];
            foreach ($stmt->fetchAll() as $row) {
                self::$cache[$row['setting_key']] = self::castValue($row['setting_value'], $row['value_type']);
            }
        }
        return self::$cache;
    }

    private static function castValue($value, string $type) {
        return match ($type) {
            'number'  => is_numeric($value) ? $value + 0 : 0,
            'boolean' => in_array(strtolower((string)$value), ['1', 'true'], true),
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }

    public static function get(string $key, $default = null) {
        $all = self::loadAll();
        return $all[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'UPDATE system_settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key'
        );
        $stmt->execute(['value' => (string)$value, 'key' => $key]);
        self::$cache = null; // invalidate cache
    }
}
