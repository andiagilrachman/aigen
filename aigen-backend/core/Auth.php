<?php
// File: core/Auth.php

class Auth {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function register(string $fullName, string $email, string $password): array {
        $pdo = getDbConnection();

        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            throw new RuntimeException('Email sudah terdaftar');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, status, created_at)
                 VALUES (:full_name, :email, :password_hash, "user", "active", NOW())'
            );
            $stmt->execute([
                'full_name'     => $fullName,
                'email'         => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ]);
            $userId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Kredit trial diberikan DI LUAR transaksi utama (hindari nested transaction, pelajaran dari histori bug)
        $trialCredit = (int)Settings::get('trial_credit_amount', 0);
        if ($trialCredit > 0) {
            CreditManager::adjust($userId, $trialCredit, 'trial', 'Kredit trial pendaftaran');
        }

        return ['user_id' => $userId];
    }

    public static function login(string $email, string $password): array {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Email atau password salah');
        }
        if ($user['status'] === 'suspended') {
            throw new RuntimeException('Akun Anda telah ditangguhkan');
        }

        self::startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);

        unset($user['password_hash']);
        return $user;
    }

    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    public static function currentUser(): ?array {
        self::startSession();
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, phone, bio, photo_url, role, status, language, theme_preference
             FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function requireLogin(): array {
        $user = self::currentUser();
        if (!$user) {
            Response::error('Silakan login terlebih dahulu', 401);
        }
        return $user;
    }
}
