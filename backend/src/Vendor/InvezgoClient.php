<?php

declare(strict_types=1);

namespace Aigen\Vendor;

use Aigen\Core\Database;
use Aigen\Core\Logger;
use Aigen\Support\Crypto;

/**
 * Klien HTTP untuk vendor data Invezgo.
 *
 * ATURAN: hanya boleh dipanggil dari jobs/*.php. Endpoint yang melayani
 * pengguna membaca tabel lokal saja — kalau tidak, satu permintaan pengguna
 * bisa memicu panggilan vendor dan kuota harian habis oleh lalu lintas biasa.
 *
 * Konfigurasi (base_url, api_key, auth_type, kuota) dibaca dari tabel
 * data_vendors, bukan dari konstanta — sesuai prinsip NO HARDCODE, dan supaya
 * vendor bisa diganti tanpa deploy ulang.
 */
final class InvezgoClient
{
    public const VENDOR_NAME = 'Invezgo';

    private int $vendorId;
    private string $baseUrl;
    private string $apiKey;
    private string $authType;
    private ?int $dailyQuota;
    private int $timeout;

    /** Jeda antar permintaan supaya tidak membanjiri server vendor. */
    private int $throttleMicroseconds;

    private int $requestCount = 0;

    /**
     * Pengirim HTTP yang bisa diganti.
     *
     * Ada supaya alur permintaan — kuota, pencatatan pemakaian, penerjemahan
     * kode status — bisa diuji tanpa jaringan dan tanpa menghabiskan kuota
     * vendor sungguhan. Bawaannya tetap curl.
     *
     * @var (callable(string,array<int,string>,int):array{body:string|false,status:int,error:string})|null
     */
    private $transport = null;

