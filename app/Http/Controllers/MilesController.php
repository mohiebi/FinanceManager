<?php

namespace App\Http\Controllers;

use App\Actions\Gamification\ActivityOverview;
use App\Actions\Gamification\AwardMilestones;
use App\Actions\Miles\MilesOverview;
use App\Enums\Feature;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MilesController extends Controller
{
    public function __invoke(
        Request $request,
        MilesOverview $overview,
        AwardMilestones $awardMilestones,
    ): Response {
        abort_unless(config('miles.ui_enabled'), 404);

        $history = $request->user()->mileLedgerEntries()
            ->latest('created_at')
            ->paginate(20)
            ->through(fn ($entry): array => [
                'id' => $entry->id,
                'amount' => $entry->amount,
                'balanceAfter' => $entry->balance_after,
                'reason' => $entry->reason->value,
                'createdAt' => $entry->created_at->toIso8601String(),
            ]);

        $awardMilestones->afterMilestoneScan($request->user());

        return Inertia::render('Miles/Index', [
            'overview' => $overview($request->user()),
            'activity' => $request->user()->hasFeature(Feature::Gamification) ? app(ActivityOverview::class)($request->user()) : null,
            'history' => $history,
        ]);
    }
}
