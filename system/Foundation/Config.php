<?php

declare(strict_types=1);

namespace System\Foundation;

class Config
{
    /**
     * @var array<string, mixed>
     */
    private array $items;

    public function __construct()
    {
        $this->items = [
            'app' => [
                'name' => $this->env('APP_NAME', 'LintasPos'),
                'env' => $this->env('APP_ENV', 'production'),
                'debug' => $this->toBool($this->env('APP_DEBUG', 'false')),
                'url' => $this->env('APP_URL', 'http://127.0.0.1:8000'),
                'key' => $this->env('APP_KEY', ''),
            ],
            'session' => [
                'driver' => $this->env('SESSION_DRIVER', 'file'),
                'cookie' => $this->env('SESSION_COOKIE', 'aiticoreflex_session'),
                'samesite' => $this->env('SESSION_SAMESITE', 'Lax'),
                'secure' => $this->toBool($this->env('SESSION_SECURE', 'false')),
            ],
            'security' => [
                'csrf_enabled' => $this->toBool($this->env('CSRF_ENABLED', 'true')),
            ],
            'paths' => [
                'view' => $this->env('VIEW_PATH', 'app/Views'),
                'storage' => $this->env('STORAGE_PATH', 'storage'),
            ],
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function env(string $key, string $default): string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    private function toBool(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
