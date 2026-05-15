<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'webhooks/github',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// PHP 8.5 deprecates PDO::MYSQL_ATTR_SSL_CA in Laravel's merged framework config.
// Skip merging framework defaults on 8.5+ so vendor config/database.php is not evaluated.
if (PHP_VERSION_ID >= 80500) {
    $app->dontMergeFrameworkConfiguration();
}

return $app;
