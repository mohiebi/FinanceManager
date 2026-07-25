<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind one or more feature modules.
 *
 * Applied by class reference with parameters — `EnsureFeatureEnabled::class.':bills'`
 * — which resolves correctly because a fully-qualified class name contains no colon.
 * The app registers no middleware aliases, so this deliberately doesn't add one.
 */
class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $user = $request->user();

        foreach ($features as $value) {
            $feature = Feature::from($value);

            if ($user !== null && $user->hasFeature($feature)) {
                continue;
            }

            $message = __('modules.locked', ['module' => $feature->label()]);

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            // A redirect, not a 403: this is a page the user can simply switch on,
            // so send them where they can do it.
            return redirect()->route('modules.edit')->with('status', $message);
        }

        return $next($request);
    }
}
