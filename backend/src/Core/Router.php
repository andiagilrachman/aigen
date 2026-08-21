<?php

declare(strict_types=1);

namespace Aigen\Core;

/**
 * Router sederhana dengan dukungan parameter bergaya {nama}.
 *
 * Mengganti pendekatan lama "satu file PHP per endpoint" yang membuat setiap
 * file harus mengulang bootstrap, CORS, dan cek auth sendiri — dan itulah
 * sebabnya endpoint admin bisa lupa dipasangi guard.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:callable,middleware:array}> */
    private array $routes = [];

    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * Kelompokkan rute dengan prefix dan middleware bersama.
     * Semua rute admin didaftarkan lewat sini sehingga guard tidak mungkin
     * terlewat per-file.
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function add(string $method, string $path, callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => '/' . trim($this->groupPrefix . $path, '/'),
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): void
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $request->path);

            if ($params === null) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $request->method) {
                continue;
            }

            $request->setParams($params);

            foreach ($route['middleware'] as $middleware) {
                $middleware($request);
            }

            ($route['handler'])($request);
            return;
        }

        if ($pathMatched) {
            Response::error('Metode HTTP tidak diizinkan untuk endpoint ini', 405, 'method_not_allowed');
        }

        Response::error('Endpoint tidak ditemukan: ' . $request->path, 404, 'not_found');
    }

    /** @return array<string,string>|null */
    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }
        if (!str_contains($pattern, '{')) {
            return null;
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /** @return array<int,array{method:string,pattern:string}> Untuk endpoint dokumentasi. */
    public function list(): array
    {
        return array_map(
            static fn (array $r): array => ['method' => $r['method'], 'pattern' => $r['pattern']],
            $this->routes
        );
    }
}
