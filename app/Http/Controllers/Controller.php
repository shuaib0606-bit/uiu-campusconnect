<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Respond to an in-place (AJAX) action with JSON, or fall back to the
     * normal redirect-back for a plain form submission. Keeps every
     * toggle-style controller action usable from both the JS interaction
     * layer and a plain <form> without duplicating logic.
     */
    protected function respond(Request $request, array $data = [], ?string $flash = null)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['success' => true], $data));
        }

        return $flash ? back()->with('success', $flash) : back();
    }
}
