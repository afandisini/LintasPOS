<?php

declare(strict_types=1);

use App\Services\Database;
use System\Foundation\Application;
use System\Security\Csrf;
use System\View\Escaper;
use System\View\RawHtml;

if (!function_exists('app')) {
    function app(): Application
    {
        $app = Application::getInstance();
        if ($app === null) {
            throw new RuntimeException('Application not bootstrapped.');
        }
        return $app;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app()->config()->get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

if (!function_exists('brand_name')) {
    function brand_name(): string
    {
        return 'LintasPos';
    }
}

if (!function_exists('brand_locked')) {
    function brand_locked(): bool
    {
        return true;
    }
}

if (!function_exists('framework_credit')) {
    function framework_credit(): string
    {
        return 'AitiCore-Flex';
    }
}

if (!function_exists('enforce_brand_name')) {
    function enforce_brand_name(?string $candidate = null): string
    {
        $brand = brand_name();
        if (!brand_locked()) {
            return $candidate !== null && trim($candidate) !== '' ? trim($candidate) : $brand;
        }

        if ($candidate !== null) {
            $normalized = trim($candidate);
            if ($normalized !== '' && strcasecmp($normalized, $brand) !== 0) {
                $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $uri = (string) ($_SERVER['REQUEST_URI'] ?? '-');
                error_log('[BrandLock] blocked brand override to "' . $normalized . '" from ' . $ip . ' at ' . $uri);
            }
        }

        return $brand;
    }
}

if (!function_exists('view')) {
    /**
     * @param array<string, mixed> $data
     */
    function view(string $name, array $data = []): string
    {
        return app()->view()->render($name, $data);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return Escaper::escape($value);
    }
}

if (!function_exists('raw')) {
    function raw(string $html): RawHtml
    {
        return new RawHtml($html);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = e(csrf_token());
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $baseFromRequest = '';
        $isWebRequest = isset($_SERVER['HTTP_HOST']) && (string) $_SERVER['HTTP_HOST'] !== '';

        if ($isWebRequest) {
            $https = (string) ($_SERVER['HTTPS'] ?? '');
            $scheme = ($https === 'on' || $https === '1') ? 'https' : 'http';
            $host = (string) $_SERVER['HTTP_HOST'];
            // Use host root to avoid /public leakage in generated asset URLs.
            $baseFromRequest = $scheme . '://' . $host;
        }

        $configured = (string) config('app.url', (string) env('APP_URL', ''));
        $base = $baseFromRequest !== '' ? $baseFromRequest : $configured;
        $base = rtrim($base, '/');

        if ($base === '') {
            $base = 'http://127.0.0.1:8000';
        }

        if ($path === '') {
            return $base;
        }

        $url = $base . '/' . ltrim($path, '/');
        // Normalize accidental double slashes while preserving protocol (http://, https://).
        return (string) preg_replace('#(?<!:)/{2,}#', '/', $url);
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return base_url($path);
    }
}

if (!function_exists('avatar_url')) {
    function avatar_url(mixed $avatar): string
    {
        if (is_int($avatar) || (is_string($avatar) && ctype_digit(trim($avatar)))) {
            $fileId = (int) $avatar;
            if ($fileId > 0) {
                $relative = filemanager_path_by_id($fileId);
                if ($relative !== '') {
                    return site_url('media?path=' . urlencode($relative));
                }
            }
            return '';
        }

        $avatar = trim((string) $avatar);
        if ($avatar === '' || strtolower($avatar) === 'null' || $avatar === '0') {
            return '';
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        if (str_starts_with($avatar, 'filemanager/')) {
            $relative = ltrim($avatar, '/');
            $absolute = app()->basePath('storage/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
            if (is_file($absolute)) {
                return site_url('media?path=' . urlencode($relative));
            }

            return '';
        }

        $assetRelative = ltrim($avatar, '/');
        $assetAbsolute = app()->basePath('public/assets/img/' . str_replace('/', DIRECTORY_SEPARATOR, $assetRelative));
        if (is_file($assetAbsolute)) {
            return base_url('assets/img/' . $assetRelative);
        }

        return '';
    }
}

if (!function_exists('filemanager_path_by_id')) {
    function filemanager_path_by_id(int $fileId): string
    {
        if ($fileId <= 0) {
            return '';
        }

        static $cache = [];
        if (array_key_exists($fileId, $cache)) {
            return $cache[$fileId];
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT path FROM filemanager WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $fileId]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                $path = ltrim((string) ($row['path'] ?? ''), '/');
                if ($path !== '' && str_starts_with($path, 'filemanager/')) {
                    $cache[$fileId] = $path;
                    return $path;
                }
            }
        } catch (\Throwable) {
            // no-op
        }

        $cache[$fileId] = '';
        return '';
    }
}

if (!function_exists('avatar_initials')) {
    function avatar_initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $parts = array_values(array_filter($parts, static fn ($part): bool => $part !== ''));
        if (count($parts) >= 2) {
            $initials = substr((string) $parts[0], 0, 1) . substr((string) $parts[1], 0, 1);
            $initials = strtoupper($initials);
            return $initials !== '' ? $initials : 'U';
        }

        $single = (string) ($parts[0] ?? $name);
        $initials = strtoupper(substr($single, 0, 2));
        return $initials !== '' ? $initials : 'U';
    }
}

if (!function_exists('avatar_meta')) {
    /**
     * @return array{url: string, initials: string, has_image: bool}
     */
    function avatar_meta(mixed $avatar, ?string $name): array
    {
        $url = avatar_url($avatar);
        return [
            'url' => $url,
            'initials' => avatar_initials($name),
            'has_image' => $url !== '',
        ];
    }
}

if (!function_exists('store_profile')) {
    /**
     * @return array<string, string>
     */
    function store_profile(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $fallback = [
            'nama_toko' => enforce_brand_name(),
            'alamat_toko' => '',
            'tlp' => '',
            'nama_pemilik' => '',
            'logo' => '',
            'icons' => '',
            'logo_mode' => 'icon',
        ];

        try {
            if (!class_exists(Database::class)) {
                $cached = $fallback;
                return $cached;
            }

            $pdo = Database::connection();
            $row = null;
            try {
                $row = $pdo->query(
                    'SELECT nama_toko, alamat_toko, tlp, nama_pemilik, logo, icons, logo_mode FROM toko ORDER BY id ASC LIMIT 1'
                )->fetch();
            } catch (\Throwable) {
                try {
                    $row = $pdo->query(
                        'SELECT nama_toko, alamat_toko, tlp, nama_pemilik, logo, icons FROM toko ORDER BY id ASC LIMIT 1'
                    )->fetch();
                } catch (\Throwable) {
                    $row = $pdo->query(
                        'SELECT nama_toko, alamat_toko, tlp, nama_pemilik, logo FROM toko ORDER BY id ASC LIMIT 1'
                    )->fetch();
                }
            }

            if (!is_array($row)) {
                $cached = $fallback;
                return $cached;
            }

            $cached = [
                'nama_toko' => enforce_brand_name((string) ($row['nama_toko'] ?? '')),
                'alamat_toko' => (string) ($row['alamat_toko'] ?? ''),
                'tlp' => (string) ($row['tlp'] ?? ''),
                'nama_pemilik' => (string) ($row['nama_pemilik'] ?? ''),
                'logo' => (string) ($row['logo'] ?? ''),
                'icons' => (string) ($row['icons'] ?? ''),
                'logo_mode' => (string) ($row['logo_mode'] ?? 'icon'),
            ];
        } catch (\Throwable) {
            $cached = $fallback;
        }

        return $cached;
    }
}

if (!function_exists('toko')) {
    function toko(string $key, string $default = ''): string
    {
        $store = store_profile();
        return (string) ($store[$key] ?? $default);
    }
}

if (!function_exists('store_brand_logo_html')) {
    function store_brand_logo_html(): string
    {
        $mode = toko('logo_mode', 'icon');
        $logoId = toko('logo', '');
        $icons = toko('icons', '');
        $storeName = toko('nama_toko', brand_name());

        if ($mode === 'gambar' && $logoId !== '' && $logoId !== '0') {
            $relative = filemanager_path_by_id((int) $logoId);
            if ($relative !== '' && str_starts_with($relative, 'filemanager/toko/')) {
                $url = site_url('media/public?path=' . urlencode($relative));
                return '<img src="' . Escaper::escape($url) . '" alt="' . Escaper::escape($storeName) . '" style="height:32px;width:auto;object-fit:contain;">';
            }
        }

        if ($icons !== '') {
            return '<i class="' . Escaper::escape($icons) . '"></i>';
        }

        return '<span>' . Escaper::escape(avatar_initials($storeName)) . '</span>';
    }
}

if (!function_exists('store_placeholders')) {
    function store_placeholders(string $content): string
    {
        $replace = [
            '{{nama_toko}}' => brand_name(),
            '{{alamat}}' => toko('alamat_toko', ''),
            '{{alamat_toko}}' => toko('alamat_toko', ''),
            '{{telepon}}' => toko('tlp', ''),
            '{{tlp}}' => toko('tlp', ''),
            '{{pemilik}}' => toko('nama_pemilik', ''),
            '{{logo}}' => avatar_url(toko('logo', '')),
            '{{icons}}' => toko('icons', ''),
            '{{brand_logo}}' => store_brand_logo_html(),
        ];

        return strtr($content, $replace);
    }
}

if (!function_exists('toast_add')) {
    function toast_add(string $message, string $type = 'info'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $type = strtolower(trim($type));
        if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
            $type = 'info';
        }

        $queue = $_SESSION['_toast_queue'] ?? [];
        if (!is_array($queue)) {
            $queue = [];
        }

        $queue[] = [
            'message' => $message,
            'type' => $type,
        ];

        $_SESSION['_toast_queue'] = $queue;
    }
}

