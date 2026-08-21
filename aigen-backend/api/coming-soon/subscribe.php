<?php
// File: api/coming-soon/subscribe.php

require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method tidak diizinkan', 405);
}

$user = Auth::currentUser(); // boleh subscribe tanpa login, cukup email
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$comingSoonId = (int)($input['coming_soon_id'] ?? 0);
$email = trim($input['email'] ?? ($user['email'] ?? ''));

if ($comingSoonId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('coming_soon_id dan email valid wajib diisi', 422);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO coming_soon_subscriptions (coming_soon_id, user_id, email, created_at)
         VALUES (:item, :uid, :email, NOW())'
    );
    $stmt->execute([
        'item'  => $comingSoonId,
        'uid'   => $user['id'] ?? null,
        'email' => $email,
    ]);
    Response::success(null, 'Anda akan diberi tahu saat fitur ini rilis');
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        Response::error('Email ini sudah terdaftar untuk notifikasi fitur ini', 409);
    }
    Response::error('Gagal menyimpan subscription', 500);
}
