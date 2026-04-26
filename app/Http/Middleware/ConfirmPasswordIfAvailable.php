<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class ConfirmPasswordIfAvailable
{
    public function __construct(
        private readonly ResponseFactory $responseFactory,
        private readonly UrlGenerator $urlGenerator,
        private readonly ?int $passwordTimeout = null,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null, string|int|null $passwordTimeoutSeconds = null): Response
    {
        if (! $request->user()?->hasPassword()) {
            return $next($request);
        }

        $confirmedAt = Date::now()->unix() - $request->session()->get('auth.password_confirmed_at', 0);

        if ($confirmedAt > ($passwordTimeoutSeconds ?? $this->passwordTimeout ?? 10800)) {
            if ($request->expectsJson()) {
                return $this->responseFactory->json([
                    'message' => 'Password confirmation required.',
                ], 423);
            }

            return $this->responseFactory->redirectGuest(
                $this->urlGenerator->route($redirectToRoute ?: 'password.confirm')
            );
        }

        return $next($request);
    }
}
