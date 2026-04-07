<?php

declare(strict_types=1);

use System\Foundation\Application;
use System\Foundation\Config;
use System\Foundation\Env;
use System\Routing\Router;
use System\View\View;

$basePath = dirname(__DIR__);
$app = new Application($basePath);

Env::load($basePath . DIRECTORY_SEPARATOR . '.env');
if (empty($_ENV)) {
    Env::load($basePath . DIRECTORY_SEPARATOR . '.env.example');
}

$config = new Config();
$app->setConfig($config);

$router = new Router();
$app->setRouter($router);

$viewPath = $app->basePath((string) $config->get('paths.view', 'app/Views'));
$app->setView(new View($viewPath));

$app->setMiddlewareGroup('web', [
    App\Middleware\StartSession::class,
    App\Middleware\VerifyCsrfToken::class,
]);
$app->setMiddlewareGroup('api', []);

$storagePath = (string) $config->get('paths.storage', 'storage');
$isAbsolutePath = static function (string $path): bool {
    return str_starts_with($path, DIRECTORY_SEPARATOR)
        || str_starts_with($path, '\\\\')
        || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
};

$storageRoot = $isAbsolutePath($storagePath)
    ? $storagePath
    : $app->basePath($storagePath);
$routeCachePath = $storageRoot . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'routes.php';

$skipRouteCacheValue = strtolower((string) ($_ENV['AITI_SKIP_ROUTE_CACHE'] ?? ''));
$skipRouteCache = in_array($skipRouteCacheValue, ['1', 'true', 'yes', 'on'], true);

if (!$skipRouteCache && is_file($routeCachePath)) {
    $cachedRoutes = require $routeCachePath;
    if (is_array($cachedRoutes)) {
        $router->importCachedRoutes($cachedRoutes);
        return $app;
    }
}

$app->loadRoutesFrom($app->basePath('routes/web.php'), 'web');
$app->loadRoutesFrom($app->basePath('routes/api.php'), 'api');

return $app;
