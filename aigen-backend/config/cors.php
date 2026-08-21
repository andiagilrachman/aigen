<?php
// File: config/cors.php
// Dipanggil di awal setiap endpoint (via bootstrap.php)

ob_start(); // cegah warning PHP mengotori JSON, sebelum Response::emit ob_clean

$allowedOrigins = [
    'http://localhost:5173', // Vite dev server
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