if (!function_exists('toast_consume')) {
    /**
     * @return array<int, array{message: string, type: string}>
     */
    function toast_consume(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }

        $queue = $_SESSION['_toast_queue'] ?? [];
        unset($_SESSION['_toast_queue']);

        if (!is_array($queue)) {
            return [];
        }

        $result = [];
        foreach ($queue as $item) {
            if (!is_array($item)) {
                continue;
            }
            $result[] = [
                'message' => (string) ($item['message'] ?? ''),
                'type' => (string) ($item['type'] ?? 'info'),
            ];
        }

        return $result;
    }
}

if (!function_exists('toast_payload_json')) {
    function toast_payload_json(): string
    {
        $payload = toast_consume();
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : '[]';
    }
}

if (!function_exists('module_script')) {
    function module_script(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || !preg_match('#^[A-Za-z0-9_./-]+$#', $relativePath)) {
            return '';
        }

        $modulesBase = app()->basePath('app/Modules');
        $modulesBaseReal = realpath($modulesBase);
        if ($modulesBaseReal === false) {
            return '';
        }

        $target = realpath($modulesBaseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($target === false || !is_file($target)) {
            return '';
        }

        $baseNorm = str_replace('\\', '/', $modulesBaseReal);
        $targetNorm = str_replace('\\', '/', $target);
        if (!str_starts_with($targetNorm, $baseNorm . '/')) {
            return '';
        }

        $content = file_get_contents($target);
        if (!is_string($content) || $content === '') {
            return '';
        }

        return '<script>' . "\n" . $content . "\n" . '</script>';
    }
}

