<?php
// File: config/bootstrap.php
// Include ini di baris pertama SETIAP file di /api/*.php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Settings.php';
require_once __DIR__ . '/../core/FeatureFlag.php';
require_once __DIR__ . '/../core/CreditManager.php';
require_once __DIR__ . '/../core/Auth.php';

$pdo = getDbConnection();
