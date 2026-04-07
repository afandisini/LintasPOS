<?php

declare(strict_types=1);

use System\Foundation\Env;
use System\Http\Request;
use System\Http\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
Env::load($basePath . DIRECTORY_SEPARATOR . '.env');
if (empty($_ENV)) {
    Env::load($basePath . DIRECTORY_SEPARATOR . '.env.example');
}

$logDir = $basePath . DIRECTORY_SEPARATOR . 'log';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'aiti_log.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

if (!is_file($logFile)) {
    touch($logFile);
}

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');
ini_set('error_log', $logFile);

$runtimeErrors = [];
$maxRuntimeErrors = 50;

$toBool = static function (string $value): bool {
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
};

$isDebug = static function () use ($toBool): bool {
    $raw = (string) ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false');
    return $toBool($raw);
};

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$appendRuntimeError = static function (
    string $type,
    string $message,
    ?string $file = null,
    ?int $line = null,
    ?string $trace = null
) use (&$runtimeErrors, $maxRuntimeErrors): void {
    if (count($runtimeErrors) >= $maxRuntimeErrors) {
        return;
    }

    $runtimeErrors[] = [
        'time' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'trace' => $trace,
    ];
};

$injectDebugPopup = static function (string $content, array $errors) use ($escapeHtml): string {
    if ($errors === []) {
        return $content;
    }

    $items = '';
    foreach ($errors as $index => $error) {
        $type = $escapeHtml((string) ($error['type'] ?? 'ERROR'));
        $message = $escapeHtml((string) ($error['message'] ?? 'Unknown error'));
        $time = $escapeHtml((string) ($error['time'] ?? ''));
        $file = $escapeHtml((string) ($error['file'] ?? '-'));
        $line = (int) ($error['line'] ?? 0);
        $trace = $escapeHtml((string) ($error['trace'] ?? ''));

        $location = $line > 0 ? $file . ':' . $line : $file;
        $items .= '<li style="margin:0 0 12px;">'
            . '<div style="font-weight:700;">#' . ($index + 1) . ' [' . $type . '] ' . $time . '</div>'
            . '<div style="margin-top:4px;">' . $message . '</div>'
            . '<div style="margin-top:4px;opacity:.8;">' . $location . '</div>';

        if ($trace !== '') {
            $items .= '<details style="margin-top:6px;"><summary>Trace</summary><pre style="white-space:pre-wrap;margin:8px 0 0;">'
                . $trace
                . '</pre></details>';
        }

        $items .= '</li>';
    }

    $popup = '<div id="aiti-error-trigger" style="position:fixed;top:12px;right:12px;z-index:2147483647;">'
        . '<button type="button" onclick="(function(){var p=document.getElementById(\'aiti-error-panel\');if(!p){return;}p.style.display=p.style.display===\'none\'?\'block\':\'none\';})();" '
        . 'style="background:#b91c1c;color:#fff;border:0;border-radius:999px;padding:8px 12px;font:600 12px/1.2 sans-serif;cursor:pointer;">'
        . 'Errors (' . count($errors) . ')</button></div>'
        . '<section id="aiti-error-panel" style="display:block;position:fixed;top:52px;right:12px;max-width:min(760px,calc(100vw - 24px));max-height:70vh;overflow:auto;z-index:2147483647;'
        . 'background:#111827;color:#f9fafb;border:1px solid #374151;border-radius:12px;padding:14px;box-shadow:0 20px 40px rgba(0,0,0,.35);font:13px/1.5 ui-monospace,Consolas,monospace;">'
        . '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px;">'
        . '<strong>Runtime Errors (Development Only)</strong>'
        . '<button type="button" onclick="document.getElementById(\'aiti-error-panel\').style.display=\'none\';" '
        . 'style="background:#1f2937;color:#f9fafb;border:1px solid #374151;border-radius:8px;padding:4px 8px;cursor:pointer;">Close</button>'
        . '</div><ol style="margin:0;padding-left:18px;">' . $items . '</ol></section>';

    if (stripos($content, '</body>') !== false) {
        return (string) preg_replace('/<\/body>/i', $popup . '</body>', $content, 1);
    }

    return $content . $popup;
};

