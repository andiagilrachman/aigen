<?php

declare(strict_types=1);

namespace Aigen\Support;

use Aigen\Core\App;
use RuntimeException;

/**
 * Enkripsi simetris untuk rahasia yang harus tersimpan di database dan bisa
 * dibaca kembali — saat ini hanya `data_vendors.api_key`.
 *
 * Password TIDAK memakai kelas ini. Password di-hash satu arah (password_hash)
 * karena tidak pernah perlu dibaca ulang; API key sebaliknya harus bisa
 * didekripsi untuk dikirim ke vendor.
 *
 * AES-256-GCM dipilih karena menyertakan tag autentikasi: ciphertext yang
 * diubah orang lain akan ditolak saat dekripsi, bukan menghasilkan sampah yang
 * diam-diam terkirim sebagai API key.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:v1:';

    /** Turunkan kunci 32 byte dari APP_KEY. */
    private static function key(): string
    {
        $appKey = (string) App::config('security.app_key', '');

        if ($appKey === '') {
            throw new RuntimeException(
                'APP_KEY belum diisi. Hasilkan dengan: php -r "echo base64_encode(random_bytes(32));"'
            );
        }

        // APP_KEY biasanya base64 dari 32 byte acak. Kalau bukan, hash dulu
        // supaya panjangnya tetap pas dan tidak menolak kunci buatan tangan.
        $decoded = base64_decode($appKey, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        return hash('sha256', $appKey, true);
    }

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Enkripsi gagal.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Kebalikan encrypt().
     *
     * Nilai tanpa prefix dianggap teks polos dan dikembalikan apa adanya:
     * kolom api_key sempat diisi manual lewat phpMyAdmin saat pengembangan, dan
     * memaksa dekripsi hanya akan membuat job gagal dengan pesan yang
     * membingungkan. isEncrypted() tersedia untuk memperingatkan operator.
     */
    public static function decrypt(string $value): string
    {
        if (!self::isEncrypted($value)) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Ciphertext rusak atau terpotong.');
        }

        $iv         = substr($raw, 0, 12);
        $tag        = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Dekripsi api_key gagal. APP_KEY kemungkinan berbeda dengan saat kunci disimpan.'
            );
        }

        return $plaintext;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
