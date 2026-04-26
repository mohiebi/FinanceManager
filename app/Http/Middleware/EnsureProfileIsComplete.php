<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->requiresProfileCompletion()) {
            return redirect()
                ->route('profile.edit')
                ->with('status', 'Complete your profile to continue.');
        }

        return $next($request);
    }
}
