<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareThemePreference
{
    /**
     * Reads the theme cookie server-side so the very first response already
     * carries the right light/dark class — no flash of the wrong theme.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('app.theme_cookie_name');
        $theme = $request->cookie($cookieName, 'light');
        $theme = in_array($theme, ['light', 'dark'], true) ? $theme : 'light';

        View::share('activeTheme', $theme);

        return $next($request);
    }
}
