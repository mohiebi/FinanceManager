<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes that cannot work without a server that can read the data.
 *
 * Spreadsheet import and export are generated server-side: the import would have
 * to read every plaintext amount off the uploaded file and encrypt it with a key
 * it no longer has, and the export would have to decrypt. Neither is possible,
 * and doing them badly would mean plaintext passing through the server — exactly
 * what the vault exists to prevent.
 *
 * These are gated rather than removed, because they come straight back if the
 * user leaves the vault.
 */
class RejectWhenVaultArmed
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->vaultIsArmed()) {
            $message = __('settings.security.vault.unavailable');

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            return back()->with('status', $message);
        }

        return $next($request);
    }
}
