<?php

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\ShareThemePreference;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            ShareThemePreference::class,
            ForcePasswordChange::class,
        ]);

        // The theme cookie is read by client-side JS, so it must stay unencrypted.
        $middleware->encryptCookies(except: [
            env('THEME_COOKIE_NAME', 'cc_theme'),
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
