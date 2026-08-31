<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ThemeController extends Controller
{
    /** Stores the light/dark choice in an unencrypted cookie for a year, so the server can render the theme. */
    public function __invoke(Request $request)
    {
        $theme = $request->string('theme')->value();
        $theme = in_array($theme, ['light', 'dark'], true) ? $theme : 'light';

        $cookie = Cookie::make(
            name: config('app.theme_cookie_name'),
            value: $theme,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: $request->secure(),
            httpOnly: false,
            sameSite: 'lax',
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'theme' => $theme])->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }
}
