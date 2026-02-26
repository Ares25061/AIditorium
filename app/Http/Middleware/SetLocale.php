<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Check if the request has a 'lang' parameter (GET parameter)
        //    Example: /api/courses?lang=ru
        if ($request->has('lang')) {
            $locale = $request->get('lang');
        }
        // 2. Check if the request has Accept-Language header
        //    Example: Accept-Language: ru
        elseif ($request->header('Accept-Language')) {
            $locale = $request->header('Accept-Language');
        }
        // 3. Otherwise use default from config
        else {
            $locale = config('app.locale'); // 'en' by default
        }

        // Check if the requested language is supported
        if (in_array($locale, ['en', 'ru'])) {
            app()->setLocale($locale); // Set the language!
        }

        // Continue to the controller
        return $next($request);
    }
}
