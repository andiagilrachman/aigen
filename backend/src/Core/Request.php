<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Pembungkus data request masuk.
 * Mendukung body JSON maupun form-urlencoded.
 */
final class Request
{
    private array $body;
    private array $query;
    private array $params = [];

    /** User terautentikasi, diisi oleh middleware auth. */
    private ?array $user = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        array $query = [],
        ?array $body = null
    ) {
        $this->query = $query;
        $this->body = $body ?? [];
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Buang prefix subfolder bila backend dipasang di /aigen-backend/public
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = '/' . trim($path, '/');

        return new self($method, $path, $_GET, self::parseBody());
    }

    private static function parseBody(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST ?: [];
    }

    /** Parameter dari pola rute, mis. /stocks/{code}. */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /** Diisi middleware auth supaya handler tidak perlu query ulang. */
    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    /** Id user yang sedang login. Hanya dipanggil di rute ber-middleware auth. */
    public function userId(): int
    {
        return (int) ($this->user['id'] ?? 0);
    }

    public function param(string $key, ?string $default = null): ?string
    {
        $value = $this->params[$key] ?? $default;
        return $value === null ? null : (string) $value;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function int(string $key, ?int $default = null): ?int
    {
        $value = $this->input($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, ?float $default = null): ?float
    {
        $value = $this->input($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return is_numeric($value) ? (float) $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }
}
