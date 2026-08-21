<?php

declare(strict_types=1);

use Aigen\Core\Database;
use Aigen\Credit\CreditCost;
use Aigen\Credit\CreditManager;
use Aigen\Credit\InsufficientCreditException;
use Aigen\Credit\UnknownActionException;
use Aigen\Credit\UsageGate;
use Aigen\Tests\TestCase;

/** Buat user polos langsung lewat SQL, tanpa trial, agar saldo bisa dikontrol. */
function makeUser(string $email, int $balance = 0): int
{
    $pdo = Database::connection();
    $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, role, status, created_at)
         VALUES (:n, :e, 'x', 'user', 'active', datetime('now'))"
    )->execute(['n' => 'Uji ' . $email, 'e' => $email]);

    $id = (int) $pdo->lastInsertId();
    CreditManager::ensureWallet($id);

    if ($balance > 0) {
        CreditManager::credit($id, $balance, 'bonus', 'Saldo awal test');
    }

    return $id;
}

TestCase::group('Kredit — tarif dari database');

TestCase::test('biaya dibaca dari tabel credit_costs, bukan hardcode', function (): void {
    TestCase::assertSame(1, CreditCost::for('run_screening'), 'screening harus 1 kredit');
    TestCase::assertSame(2, CreditCost::for('view_stock_detail'), 'detail harus 2 kredit');
    TestCase::assertSame(0, CreditCost::for('view_balance'), 'cek saldo harus gratis');
    TestCase::assertSame(0, CreditCost::for('add_watchlist'), 'watchlist harus gratis');
});

TestCase::test('aksi yang belum terdaftar mengembalikan null, bukan 0', function (): void {
    // Ini pembeda penting: "gratis" dan "belum dikonfigurasi" tidak boleh sama,
    // kalau tidak, aksi berbayar bisa diam-diam menjadi gratis saat seed lupa.
    TestCase::assertSame(null, CreditCost::for('aksi_yang_tidak_ada'));
});

TestCase::group('Kredit — dompet');

TestCase::test('saldo bertambah dan tercatat di credit_transactions', function (): void {
    $userId = makeUser('wallet1@test.id');

    $balance = CreditManager::credit($userId, 100, 'trial', 'Kredit trial');
    TestCase::assertSame(100, $balance);
    TestCase::assertSame(100, CreditManager::balance($userId));

    $history = CreditManager::history($userId);
    TestCase::assertCount(1, $history);
    TestCase::assertSame(100, (int) $history[0]['amount']);
    TestCase::assertSame(100, (int) $history[0]['balance_after']);
});

TestCase::test('pemotongan mengurangi saldo dan dicatat negatif', function (): void {
    $userId = makeUser('wallet2@test.id', 10);

    $balance = CreditManager::debit($userId, 3, 'Screening');
    TestCase::assertSame(7, $balance);

    $history = CreditManager::history($userId);
    TestCase::assertSame(-3, (int) $history[0]['amount'], 'pemakaian harus tercatat negatif');
});

TestCase::test('saldo tidak cukup ditolak dan saldo tidak berubah', function (): void {
    $userId = makeUser('wallet3@test.id', 1);

    TestCase::assertThrows(
        InsufficientCreditException::class,
        static fn () => CreditManager::debit($userId, 5, 'Terlalu mahal')
    );

    TestCase::assertSame(1, CreditManager::balance($userId), 'saldo harus utuh setelah penolakan');
});

TestCase::test('saldo tidak pernah menjadi negatif walau dipotong berkali-kali', function (): void {
    $userId = makeUser('wallet4@test.id', 5);

    $sukses = 0;
    for ($i = 0; $i < 10; $i++) {
        try {
            CreditManager::debit($userId, 1, 'Potong beruntun');
            $sukses++;
        } catch (InsufficientCreditException) {
            break;
        }
    }

    TestCase::assertSame(5, $sukses, 'hanya 5 pemotongan yang boleh berhasil');
    TestCase::assertSame(0, CreditManager::balance($userId));
});

TestCase::group('UsageGate — gerbang pemakaian');

