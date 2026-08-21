<?php
// File: api/admin/settings.php
// TODO: tambahkan AdminAuth::requireAdmin() setelah core/AdminAuth.php dibangun

require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT setting_key, setting_value, setting_group, value_type, description FROM system_settings ORDER BY setting_group, setting_key');
    Response::success($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    // Format: { "settings": { "site_name": "AIGen", "trial_days": "3", ... } }
    $settings = $input['settings'] ?? [];

    foreach ($settings as $key => $value) {
        Settings::set($key, $value);
    }
    Response::success(null, 'Pengaturan tersimpan');
    exit;
}

Response::error('Method tidak diizinkan', 405);
