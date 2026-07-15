<?php

namespace App\Http\Middleware;

use App\Support\AcquisitionSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAcquisitionSource
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        AcquisitionSource::capture($request);

        return $next($request);
    }
}
