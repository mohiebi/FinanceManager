<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Enums\McpProposalStatus;
use App\Exceptions\FeatureDisabledException;
use App\Mcp\Servers\FinanceServer;
use App\Mcp\Support\ProposalApplier;
use App\Mcp\Tools\Bills\ListBillsTool;
use App\Mcp\Tools\Investments\ListInvestmentsTool;
use App\Mcp\Tools\Investments\PortfolioSummaryTool;
use App\Mcp\Tools\Reports\SpendingSummaryTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use App\Models\McpProposal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('a tool whose module is off cannot be called', function () {
    $user = User::factory()->create();

    FinanceServer::actingAs($user)
        ->tool(ListBillsTool::class)
        ->assertHasErrors();
});

test('a tool whose module is on can be called', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    FinanceServer::actingAs($user)
        ->tool(ListBillsTool::class)
        ->assertOk();
});

test('core tools stay available with every optional module off', function () {
    // A brand-new user has every optional module switched off.
    $user = User::factory()->create();

    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class)
        ->assertOk();

    FinanceServer::actingAs($user)->tool(SpendingSummaryTool::class, [
        'from_date' => '2026-07-01',
        'to_date' => '2026-07-31',
    ])->assertOk();
});

test('each gated tool follows its own module', function () {
    $user = User::factory()->create();

    FinanceServer::actingAs($user)->tool(ListInvestmentsTool::class)->assertHasErrors();
    FinanceServer::actingAs($user)->tool(PortfolioSummaryTool::class)->assertHasErrors();

    // Enabling portfolio pulls investments in with it, so both open up.
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);
    Cache::flush();

    FinanceServer::actingAs($user->fresh())->tool(ListInvestmentsTool::class)->assertOk();
    FinanceServer::actingAs($user->fresh())->tool(PortfolioSummaryTool::class)->assertOk();
});

test('a proposal made before a module was switched off cannot be applied afterwards', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    $proposal = McpProposal::query()->create([
        'user_id' => $user->id,
        'resource_type' => 'bill',
        'action' => 'create',
        'status' => McpProposalStatus::Pending,
        'payload' => [
            'title' => 'Rent',
            'amount' => 5000000,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ],
        'diff_summary' => ['title' => 'Rent'],
        'expires_at' => now()->addMinutes(10),
    ]);

    app(UpdateUserFeature::class)($user, Feature::Bills, false);

    expect(fn () => app(ProposalApplier::class)->apply($proposal->fresh()))
        ->toThrow(FeatureDisabledException::class);

    expect($user->fresh()->bills()->count())->toBe(0);
});