TestCase::test('aksi gratis tidak memotong kredit', function (): void {
    $userId = makeUser('gate1@test.id', 10);

    $gate = UsageGate::open($userId, 'view_balance');
    $gate->commit();

    TestCase::assertSame(UsageGate::CHARGE_FREE, $gate->chargeType);
    TestCase::assertSame(10, CreditManager::balance($userId), 'saldo tidak boleh berkurang');
});

TestCase::test('tanpa langganan, user memakai kuota gratis tier free lebih dulu', function (): void {
    // Sesuai model yang disepakati: user tanpa langganan aktif tidak diblokir,
    // ia jatuh ke tier free dengan 5 screening gratis per hari.
    $userId = makeUser('gate2@test.id', 10);

    $gate = UsageGate::open($userId, 'run_screening');
    $gate->commit();

    TestCase::assertSame(UsageGate::CHARGE_QUOTA, $gate->chargeType);
    TestCase::assertSame(0, $gate->creditsCharged);
    TestCase::assertSame(10, CreditManager::balance($userId), 'kuota gratis tidak menyentuh saldo');
});

TestCase::test('kuota free habis setelah 5 kali, sisanya memotong kredit', function (): void {
    $userId = makeUser('gate2b@test.id', 10);

    for ($i = 0; $i < 5; $i++) {
        UsageGate::open($userId, 'run_screening')->commit();
    }
    TestCase::assertSame(10, CreditManager::balance($userId), '5 screening pertama gratis');

    $gate = UsageGate::open($userId, 'run_screening');
    $gate->commit();

    TestCase::assertSame(UsageGate::CHARGE_CREDIT, $gate->chargeType);
    TestCase::assertSame(1, $gate->creditsCharged);
    TestCase::assertSame(9, CreditManager::balance($userId));
});

TestCase::test('detail emiten tidak pernah memakai kuota, selalu kredit', function (): void {
    // Kuota tier hanya berlaku untuk screening. Detail emiten harus tetap
    // berbayar walau kuota harian masih tersisa.
    $userId = makeUser('gate2c@test.id', 10);

    $gate = UsageGate::open($userId, 'view_stock_detail');
    $gate->commit();

    TestCase::assertSame(UsageGate::CHARGE_CREDIT, $gate->chargeType);
    TestCase::assertSame(8, CreditManager::balance($userId));
});

TestCase::test('rollback mengembalikan kredit yang sudah dipotong', function (): void {
    $userId = makeUser('gate3@test.id', 10);

    $gate = UsageGate::open($userId, 'view_stock_detail');
    TestCase::assertSame(8, CreditManager::balance($userId), 'kredit dipotong lebih dulu');

    $gate->rollback('Proses gagal');
    TestCase::assertSame(10, CreditManager::balance($userId), 'kredit harus kembali utuh');
});

TestCase::test('rollback setelah commit tidak menggandakan pengembalian', function (): void {
    $userId = makeUser('gate4@test.id', 10);

    $gate = UsageGate::open($userId, 'view_stock_detail');
    $gate->commit();
    $gate->rollback();
    $gate->rollback();

    TestCase::assertSame(8, CreditManager::balance($userId), 'commit bersifat final');
});

TestCase::test('aksi tak dikenal melempar UnknownActionException', function (): void {
    $userId = makeUser('gate5@test.id', 10);

    TestCase::assertThrows(
        UnknownActionException::class,
        static fn () => UsageGate::open($userId, 'aksi_belum_diseed')
    );
});

TestCase::test('kredit habis menghentikan screening setelah kuota gratis terpakai', function (): void {
    $userId = makeUser('gate6@test.id', 0);

    // 5 jatah gratis tier free tetap boleh jalan meski saldo nol.
    for ($i = 0; $i < 5; $i++) {
        UsageGate::open($userId, 'run_screening')->commit();
    }

    // Sesudah itu, tanpa saldo, gerbang harus menutup.
    TestCase::assertThrows(
        InsufficientCreditException::class,
        static fn () => UsageGate::open($userId, 'run_screening')
    );
});

TestCase::test('saldo nol langsung menghentikan aksi berbayar non-kuota', function (): void {
    $userId = makeUser('gate7@test.id', 0);

    TestCase::assertThrows(
        InsufficientCreditException::class,
        static fn () => UsageGate::open($userId, 'view_stock_detail')
    );
});
