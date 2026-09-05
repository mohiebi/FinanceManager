<?php

namespace App\Http\Middleware;

use App\Actions\Miles\EnsureWelcomeMiles;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(private readonly EnsureWelcomeMiles $ensureWelcomeMiles) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $lastActiveAt = $user?->getRawOriginal('last_active_at');

        if ($user !== null && ($lastActiveAt === null || Carbon::parse($lastActiveAt)->lt(now()->subMinutes(5)))) {
            $trackedAt = now();

            $user->newQuery()
                ->whereKey($user->getKey())
                ->where(function ($query) use ($trackedAt): void {
                    $query->whereNull('last_active_at')
                        ->orWhere('last_active_at', '<', $trackedAt->copy()->subMinutes(5));
                })
                ->update(['last_active_at' => $trackedAt]);

            $user->setAttribute('last_active_at', $trackedAt);
        }

        if ($user !== null) {
            ($this->ensureWelcomeMiles)($user);
        }

        return $next($request);
    }
}