$shouldInjectDebugPopup = static function (Response $response) use ($isDebug): bool {
    if (!$isDebug()) {
        return false;
    }

    $contentType = '';
    foreach ($response->headers() as $name => $value) {
        if (strtolower($name) === 'content-type') {
            $contentType = strtolower($value);
            break;
        }
    }

    if ($contentType === '') {
        $contentType = 'text/html';
    }

    return str_contains($contentType, 'text/html');
};

$decorateResponse = static function (Response $response) use (&$runtimeErrors, $injectDebugPopup, $shouldInjectDebugPopup): Response {
    if (!$shouldInjectDebugPopup($response) || $runtimeErrors === []) {
        return $response;
    }

    $content = $response->content();
    if (!str_contains(strtolower($content), '<html')) {
        return $response;
    }

    $patched = $injectDebugPopup($content, $runtimeErrors);
    return Response::html($patched, $response->statusCode(), $response->headers());
};

$requestSummary = static function (): string {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $uri = $_SERVER['REQUEST_URI'] ?? '-';
    return $method . ' ' . $uri;
};

$errorLevelLabel = static function (int $severity): string {
    return match ($severity) {
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        default => 'E_UNKNOWN',
    };
};

set_error_handler(static function (
    int $severity,
    string $message,
    string $file,
    int $line
) use ($requestSummary, $appendRuntimeError, $errorLevelLabel): bool {
    $type = $errorLevelLabel($severity);
    $appendRuntimeError($type, $message, $file, $line);

    $entry = sprintf(
        '[%s] ERROR (%d) %s in %s:%d | request=%s',
        date('Y-m-d H:i:s'),
        $severity,
        $message,
        $file,
        $line,
        $requestSummary()
    );
    error_log($entry);
    return false;
});

set_exception_handler(static function (\Throwable $exception) use ($requestSummary, $appendRuntimeError, $decorateResponse): void {
    $appendRuntimeError(
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    $entry = sprintf(
        "[%s] EXCEPTION %s: %s in %s:%d\nStack trace:\n%s\nRequest: %s",
        date('Y-m-d H:i:s'),
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString(),
        $requestSummary()
    );
    error_log($entry);

    if (!headers_sent()) {
        $response = Response::html('Internal Server Error', 500);
        $decorateResponse($response)->send();
    }
});

register_shutdown_function(static function () use ($requestSummary, $appendRuntimeError, $decorateResponse): void {
    $lastError = error_get_last();
    if ($lastError === null) {
        return;
    }

    $fatalLevels = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($lastError['type'], $fatalLevels, true)) {
        return;
    }

    $appendRuntimeError(
        'FATAL',
        (string) $lastError['message'],
        (string) $lastError['file'],
        (int) $lastError['line']
    );

    $entry = sprintf(
        '[%s] FATAL (%d) %s in %s:%d | request=%s',
        date('Y-m-d H:i:s'),
        $lastError['type'],
        $lastError['message'],
        $lastError['file'],
        $lastError['line'],
        $requestSummary()
    );
    error_log($entry);

    if (!headers_sent()) {
        $response = Response::html('Internal Server Error', 500);
        $decorateResponse($response)->send();
    }
});

try {
    $app = require $basePath . '/bootstrap/app.php';
    $kernel = $app->kernel();
    $response = $kernel->handle(Request::capture());
    $decorateResponse($response)->send();
} catch (\Throwable $exception) {
    $appendRuntimeError(
        'UNCAUGHT',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    error_log(sprintf(
        "[%s] UNCAUGHT %s: %s in %s:%d\nStack trace:\n%s\nRequest: %s",
        date('Y-m-d H:i:s'),
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString(),
        $requestSummary()
    ));

    $response = Response::html('Internal Server Error', 500);
    $decorateResponse($response)->send();
}
