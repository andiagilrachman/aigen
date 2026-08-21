<?php
// File: api/auth/login.php

require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method tidak diizinkan', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    Response::error('Email dan password wajib diisi', 422);
}

try {
    $user = Auth::login($email, $password);
    Response::success($user, 'Login berhasil');
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 401);
}
