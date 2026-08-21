<?php
// File: api/coming-soon/vote.php

require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method tidak diizinkan', 405);
}

$user = Auth::requireLogin();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$comingSoonId = (int)($input['coming_soon_id'] ?? 0);

if ($comingSoonId <= 0) {
    Response::error('coming_soon_id wajib diisi', 422);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO coming_soon_votes (coming_soon_id, user_id, created_at) VALUES (:item, :uid, NOW())'
    );
    $stmt->execute(['item' => $comingSoonId, 'uid' => $user['id']]);
    Response::success(null, 'Vote tersimpan');
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        Response::error('Anda sudah vote fitur ini', 409);
    }
    Response::error('Gagal menyimpan vote', 500);
}