if (!function_exists('helper_toast_script')) {
    function helper_toast_script(): string
    {
        return module_script('Helper/js/toast.js');
    }
}

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $value): string|false
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($normalized, true);
    }
}

if (!function_exists('signed_id_secret')) {
    function signed_id_secret(): string
    {
        $secret = trim((string) config('app.key', ''));
        if ($secret !== '') {
            return $secret;
        }

        $fallback = trim((string) env('APP_KEY', ''));
        if ($fallback !== '') {
            return $fallback;
        }

        return hash('sha256', (string) env('APP_URL', 'http://127.0.0.1:8000'));
    }
}

if (!function_exists('signed_id_encode')) {
    function signed_id_encode(int $id, int $ttlSeconds = 2_592_000): string
    {
        if ($id <= 0) {
            return '';
        }

        $issuedAt = time();
        $expiresAt = $ttlSeconds > 0 ? ($issuedAt + $ttlSeconds) : 0;
        $nonce = bin2hex(random_bytes(8));
        $payloadRaw = $id . ':' . $issuedAt . ':' . $expiresAt . ':' . $nonce;
        $payload = base64url_encode($payloadRaw);
        $signature = base64url_encode(hash_hmac('sha256', $payload, signed_id_secret(), true));

        return $payload . '.' . $signature;
    }
}

