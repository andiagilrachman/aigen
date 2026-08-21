<?php
// File: api/auth/register.php

require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method tidak diizinkan', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$fullName = trim($input['full_name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($fullName === '' || $email === '' || $password === '') {
    Response::error('Nama, email, dan password wajib diisi', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('Format email tidak valid', 422);
}
if (strlen($password) < 8) {
    Response::error('Password minimal 8 karakter', 422);
}

try {
    $result = Auth::register($fullName, $email, $password);
    Response::success($result, 'Registrasi berhasil');
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 409);
} catch (Exception $e) {
    Response::error('Terjadi kesalahan, coba lagi', 500);
}
