<?php
// File: api/admin/run-sync.php
// TODO: tambahkan AdminAuth::requireAdmin() setelah core/AdminAuth.php dibangun
// Dipanggil dari tombol "Sync Data" di panel admin, menjalankan job yang sama
// dengan jobs/*.php via cron.

require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method tidak diizinkan', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$job = $input['job'] ?? '';

$allowedJobs = ['sync_stocks', 'sync_fundamental'];
if (!in_array($job, $allowedJobs, true)) {
    Response::error('Job tidak dikenali', 422);
}

$jobPath = __DIR__ . "/../../jobs/{$job}.php";

ob_start();
try {
    include $jobPath;
    $output = ob_get_clean();
    Response::success(['log' => $output], 'Sync selesai');
} catch (Exception $e) {
    $output = ob_get_clean();
    Response::error('Sync gagal: ' . $e->getMessage(), 500, ['log' => $output]);
}