if (!function_exists('signed_id_decode')) {
    function signed_id_decode(string $token): ?int
    {
        $token = trim($token);
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }

        [$payload, $signature] = explode('.', $token, 2);
        if ($payload === '' || $signature === '') {
            return null;
        }

        $expected = base64url_encode(hash_hmac('sha256', $payload, signed_id_secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = base64url_decode($payload);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 4) {
            return null;
        }

        $id = (int) ($parts[0] ?? 0);
        $expiresAt = (int) ($parts[2] ?? 0);
        if ($id <= 0) {
            return null;
        }

        if ($expiresAt > 0 && time() > $expiresAt) {
            return null;
        }

        return $id;
    }
}

if (!function_exists('field_label_from_name')) {
    function field_label_from_name(string $fieldName): string
    {
        $name = trim($fieldName);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/[_\-]+/', ' ', $name);
        $name = is_string($name) ? trim(preg_replace('/\s+/', ' ', $name) ?? '') : '';
        if ($name === '') {
            return '';
        }

        return ucwords(strtolower($name));
    }
}

if (!function_exists('parseFieldDefinition')) {
    /**
     * Supported formats:
     * - field_name
     * - field_name[Custom Label]
     * - field_name[Custom Label|custom-css-class]
     *
     * @return array{name: string, label: string, class: string}
     */
    function parseFieldDefinition(string $input): array
    {
        $raw = trim($input);
        if ($raw === '') {
            return ['name' => '', 'label' => '', 'class' => ''];
        }

        $name = $raw;
        $meta = '';
        if (preg_match('/^\s*([^\[\]]+?)\s*(?:\[(.*)\])?\s*$/', $raw, $matches) === 1) {
            $name = trim((string) ($matches[1] ?? $raw));
            $meta = trim((string) ($matches[2] ?? ''));
        } else {
            $bracketPos = strpos($raw, '[');
            if ($bracketPos !== false) {
                $name = trim(substr($raw, 0, $bracketPos));
                $meta = trim(substr($raw, $bracketPos + 1), " \t\n\r\0\x0B]");
            }
        }

        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        $name = is_string($name) ? trim($name) : '';

        $label = '';
        $class = '';
        if ($meta !== '') {
            $parts = explode('|', $meta, 2);
            $label = trim((string) ($parts[0] ?? ''));
            $class = trim((string) ($parts[1] ?? ''));
        }

        if ($label === '') {
            $label = field_label_from_name($name);
        }

        if ($class !== '') {
            $class = preg_replace('/[^a-zA-Z0-9_\-\s:]/', '', $class);
            $class = is_string($class) ? trim($class) : '';
        }

        return [
            'name' => $name,
            'label' => $label,
            'class' => $class,
        ];
    }
}

