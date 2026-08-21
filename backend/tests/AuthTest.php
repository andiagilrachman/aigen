<?php

declare(strict_types=1);

use Aigen\Auth\Auth;
use Aigen\Core\Database;
use Aigen\Core\Settings;
use Aigen\Credit\CreditManager;
use Aigen\Credit\SubscriptionQuota;
use Aigen\Credit\UsageGate;
use Aigen\Tests\TestCase;

TestCase::group('Autentikasi — pendaftaran');

TestCase::test('pendaftaran membuat user, dompet, dan kredit trial', function (): void {
    $result = Auth::register('Budi Santoso', 'budi@test.id', 'rahasia123');
    $userId = $result['user_id'];

    TestCase::assertTrue($userId > 0, 'user id harus terisi');

    // Model kredit yang disepakati: trial 100 kredit.
    $expected = Settings::int('trial_credit_amount', 0);
    TestCase::assertSame(100, $expected, 'seed harus menetapkan trial 100 kredit');
    TestCase::assertSame(100, CreditManager::balance($userId), 'saldo awal harus 100');

    $history = CreditManager::history($userId);
    TestCase::assertSame('trial', $history[0]['type']);
});

TestCase::test('pendaftaran memberi langganan tier trial yang aktif', function (): void {
    $result = Auth::register('Sari Dewi', 'sari@test.id', 'rahasia123');
    $userId = $result['user_id'];

    $tier = SubscriptionQuota::activeTier($userId);
    TestCase::assertTrue($tier !== null, 'user baru harus punya langganan aktif');
    TestCase::assertSame('trial', $tier['tier_key']);
    TestCase::assertSame(20, (int) $tier['screening_quota'], 'tier trial punya kuota 20/hari');
});

TestCase::test('email ganda ditolak', function (): void {
    Auth::register('Orang Pertama', 'kembar@test.id', 'rahasia123');

    TestCase::assertThrows(
        RuntimeException::class,
        static fn () => Auth::register('Orang Kedua', 'kembar@test.id', 'rahasia123')
    );
});

TestCase::test('pendaftaran gagal tidak menyisakan user setengah jadi', function (): void {
    $pdo = Database::connection();
    $before = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'kembar@test.id'")->fetchColumn();

    try {
        Auth::register('Orang Ketiga', 'kembar@test.id', 'rahasia123');
    } catch (RuntimeException) {
        // diharapkan
    }

    $after = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'kembar@test.id'")->fetchColumn();
    TestCase::assertSame($before, $after, 'transaksi harus dibatalkan seluruhnya');
});

TestCase::group('Autentikasi — login');

TestCase::test('login dengan kredensial benar mengembalikan data user', function (): void {
    Auth::register('Login Sah', 'loginsah@test.id', 'rahasia123');

    $user = Auth::login('loginsah@test.id', 'rahasia123');

    TestCase::assertSame('loginsah@test.id', $user['email']);
    TestCase::assertTrue(!isset($user['password_hash']), 'hash password tidak boleh ikut terkirim');
});

TestCase::test('password salah ditolak', function (): void {
    Auth::register('Login Salah', 'loginsalah@test.id', 'rahasia123');

    TestCase::assertThrows(
        RuntimeException::class,
        static fn () => Auth::login('loginsalah@test.id', 'passwordkeliru')
    );
});

TestCase::test('email tidak terdaftar ditolak dengan pesan yang sama', function (): void {
    $e = TestCase::assertThrows(
        RuntimeException::class,
        static fn () => Auth::login('tidakada@test.id', 'apapun')
    );

    // Pesan harus identik dengan kasus password salah, supaya penyerang tidak
    // bisa memakai perbedaan pesan untuk memetakan email yang terdaftar.
    TestCase::assertSame('Email atau password salah', $e->getMessage());
});

TestCase::test('akun suspended tidak bisa login', function (): void {
    $result = Auth::register('Akun Beku', 'beku@test.id', 'rahasia123');
    Database::connection()
        ->prepare("UPDATE users SET status = 'suspended' WHERE id = :id")
        ->execute(['id' => $result['user_id']]);

    $e = TestCase::assertThrows(
        RuntimeException::class,
        static fn () => Auth::login('beku@test.id', 'rahasia123')
    );
    TestCase::assertSame('Akun Anda telah ditangguhkan', $e->getMessage());
});

TestCase::group('Kuota langganan');

TestCase::test('user trial memakai kuota harian sebelum kredit dipotong', function (): void {
    $result = Auth::register('Pengguna Trial', 'trialuser@test.id', 'rahasia123');
    $userId = $result['user_id'];

    $saldoAwal = CreditManager::balance($userId);

    $gate = UsageGate::open($userId, 'run_screening');
    $gate->commit();

    TestCase::assertSame(
        UsageGate::CHARGE_QUOTA,
        $gate->chargeType,
        'selama kuota tersedia, kredit tidak boleh dipotong'
    );
    TestCase::assertSame($saldoAwal, CreditManager::balance($userId), 'saldo harus utuh');
});

TestCase::test('kredit baru dipotong setelah kuota harian habis', function (): void {
    $result = Auth::register('Kuota Habis', 'kuotahabis@test.id', 'rahasia123');
    $userId = $result['user_id'];

    $kuota = 20; // tier trial
    for ($i = 0; $i < $kuota; $i++) {
        UsageGate::open($userId, 'run_screening')->commit();
    }

    $saldoSebelum = CreditManager::balance($userId);
    TestCase::assertSame(100, $saldoSebelum, 'kuota belum menyentuh saldo');

    $gate = UsageGate::open($userId, 'run_screening');
    $gate->commit();

    TestCase::assertSame(UsageGate::CHARGE_CREDIT, $gate->chargeType, 'kuota habis harus beralih ke kredit');
    TestCase::assertSame(99, CreditManager::balance($userId));
});
