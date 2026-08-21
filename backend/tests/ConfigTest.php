<?php

declare(strict_types=1);

use Aigen\Core\Database;
use Aigen\Core\FeatureFlag;
use Aigen\Core\Settings;
use Aigen\Tests\TestCase;

TestCase::group('Skema & seed');

TestCase::test('seluruh 31 tabel terbentuk dari schema.sql', function (): void {
    $tables = Database::connection()
        ->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
        ->fetchAll(PDO::FETCH_COLUMN);

    TestCase::assertCount(31, $tables);
});

TestCase::test('seed mengisi tabel konfigurasi', function (): void {
    $pdo = Database::connection();
    $jumlah = static fn (string $t): int => (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();

    TestCase::assertTrue($jumlah('system_settings') > 0, 'system_settings kosong');
    TestCase::assertTrue($jumlah('credit_costs') > 0, 'credit_costs kosong');
    TestCase::assertSame(10, $jumlah('nav_menu'));
    TestCase::assertSame(4, $jumlah('subscription_tiers'));
    TestCase::assertSame(8, $jumlah('theme_presets'));
});

TestCase::test('seed tidak memuat API key vendor', function (): void {
    $keys = Database::connection()
        ->query('SELECT api_key FROM data_vendors')
        ->fetchAll(PDO::FETCH_COLUMN);

    foreach ($keys as $key) {
        TestCase::assertTrue(
            $key === null || $key === '',
            'api_key wajib kosong di seed — kredensial tidak boleh masuk Git'
        );
    }
});

TestCase::test('coming_soon_items terhubung ke nav_menu tanpa baris yatim', function (): void {
    $yatim = (int) Database::connection()->query(
        'SELECT COUNT(*) FROM coming_soon_items c
         LEFT JOIN nav_menu n ON n.id = c.nav_menu_id
         WHERE n.id IS NULL'
    )->fetchColumn();

    TestCase::assertSame(0, $yatim);
});

TestCase::group('Pengaturan sistem');

TestCase::test('nilai dibaca dengan tipe yang benar', function (): void {
    TestCase::assertSame('AIGen', Settings::string('site_name'));
    TestCase::assertSame(100, Settings::int('trial_credit_amount'));
    TestCase::assertSame(7, Settings::int('trial_duration_days'));
    TestCase::assertTrue(Settings::bool('allow_registration'));
});

TestCase::test('kunci yang tidak ada memakai nilai bawaan', function (): void {
    TestCase::assertSame('cadangan', Settings::string('kunci_tidak_ada', 'cadangan'));
    TestCase::assertSame(42, Settings::int('kunci_tidak_ada', 42));
});

TestCase::test('model kredit hasil kesepakatan tersimpan di database', function (): void {
    // Nilai-nilai ini adalah keputusan produk, jadi harus berada di database,
    // bukan tersebar sebagai angka ajaib di dalam kode.
    TestCase::assertSame(7, Settings::int('trial_duration_days'), 'trial 7 hari');
    TestCase::assertSame(100, Settings::int('trial_credit_amount'), 'trial 100 kredit');

    $free = Database::connection()->query(
        "SELECT screening_quota FROM subscription_tiers WHERE tier_key = 'free'"
    )->fetchColumn();
    TestCase::assertSame(5, (int) $free, 'tier free dibatasi 5 screening per hari');
});

TestCase::group('Feature flag');

TestCase::test('flag aktif dan nonaktif terbaca sesuai seed', function (): void {
    TestCase::assertTrue(FeatureFlag::isActive('fundamental_screening'));
    TestCase::assertTrue(!FeatureFlag::isActive('payment_midtrans'), 'Midtrans belum aktif di rilis pertama');
});
