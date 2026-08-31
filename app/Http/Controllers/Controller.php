<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Answers an AJAX action with JSON, or redirects back for a plain form submission. */
    protected function respond(Request $request, array $data = [], ?string $flash = null)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['success' => true], $data));
        }

        return $flash ? back()->with('success', $flash) : back();
    }
}
