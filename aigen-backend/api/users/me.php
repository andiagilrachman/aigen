<?php
// File: api/users/me.php

require_once __DIR__ . '/../../config/bootstrap.php';

$user = Auth::requireLogin();
$balance = CreditManager::getBalance((int)$user['id']);

Response::success(array_merge($user, ['credit_balance' => $balance]));
