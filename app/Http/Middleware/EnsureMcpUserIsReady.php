<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the MCP endpoint the same way the web app gates its pages: the
 * authenticated user must have a verified email and a completed profile.
 * Returns JSON errors (not redirects) since MCP clients are not browsers.
 */
class EnsureMcpUserIsReady
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'forbidden',
                'error_description' => 'Your email address must be verified before AI assistants can access your account.',
            ], 403);
        }

        if ($user->requiresProfileCompletion()) {
            return response()->json([
                'error' => 'forbidden',
                'error_description' => 'Complete your CashPilot profile before connecting AI assistants.',
            ], 403);
        }

        return $next($request);
    }
}
