<?php

declare(strict_types=1);

namespace Aigen\Auth;

use Aigen\Core\Database;
use Aigen\Core\Response;
use Aigen\Core\Settings;
use Aigen\Credit\CreditManager;
use PDOException;
use RuntimeException;

/**
 * Autentikasi berbasis sesi PHP.
 */
final class Auth
{
    private static array $sessionConfig = [
        'name'      => 'aigen_session',
        'lifetime'  => 0,
        'same_site' => 'Lax',
        'secure'    => false,
    ];

    private static ?array $testUser = null;

    public static function configure(array $config): void
    {
        self::$sessionConfig = array_merge(self::$sessionConfig, $config);
    }

    /** Dipakai test suite untuk memalsukan user login tanpa sesi HTTP. */
    public static function actingAs(?array $user): void
    {
        self::$testUser = $user;
    }

    public static function startSession(): void
    {
        if (self::$testUser !== null) {
            return;
        }
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        session_name((string) self::$sessionConfig['name']);
        session_set_cookie_params([
            'lifetime' => (int) self::$sessionConfig['lifetime'],
            'path'     => '/',
            'httponly' => true,
            'samesite' => (string) self::$sessionConfig['same_site'],
            'secure'   => (bool) self::$sessionConfig['secure'],
        ]);
        session_start();
    }

    public static function register(string $fullName, string $email, string $password): array
    {
        $email = strtolower(trim($email));

        return Database::transaction(static function ($pdo) use ($fullName, $email, $password): array {
            $now = self::now();

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role, status, created_at)
                     VALUES (:full_name, :email, :password_hash, 'user', 'active', $now)"
                );
                $stmt->execute([
                    'full_name'     => $fullName,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ]);
            } catch (PDOException $e) {
                // 23000 = unique violation pada kolom email.
                if ($e->getCode() === '23000') {
                    throw new RuntimeException('Email sudah terdaftar');
                }
                throw $e;
            }

            $userId = (int) $pdo->lastInsertId();

            CreditManager::ensureWallet($userId);

            // Kredit trial. Berkat Database::transaction() yang sadar transaksi
            // bersarang, ini aman dipanggil DI DALAM transaksi — tidak seperti
            // versi lama yang terpaksa memanggilnya di luar.
            $trialCredit = Settings::int('trial_credit_amount', 0);
            if ($trialCredit > 0) {
                CreditManager::credit(
                    $userId,
                    $trialCredit,
                    'trial',
                    'Kredit trial pendaftaran'
                );
            }

            self::grantTrialSubscription($userId);

            return ['user_id' => $userId];
        });
    }

    /** Beri langganan tier trial selama N hari sesuai system_settings. */
    private static function grantTrialSubscription(int $userId): void
    {
        $tierKey = Settings::string('trial_tier_key', '');
        $days    = Settings::int('trial_duration_days', 0);

        if ($tierKey === '' || $days <= 0) {
            return;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT id FROM subscription_tiers WHERE tier_key = :k AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['k' => $tierKey]);
        $tierId = $stmt->fetchColumn();

        if ($tierId === false) {
            return;
        }

        $endsAt = Database::driver() === 'sqlite'
            ? "datetime('now', '+$days day')"
            : "DATE_ADD(NOW(), INTERVAL $days DAY)";

        $insert = $pdo->prepare(
            "INSERT INTO user_subscriptions (user_id, tier_id, status, started_at, ends_at, created_at)
             VALUES (:user_id, :tier_id, 'active', " . self::now() . ", $endsAt, " . self::now() . ')'
        );
        $insert->execute(['user_id' => $userId, 'tier_id' => (int) $tierId]);
    }

    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // password_verify tetap dijalankan pada hash dummy saat user tidak ada,
        // supaya waktu respons tidak membocorkan email mana yang terdaftar.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforeseeingsomethingthatneverexistsxx';

        if (!password_verify($password, $hash) || $user === false) {
            throw new RuntimeException('Email atau password salah');
        }
        if ($user['status'] === 'suspended') {
            throw new RuntimeException('Akun Anda telah ditangguhkan');
        }

        self::startSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true); // cegah session fixation
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role']    = $user['role'];
        }

        $update = Database::connection()->prepare(
            'UPDATE users SET last_login_at = ' . self::now() . ' WHERE id = :id'
        );
        $update->execute(['id' => $user['id']]);

        CreditManager::ensureWallet((int) $user['id']);

        unset($user['password_hash']);

        return self::publicShape($user);
    }

    public static function logout(): void
    {
        self::startSession();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'httponly' => true,
                    'samesite' => $params['samesite'] ?: 'Lax',
                    'secure'   => (bool) $params['secure'],
                ]);
            }
            session_destroy();
        }
    }

    public static function currentUser(): ?array
    {
        if (self::$testUser !== null) {
            return self::$testUser;
        }

        self::startSession();

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, full_name, email, email_verified_at, phone, bio, photo_url,
                    role, status, language, theme_preference, last_login_at, created_at
               FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || $user['status'] === 'suspended') {
            return null;
        }

        return self::publicShape($user);
    }

    public static function requireLogin(): array
    {
        $user = self::currentUser();
        if ($user === null) {
            Response::error('Silakan login terlebih dahulu', 401, 'unauthenticated');
        }
        return $user;
    }

    /**
     * Guard peran admin.
     *
     * PERBAIKAN KRITIKAL: versi lama sama sekali tidak punya ini —
     * api/admin/settings.php dan run-sync.php terbuka untuk siapa pun,
     * hanya ditandai komentar "TODO: tambahkan AdminAuth".
     */
    public static function requireRole(string ...$roles): array
    {
        $user = self::requireLogin();

        if (!in_array($user['role'], $roles, true)) {
            Response::error('Anda tidak memiliki akses ke sumber daya ini', 403, 'forbidden');
        }

        return $user;
    }

    public static function requireAdmin(): array
    {
        return self::requireRole('super_admin', 'admin');
    }

    public static function requireSuperAdmin(): array
    {
        return self::requireRole('super_admin');
    }

    private static function publicShape(array $user): array
    {
        unset($user['password_hash']);

        $user['id'] = (int) $user['id'];

        if (isset($user['theme_preference']) && is_string($user['theme_preference'])) {
            $user['theme_preference'] = json_decode($user['theme_preference'], true) ?: null;
        }

        return $user;
    }

    private static function now(): string
    {
        return Database::driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
