<?php

declare(strict_types=1);

namespace System\View;

class View
{
    public function __construct(private string $basePath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $file = $this->resolvePath($view);

        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        $variables = [];
        foreach ($data as $key => $value) {
            $variables[$key] = Escaper::wrap($value);
        }

        if (function_exists('store_profile')) {
            $store = (array) store_profile();
            $variables['nama_toko'] = Escaper::wrap((string) ($store['nama_toko'] ?? ''));
            $variables['alamat_toko'] = Escaper::wrap((string) ($store['alamat_toko'] ?? ''));
            $variables['alamat'] = Escaper::wrap((string) ($store['alamat_toko'] ?? ''));
            $variables['tlp'] = Escaper::wrap((string) ($store['tlp'] ?? ''));
            $variables['nama_pemilik'] = Escaper::wrap((string) ($store['nama_pemilik'] ?? ''));
            $variables['logo_toko'] = Escaper::wrap((string) ($store['logo'] ?? ''));
            $variables['icons_toko'] = Escaper::wrap((string) ($store['icons'] ?? ''));
        }

        extract($variables, EXTR_SKIP);
        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        if (function_exists('store_placeholders')) {
            $content = store_placeholders($content);
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderWithLayout(string $view, array $data, string $layout): string
    {
        $content = $this->render($view, $data);
        $layoutData = $data;
        $layoutData['content'] = new RawHtml($content);
        return $this->render($layout, $layoutData);
    }

    private function resolvePath(string $view): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
    }
}
