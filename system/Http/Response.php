<?php

declare(strict_types=1);

namespace System\Http;

class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8']
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $content, int $statusCode = 200, array $headers = []): self
    {
        return new self($content, $statusCode, array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers));
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $statusCode = 200, array $headers = []): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE),
            $statusCode,
            array_merge(['Content-Type' => 'application/json'], $headers)
        );
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->content;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function withoutBody(): self
    {
        return new self('', $this->statusCode, $this->headers);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
