<?php
// File: api/theme/presets.php

require_once __DIR__ . '/../../config/bootstrap.php';

$stmt = $pdo->query('SELECT * FROM theme_presets ORDER BY sort_order ASC');
Response::success($stmt->fetchAll());
