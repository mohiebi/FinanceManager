<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives browser-side diagnostics and writes them to the server log.
 *
 * Hydration mismatches, and the JavaScript errors that follow one, only ever
 * exist in the user's console — and Vue strips its hydration warnings from
 * production builds entirely. This endpoint is how that evidence reaches
 * `docker logs`, where it can be read next to the `[ssr]` lines the SSR worker
 * writes.
 *
 * Deliberately logged at error level: the container ships with LOG_LEVEL=error,
 * so anything quieter would be filtered out and the instrumentation would look
 * like it was working while recording nothing.
 */
class ClientDiagnosticsController extends Controller
{
    /** Keep one report from filling a log line with a whole stack dump. */
    private const MAX_FIELD_LENGTH = 2000;

    public function __invoke(Request $request): Response
    {
        if (! config('diagnostics.client_enabled')) {
            return response()->noContent(404);
        }

        $payload = $request->validate([
            'kind' => ['required', 'string', 'max:32'],
            'component' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'string', 'max:2000'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:4000'],
            'stack' => ['nullable', 'string', 'max:8000'],
            'ssrMarkup' => ['nullable', 'boolean'],
            'ssrChildren' => ['nullable', 'integer'],
        ]);

        Log::error('[client] '.$this->summarize($payload), [
            'user_id' => $request->user()?->id,
            'kind' => $payload['kind'],
            'component' => $payload['component'] ?? null,
            'url' => $payload['url'] ?? null,
            'referrer' => $payload['referrer'] ?? null,
            'ssr_markup' => $payload['ssrMarkup'] ?? null,
            'ssr_children' => $payload['ssrChildren'] ?? null,
            'message' => $this->clamp($payload['message'] ?? null),
            'stack' => $this->clamp($payload['stack'] ?? null),
            'user_agent' => substr((string) $request->userAgent(), 0, 300),
        ]);

        return response()->noContent();
    }

    /**
     * A single greppable line, so `docker logs | grep '\[client\]'` is readable
     * without expanding the context array on every entry.
     *
     * @param  array<string, mixed>  $payload
     */
    private function summarize(array $payload): string
    {
        $parts = [
            'kind='.$payload['kind'],
            'component='.($payload['component'] ?? '-'),
        ];

        if (array_key_exists('ssrMarkup', $payload) && $payload['ssrMarkup'] !== null) {
            $parts[] = 'ssr='.($payload['ssrMarkup'] ? 'yes' : 'NO');
            $parts[] = 'ssr_children='.($payload['ssrChildren'] ?? '-');
        }

        if (! blank($payload['referrer'] ?? null)) {
            $parts[] = 'referrer='.$payload['referrer'];
        }

        if (! blank($payload['message'] ?? null)) {
            $parts[] = 'msg='.str_replace("\n", ' ', substr($payload['message'], 0, 300));
        }

        return implode(' ', $parts);
    }

    private function clamp(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, self::MAX_FIELD_LENGTH);
    }
}
