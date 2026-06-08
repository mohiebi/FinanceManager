<?php

namespace App\Http\Middleware;

use App\Support\FrontendLocalization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserPreferences
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(FrontendLocalization::normalizeLocale($request->user()?->locale));

        return $next($request);
    }
}
