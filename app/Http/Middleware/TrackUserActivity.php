<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
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

        return $next($request);
    }
}
