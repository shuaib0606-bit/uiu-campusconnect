<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Users created by the faculty bulk-import tool land with a temporary
     * password and must change it before touching anything else.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowed = ['settings.password', 'settings.password.update', 'logout'];

        if ($user && $user->must_change_password && ! in_array($request->route()?->getName(), $allowed, true)) {
            return redirect()->route('settings.password', ['force' => 1])
                ->with('warning', 'Please change your temporary password before continuing.');
        }

        return $next($request);
    }
}
