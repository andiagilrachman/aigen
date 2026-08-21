<?php
// File: api/theme/save-preference.php

require_once __DIR__ . '/../../config/bootstrap.php';

$user = Auth::requireLogin();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$preference = json_encode([
    'preset_id'      => $input['preset_id'] ?? null,
    'primary_color'  => $input['primary_color'] ?? null,
    'accent_color'   => $input['accent_color'] ?? null,
    'background'     => $input['background'] ?? null,
    'radius'         => $input['radius'] ?? null,
    'auto_switch'    => (bool)($input['auto_switch'] ?? false),
]);

$stmt = $pdo->prepare('UPDATE users SET theme_preference = :pref WHERE id = :id');
$stmt->execute(['pref' => $preference, 'id' => $user['id']]);

Response::success(null, 'Preferensi tema tersimpan');
