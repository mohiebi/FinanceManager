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
     * 404 rather than 403 — a goal belonging to someone else should not be
     * confirmed to exist.
     */
    private function authoriseOwnership(Request $request, SavingsGoal $goal): void
    {
        abort_unless((int) $goal->user_id === (int) $request->user()->id, 404);
    }
}
