<?php

declare(strict_types=1);

namespace System\View;

final class Escaper
{
    public static function escape(mixed $value): string
    {
        if ($value instanceof RawHtml) {
            return (string) $value;
        }

        if ($value instanceof EscapedString) {
            return (string) $value;
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function wrap(mixed $value): mixed
    {
        if ($value instanceof RawHtml || $value instanceof EscapedString) {
            return $value;
        }

        if (is_array($value)) {
            $wrapped = [];
            foreach ($value as $key => $item) {
                $wrapped[$key] = self::wrap($item);
            }
            return $wrapped;
        }

        if (is_string($value)) {
            return new EscapedString($value);
        }

        return $value;
    }
}

final class EscapedString
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return htmlspecialchars($this->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

final class RawHtml
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
