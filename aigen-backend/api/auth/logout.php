<?php
// File: api/auth/logout.php

require_once __DIR__ . '/../../config/bootstrap.php';

Auth::logout();
Response::success(null, 'Logout berhasil');
