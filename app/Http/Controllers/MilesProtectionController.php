<?php

namespace App\Http\Controllers;

use App\Actions\Miles\PurchaseStreakFreeze;
use App\Actions\Miles\RepairStreak;
use App\Http\Requests\RepairStreakRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilesProtectionController extends Controller
{
    public function storeFreeze(Request $request, PurchaseStreakFreeze $purchase): RedirectResponse
    {
        $purchase($request->user());

        return back()->with('success', __('Streak Freeze ready.'));
    }

    public function repair(RepairStreakRequest $request, RepairStreak $repair): RedirectResponse
    {
        $repair($request->user(), CarbonImmutable::parse($request->validated('date'), $request->user()->resolvedTimezone()));

        return back()->with('success', __('Flight Streak repaired.'));
    }
}
