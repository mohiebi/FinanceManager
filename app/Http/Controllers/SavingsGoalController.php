<?php

namespace App\Http\Controllers;

use App\Actions\Goals\SaveGoal;
use App\Models\SavingsGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavingsGoalController extends Controller
{
    public function __construct(private readonly SaveGoal $saveGoal) {}

    public function store(Request $request): RedirectResponse
    {
        $vaultArmed = $request->user()->vaultIsArmed();
        $validated = $request->validate(SaveGoal::rules($vaultArmed));

        $this->saveGoal->handle(
            $request->user(),
            SaveGoal::normalize($request->user(), $validated, $vaultArmed),
        );

        return back()->with('status', __('gamification.goals.saved'));
    }

    public function update(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authoriseOwnership($request, $savingsGoal);

        $vaultArmed = $request->user()->vaultIsArmed();
        $validated = $request->validate(SaveGoal::rules($vaultArmed));

        $this->saveGoal->handle(
            $request->user(),
            SaveGoal::normalize($request->user(), $validated, $vaultArmed, creating: false),
            $savingsGoal,
        );

        return back()->with('status', __('gamification.goals.saved'));
    }

    public function destroy(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authoriseOwnership($request, $savingsGoal);

        $savingsGoal->delete();

        return back()->with('status', __('gamification.goals.deleted'));
    }

    /**
     * Record that a goal has been met, for users whose server cannot see it.
     *
     * With the vault armed the target and the holdings are both ciphertext, so
     * only the browser knows a goal is finished — and without a date the
     * portfolio cannot tell a finish from last week apart from one from 2024.
     *
     * Write-once and idempotent: the date is never moved, so a stale tab
     * replaying this cannot rewrite history, and selling the asset afterwards
     * cannot un-achieve a goal the user genuinely finished.
     */
    public function markAchieved(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authoriseOwnership($request, $savingsGoal);

        if ($savingsGoal->achieved_on === null) {
            $savingsGoal->forceFill([
                'achieved_on' => $request->user()->localToday()->toDateString(),
            ])->save();
        }

        return back();
    }

    /**
     * 404 rather than 403 — a goal belonging to someone else should not be
     * confirmed to exist.
     */
    private function authoriseOwnership(Request $request, SavingsGoal $goal): void
    {
        abort_unless((int) $goal->user_id === (int) $request->user()->id, 404);
    }
}
