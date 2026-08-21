<?php
// File: core/VendorClient/DataSectorsClient.php
// PENTING: class ini HANYA boleh dipanggil dari /jobs/*.php (sync terjadwal/manual admin).
// Endpoint yang diakses user (api/fundamental/*, api/stocks/*) TIDAK BOLEH memanggil ini —
// mereka hanya baca dari tabel lokal (indicator_snapshot_fundamental, stocks, dll).

class DataSectorsClient {
    private string $baseUrl;
    private string $apiKey;

    public function __construct() {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT base_url, api_key FROM data_vendors WHERE vendor_name = "DataSectors" AND is_active = 1');
        $stmt->execute();
        $vendor = $stmt->fetch();
        if (!$vendor) {
            throw new RuntimeException('Vendor DataSectors belum dikonfigurasi atau nonaktif');
        }
        $this->baseUrl = rtrim($vendor['base_url'], '/');
        $this->apiKey = $vendor['api_key'] ?? '';
    }

    private function request(string $path, array $query = []): array {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logUsage();

        if ($httpCode >= 400) {
            throw new RuntimeException("DataSectors error [$httpCode]: $path");
        }
        return json_decode($response, true) ?? [];
    }

    private function logUsage(): void {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vendor_usage_log (vendor_id, usage_date, request_count)
             SELECT id, CURDATE(), 1 FROM data_vendors WHERE vendor_name = "DataSectors"
             ON DUPLICATE KEY UPDATE request_count = request_count + 1'
        );
        $stmt->execute();
    }

    public function keyRatios(string $symbol, string $market = 'id-id'): array {
        return $this->request('/api/stocks/v2/key-ratios', ['symbol' => $symbol, 'market' => $market]);
    }

    public function equities(string $symbol, string $market = 'id-id'): array {
        return $this->request('/api/stocks/v2/equities', ['symbol' => $symbol, 'market' => $market]);
    }

    public function insights(string $symbol, string $market = 'id-id'): array {
        return $this->request('/api/stocks/v2/insights', ['symbol' => $symbol, 'market' => $market]);
    }

    public function earnings(string $symbol, string $market = 'id-id'): array {
        return $this->request('/api/stocks/v2/earnings', ['symbol' => $symbol, 'market' => $market]);
    }
}
