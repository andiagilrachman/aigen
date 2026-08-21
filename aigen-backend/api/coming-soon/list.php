<?php
// File: api/coming-soon/list.php

require_once __DIR__ . '/../../config/bootstrap.php';

$user = Auth::currentUser(); // boleh null, tetap bisa lihat daftar tanpa login

$stmt = $pdo->query(
    'SELECT csi.*, nm.menu_key, nm.label,
            (SELECT COUNT(*) FROM coming_soon_votes v WHERE v.coming_soon_id = csi.id) AS vote_count
     FROM coming_soon_items csi
     LEFT JOIN nav_menu nm ON nm.id = csi.nav_menu_id
     ORDER BY csi.id ASC'
);
$items = $stmt->fetchAll();

if ($user) {
    $votedStmt = $pdo->prepare('SELECT coming_soon_id FROM coming_soon_votes WHERE user_id = :uid');
    $votedStmt->execute(['uid' => $user['id']]);
    $votedIds = array_column($votedStmt->fetchAll(), 'coming_soon_id');
    foreach ($items as &$item) {
        $item['has_voted'] = in_array($item['id'], $votedIds);
    }
}

Response::success($items);
