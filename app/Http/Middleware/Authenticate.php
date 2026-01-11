<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        Log::info('########### App\Http\Middleware\Authenticate');
        if (! $request->expectsJson()) {
            Log::info('########## $request->expectsJson() = true');
            return route('login');
        }
        Log::info('########## $request->expectsJson() = false');
    }
}
