<?php
// File: core/VendorClient/InvezgoClient.php
// PENTING: sama seperti DataSectorsClient — HANYA dipanggil dari /jobs/*.php.
// Endpoint user tidak boleh memanggil vendor langsung, hanya baca tabel lokal.

class InvezgoClient {
    private string $baseUrl;
    private string $apiKey;

    public function __construct() {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT base_url, api_key FROM data_vendors WHERE vendor_name = "Invezgo" AND is_active = 1');
        $stmt->execute();
        $vendor = $stmt->fetch();
        if (!$vendor) {
            throw new RuntimeException('Vendor Invezgo belum dikonfigurasi atau nonaktif');
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
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logUsage();

        if ($httpCode >= 400 && $httpCode !== 204) {
            throw new RuntimeException("Invezgo error [$httpCode]: $path");
        }
        return json_decode($response, true) ?? [];
    }

    private function logUsage(): void {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vendor_usage_log (vendor_id, usage_date, request_count)
             SELECT id, CURDATE(), 1 FROM data_vendors WHERE vendor_name = "Invezgo"
             ON DUPLICATE KEY UPDATE request_count = request_count + 1'
        );
        $stmt->execute();
    }

    public function stockList(): array {
        return $this->request('/analysis/list/stock');
    }

    public function information(string $code): array {
        return $this->request("/analysis/information/$code");
    }

    public function financialStatement(string $code, string $statement, string $type = 'FY', int $limit = 8): array {
        return $this->request("/analysis/financial-statement/$code", [
            'statement' => $statement, // BS, IS, CF
            'type' => $type,
            'limit' => $limit,
        ]);
    }

    public function keystat(string $code, string $type = 'FY', int $limit = 5): array {
        return $this->request("/analysis/keystat/$code", [
            'type' => $type,
            'limit' => $limit,
        ]);
    }

    public function shareholder(string $code): array {
        return $this->request("/analysis/shareholder/$code");
    }
}
