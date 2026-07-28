<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Vault\ArmVault;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\VaultEnableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Owns the vault's on/off switch.
 *
 * Deliberately separate from the modules endpoint: arming the vault requires the
 * browser to wrap the data key first, and a generic toggle would destroy the
 * server's only copy with nothing wrapped in its place.
 */
class VaultController extends Controller
{
    public function __construct(private readonly ArmVault $armVault) {}

    /**
     * Hand the browser the data key, once, so it can wrap it.
     *
     * Password-confirmed by middleware. Logged as a security event, because this
     * is the one moment a server that is about to become blind can still see.
     */
    public function enroll(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $dek = $this->armVault->enroll($user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        logger()->info('Vault enrollment issued', [
            'user_id' => $user->getKey(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['dek' => $dek]);
    }

    public function enable(VaultEnableRequest $request): RedirectResponse
    {
        try {
            $this->armVault->arm($request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['vault' => $exception->getMessage()]);
        }

        logger()->info('Vault armed', [
            'user_id' => $request->user()->getKey(),
            'ip' => $request->ip(),
        ]);

        return back()->with('status', __('settings.security.vault.armed_status'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // The browser hands the key back; the server cannot recover it alone.
            'dek' => ['required', 'string', 'max:128'],
            'confirmed' => ['required', 'accepted'],
        ]);

        try {
            $this->armVault->disarm($request->user(), $validated['dek']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['vault' => $exception->getMessage()]);
        }

        logger()->info('Vault disarmed', [
            'user_id' => $request->user()->getKey(),
            'ip' => $request->ip(),
        ]);

        return back()->with('status', __('settings.security.vault.disarmed_status'));
    }
}