if (!function_exists('parseFieldDefinitions')) {
    /**
     * @param array<int, mixed> $fields
     * @return array<int, array{name: string, label: string, class: string}>
     */
    function parseFieldDefinitions(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (!is_scalar($field)) {
                continue;
            }

            $parsed = parseFieldDefinition((string) $field);
            if ($parsed['name'] === '') {
                continue;
            }
            $result[] = $parsed;
        }

        return $result;
    }
}

if (!function_exists('format_date_id')) {
    function format_date_id(?string $value, bool $withTime = false): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Throwable) {
            return '-';
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $day = $date->format('d');
        $month = $months[(int) $date->format('n')] ?? $date->format('m');
        $year = $date->format('Y');
        $base = $day . ' ' . $month . ' ' . $year;

        return $withTime ? ($base . ' ' . $date->format('H:i')) : $base;
    }
}

if (!function_exists('format_currency_id')) {
    function format_currency_id(mixed $value, string $prefix = 'Rp'): string
    {
        if ($value === null || $value === '') {
            return $prefix . ' 0';
        }

        $number = is_numeric($value) ? (float) $value : 0.0;
        return $prefix . ' ' . number_format($number, 0, ',', '.');
    }
}

if (!function_exists('relation_label')) {
    function relation_label(mixed $value, string $fallback = '-'): string
    {
        if (is_array($value)) {
            foreach (['label', 'name', 'title', 'text', 'value'] as $key) {
                $candidate = trim((string) ($value[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            return $fallback;
        }

        $scalar = trim((string) $value);
        return $scalar !== '' ? $scalar : $fallback;
    }
}

if (!function_exists('render_image_cell')) {
    function render_image_cell(mixed $value, string $alt = 'Image'): string
    {
        $source = trim((string) $value);
        if ($source === '') {
            return '<span class="text-muted">-</span>';
        }

        if (ctype_digit($source)) {
            $relative = filemanager_path_by_id((int) $source);
            if ($relative !== '') {
                $source = site_url('media?path=' . urlencode($relative));
            }
        } elseif (str_starts_with($source, 'filemanager/')) {
            $source = site_url('media?path=' . urlencode(ltrim($source, '/')));
        } elseif (!str_starts_with($source, 'http://') && !str_starts_with($source, 'https://')) {
            $source = base_url(ltrim($source, '/'));
        }

        if ($source === '') {
            return '<span class="text-muted">-</span>';
        }

        return '<img src="' . e($source) . '" alt="' . e($alt) . '" style="width:44px;height:44px;object-fit:cover;border-radius:8px;">';
    }
}

if (!function_exists('render_file_cell')) {
    function render_file_cell(mixed $value, string $label = 'Lihat File'): string
    {
        $source = trim((string) $value);
        if ($source === '') {
            return '<span class="text-muted">-</span>';
        }

        if (ctype_digit($source)) {
            $relative = filemanager_path_by_id((int) $source);
            if ($relative !== '') {
                $source = site_url('media?path=' . urlencode($relative));
            }
        } elseif (str_starts_with($source, 'filemanager/')) {
            $source = site_url('media?path=' . urlencode(ltrim($source, '/')));
        } elseif (!str_starts_with($source, 'http://') && !str_starts_with($source, 'https://')) {
            $source = base_url(ltrim($source, '/'));
        }

        if ($source === '') {
            return '<span class="text-muted">-</span>';
        }

        return '<a href="' . e($source) . '" target="_blank" rel="noopener noreferrer" class="btn-g btn-sm">' . e($label) . '</a>';
    }
}

if (!function_exists('menu_generator_sidebar_items')) {
    /**
     * @return array<int, array{module_name: string, module_slug: string, menu_title: string, menu_icon: string, route_prefix: string, parent_menu_key: string, menu_order: int}>
     */
    function menu_generator_sidebar_items(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $cached = [];
        try {
            $pdo = Database::connection();
            $manualSlugs = [
                'transaksi',
                'transaksi-penjualan',
                'transaksi-pembelian',
                'keuangan',
                'keuangan-input',
                'laporan',
            ];
            $slugPlaceholders = implode(', ', array_fill(0, count($manualSlugs), '?'));
            $stmt = $pdo->prepare(
                'SELECT id, module_name, module_slug, menu_title, menu_icon, route_prefix, parent_menu_key, menu_order '
                . 'FROM menu_generator '
                . 'WHERE deleted_at IS NULL AND (status = \'generated\' OR module_slug IN (' . $slugPlaceholders . ')) '
                . 'ORDER BY menu_order ASC, id ASC'
            );
            $stmt->execute($manualSlugs);
            $rows = $stmt->fetchAll();
            if (!is_array($rows)) {
                return $cached;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $routePrefix = trim((string) ($row['route_prefix'] ?? ''), '/');
                if ($routePrefix === '' || !preg_match('/^[a-zA-Z0-9_\\/-]+$/', $routePrefix)) {
                    continue;
                }

                $moduleName = trim((string) ($row['module_name'] ?? ''));
                $moduleSlug = trim((string) ($row['module_slug'] ?? ''));
                $menuTitle = trim((string) ($row['menu_title'] ?? ''));
                $menuIcon = trim((string) ($row['menu_icon'] ?? 'bi bi-grid-3x3-gap-fill'));
                $parentMenuKey = trim((string) ($row['parent_menu_key'] ?? ''));
                $menuOrder = (int) ($row['menu_order'] ?? 0);
                if ($moduleName === '') {
                    $moduleName = ucwords(str_replace(['_', '-'], ' ', $routePrefix));
                }
                if ($moduleSlug === '') {
                    $moduleSlug = strtolower(str_replace('/', '-', $routePrefix));
                }
                if ($menuTitle === '') {
                    $menuTitle = $moduleName;
                }
                if ($menuIcon === '') {
                    $menuIcon = 'bi bi-grid-3x3-gap-fill';
                }
                if ($parentMenuKey === '') {
                    $parentMenuKey = 'modul-generator';
                }

                $cached[] = [
                    'module_name' => $moduleName,
                    'module_slug' => $moduleSlug,
                    'menu_title' => $menuTitle,
                    'menu_icon' => $menuIcon,
                    'route_prefix' => $routePrefix,
                    'parent_menu_key' => strtolower(substr($parentMenuKey, 0, 150)),
                    'menu_order' => $menuOrder,
                ];
            }
        } catch (\Throwable) {
            // no-op
        }

        return $cached;
    }
}

if (!function_exists('menu_generator_title_by_route')) {
    function menu_generator_title_by_route(string $routePrefix, string $fallback = ''): string
    {
        $routePrefix = trim(strtolower($routePrefix), '/');
        if ($routePrefix === '') {
            return $fallback;
        }

        static $cache = [];
        if (array_key_exists($routePrefix, $cache)) {
            $title = (string) ($cache[$routePrefix] ?? '');
            return $title !== '' ? $title : $fallback;
        }

        $cache[$routePrefix] = '';
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT menu_title, module_name '
                . 'FROM menu_generator '
                . 'WHERE deleted_at IS NULL AND route_prefix = :route_prefix '
                . 'ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['route_prefix' => $routePrefix]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                $title = trim((string) ($row['menu_title'] ?? ''));
                if ($title === '') {
                    $title = trim((string) ($row['module_name'] ?? ''));
                }
                if ($title !== '') {
                    $cache[$routePrefix] = $title;
                }
            }
        } catch (\Throwable) {
            // no-op
        }

        $title = (string) ($cache[$routePrefix] ?? '');
        return $title !== '' ? $title : $fallback;
    }
}
