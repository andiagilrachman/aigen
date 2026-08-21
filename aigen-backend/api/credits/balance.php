<?php
// File: api/credits/balance.php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../core/CreditManager.php';

$user = Auth::requireLogin();
$balance = CreditManager::getBalance((int)$user['id']);

Response::success(['balance' => $balance]);
