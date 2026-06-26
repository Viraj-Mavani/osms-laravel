<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('safe_route')) {
    /**
     * Resolve a named route, or return a fallback if it isn't registered yet.
     * Lets shared UI (sidebar, dashboard) reference module routes that may be
     * added in a later build phase without throwing.
     */
    function safe_route(string $name, mixed $parameters = [], string $fallback = '#'): string
    {
        return Route::has($name) ? route($name, $parameters) : $fallback;
    }
}