    public function __construct(string $vendorName = self::VENDOR_NAME, int $timeout = 20)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, base_url, api_key, auth_type, daily_quota, is_active
               FROM data_vendors
              WHERE vendor_name = :name'
        );
        $stmt->execute(['name' => $vendorName]);
        $vendor = $stmt->fetch();

        if ($vendor === false) {
            throw new VendorException("Vendor '$vendorName' tidak ada di tabel data_vendors.");
        }
        if ((int) $vendor['is_active'] !== 1) {
            throw new VendorException("Vendor '$vendorName' berstatus nonaktif.");
        }

        $rawKey = (string) ($vendor['api_key'] ?? '');
        if (trim($rawKey) === '') {
            throw new VendorException(
                "API key vendor '$vendorName' masih kosong. Isi kolom data_vendors.api_key lebih dulu."
            );
        }

        if (!Crypto::isEncrypted($rawKey)) {
            Logger::warning('API key vendor tersimpan sebagai teks polos', ['vendor' => $vendorName]);
        }

        $this->vendorId   = (int) $vendor['id'];
        $this->baseUrl    = rtrim((string) $vendor['base_url'], '/');
        $this->apiKey     = Crypto::decrypt($rawKey);
        $this->authType   = (string) $vendor['auth_type'];
        $this->dailyQuota = $vendor['daily_quota'] !== null ? (int) $vendor['daily_quota'] : null;
        $this->timeout    = $timeout;

        $this->throttleMicroseconds = 150_000;
    }

    public function vendorId(): int
    {
        return $this->vendorId;
    }

    /**
     * Ganti pengirim HTTP (dipakai test) dan sekaligus matikan jeda antar
     * permintaan supaya suite tidak ikut menunggu.
     *
     * @param callable(string,array<int,string>,int):array{body:string|false,status:int,error:string} $transport
     */
    public function setTransport(callable $transport): void
    {
        $this->transport            = $transport;
        $this->throttleMicroseconds = 0;
    }

    public function requestCount(): int
    {
        return $this->requestCount;
    }

    /** Sisa kuota hari ini, atau null bila vendor tidak membatasi. */
    public function remainingQuota(): ?int
    {
        if ($this->dailyQuota === null) {
            return null;
        }
        return max(0, $this->dailyQuota - $this->usedToday());
    }

    private function usedToday(): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT request_count FROM vendor_usage_log
              WHERE vendor_id = :id AND usage_date = :d'
        );
        $stmt->execute(['id' => $this->vendorId, 'd' => date('Y-m-d')]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    // ---------------------------------------------------------------- endpoint

    /** @return array<int,array<string,mixed>> Daftar seluruh emiten. */
    public function stockList(): array
    {
        $data = $this->request('/analysis/list/stock');

        // Vendor kadang membungkus dalam {data: [...]}, kadang array telanjang.
        return $this->unwrapList($data);
    }

    /** @return array<string,mixed> Profil perusahaan. */
    public function information(string $code): array
    {
        return $this->unwrapObject($this->request('/analysis/information/' . rawurlencode($code)));
    }

    /**
     * Laporan keuangan mentah.
     *
     * @param string $statement BS (neraca), IS (laba rugi), atau CF (arus kas)
     */
    public function financialStatement(
        string $code,
        string $statement,
        string $type = 'FY',
        int $limit = 8,
    ): array {
        return $this->request('/analysis/financial-statement/' . rawurlencode($code), [
            'statement' => $statement,
            'type'      => $type,
            'limit'     => $limit,
        ]);
    }

    /** Rasio kunci (ROE, ROA, DER, PER, PBV, EPS, BVPS) per periode. */
    public function keystat(string $code, string $type = 'FY', int $limit = 5): array
    {
        return $this->request('/analysis/keystat/' . rawurlencode($code), [
            'type'  => $type,
            'limit' => $limit,
        ]);
    }

    /** @return array<int,array<string,mixed>> Komposisi pemegang saham. */
    public function shareholder(string $code): array
    {
        return $this->unwrapList($this->request('/analysis/shareholder/' . rawurlencode($code)));
    }

    // ----------------------------------------------------------------- interna

    /**
     * @param  array<string,scalar> $query
     * @return array<mixed>
     */
    private function request(string $path, array $query = []): array
    {
        $remaining = $this->remainingQuota();
        if ($remaining !== null && $remaining <= 0) {
            throw new VendorException(
                "Kuota harian vendor habis ($this->dailyQuota permintaan). Lanjutkan besok.",
                429,
                $path,
                true
            );
        }

        if ($this->requestCount > 0 && $this->throttleMicroseconds > 0) {
            usleep($this->throttleMicroseconds);
        }

        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $header = $this->authType === 'bearer'
            ? 'Authorization: Bearer ' . $this->apiKey
            : 'X-API-KEY: ' . $this->apiKey;

        $headers = [$header, 'Accept: application/json'];

        $send = $this->transport ?? self::curlTransport(...);

        /** @var array{body:string|false,status:int,error:string} $result */
        $result = $send($url, $headers, $this->timeout);

        $body    = $result['body'];
        $status  = $result['status'];
        $curlErr = $result['error'];

        $this->requestCount++;

        // Kegagalan koneksi TIDAK dihitung sebagai pemakaian kuota: vendor tidak
        // pernah menerima permintaannya. Versi lama mencatat semua percobaan,
        // sehingga angka vendor_usage_log ikut membengkak saat jaringan putus
        // dan kuota terlihat habis padahal belum terpakai.
        if ($body === false) {
            throw new VendorException("Gagal menghubungi vendor: $curlErr", 0, $path);
        }

        $this->logUsage();

        if ($status >= 400) {
            throw new VendorException(
                "Vendor membalas HTTP $status untuk $path" . $this->hint($status),
                $status,
                $path
            );
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new VendorException("Balasan vendor bukan JSON yang sah untuk $path", $status, $path);
        }

        return $decoded;
    }

    /**
     * @param  array<int,string> $headers
     * @return array{body:string|false,status:int,error:string}
     */
    private static function curlTransport(string $url, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);

        // Tanpa curl_close(): sejak PHP 8.0 fungsi itu tidak berefek apa-apa,
        // dan sejak 8.5 memanggilnya memicu peringatan deprecated. Handle-nya
        // dibersihkan sendiri saat $ch keluar dari cakupan.

        return [
            'body'   => is_string($body) ? $body : false,
            'status' => $status,
            'error'  => $error,
        ];
    }

    private function hint(int $status): string
    {
        return match (true) {
            $status === 401 => ' — API key ditolak. Periksa data_vendors.api_key.',
            $status === 402 => ' — langganan vendor kemungkinan kedaluwarsa.',
            $status === 403 => ' — akses ditolak untuk endpoint ini.',
            $status === 404 => ' — emiten atau endpoint tidak ditemukan.',
            $status === 429 => ' — terlalu banyak permintaan, coba lagi nanti.',
            $status >= 500  => ' — gangguan di sisi vendor, biasanya cukup diulang.',
            default         => '',
        };
    }

    private function logUsage(): void
    {
        $sql = Database::driver() === 'sqlite'
            ? 'INSERT INTO vendor_usage_log (vendor_id, usage_date, request_count)
               VALUES (:id, :d, 1)
               ON CONFLICT (vendor_id, usage_date)
               DO UPDATE SET request_count = request_count + 1'
            : 'INSERT INTO vendor_usage_log (vendor_id, usage_date, request_count)
               VALUES (:id, :d, 1)
               ON DUPLICATE KEY UPDATE request_count = request_count + 1';

        Database::connection()->prepare($sql)->execute([
            'id' => $this->vendorId,
            'd'  => date('Y-m-d'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function unwrapList(array $payload): array
    {
        foreach (['data', 'result', 'results', 'items', 'rows'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload = $payload[$key];
                break;
            }
        }

        return array_values(array_filter($payload, 'is_array'));
    }

    /** @return array<string,mixed> */
    private function unwrapObject(array $payload): array
    {
        foreach (['data', 'result'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }
}
