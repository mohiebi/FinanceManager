<?php

use App\Enums\McpProposalStatus;
use App\Mcp\Servers\FinanceServer;
use App\Mcp\Support\ProposalService;
use App\Mcp\Tools\Bills\ProposeBillTool;
use App\Mcp\Tools\Bills\ProposePayBillTool;
use App\Mcp\Tools\Investments\ProposeCustomAssetTool;
use App\Mcp\Tools\Proposals\ConfirmProposalTool;
use App\Mcp\Tools\Proposals\ListPendingProposalsTool;
use App\Mcp\Tools\Proposals\RejectProposalTool;
use App\Mcp\Tools\Transactions\ProposeCategoryTool;
use App\Mcp\Tools\Transactions\ProposeTransactionTool;
use App\Models\Category;
use App\Models\McpProposal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;

test('a proposal records the OAuth client attached to the Passport token', function () {
    $user = User::factory()->withModules()->create();
    $client = Client::query()->forceCreate([
        'name' => 'Codex',
        'secret' => null,
        'provider' => null,
        'redirect_uris' => ['http://localhost/callback'],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'revoked' => false,
    ]);

    $user->withAccessToken(new AccessToken([
        'oauth_client_id' => (string) $client->getKey(),
    ]));

    $proposal = app(ProposalService::class)->propose(
        $user,
        'create',
        'transaction',
        null,
        ['title' => 'Coffee beans'],
        ['title' => ['old' => null, 'new' => 'Coffee beans']],
    );

    expect($proposal->oauth_client_id)->toBe((string) $client->getKey())
        ->and($proposal->client_name)->toBe('Codex');
});

test('proposing a transaction stores a pending proposal and writes nothing', function () {
    $user = User::factory()->withModules()->create();
    $category = Category::factory()->cost()->create();

    FinanceServer::actingAs($user)
        ->tool(ProposeTransactionTool::class, [
            'action' => 'create',
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => 250,
            'currency' => 'toman',
            'title' => 'Coffee beans',
            'occurred_at' => '2026-07-20',
        ])
        ->assertOk()
        ->assertSee('proposal_id');

    expect(Transaction::query()->count())->toBe(0)
        ->and(McpProposal::query()->count())->toBe(1);

    $proposal = McpProposal::query()->first();
    expect($proposal->status)->toBe(McpProposalStatus::Pending)
        ->and($proposal->user_id)->toBe($user->id)
        ->and($proposal->payload['title'])->toBe('Coffee beans');
});

test('proposal payload is encrypted at rest', function () {
    $user = User::factory()->withModules()->create();
    $category = Category::factory()->cost()->create();

    FinanceServer::actingAs($user)
        ->tool(ProposeTransactionTool::class, [
            'action' => 'create',
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => 999,
            'currency' => 'toman',
            'title' => 'SensitivePurchase',
            'occurred_at' => '2026-07-20',
        ])
        ->assertOk();

    $raw = DB::table('mcp_proposals')->first();

    expect($raw->payload)->not->toContain('SensitivePurchase')
        ->and($raw->diff_summary)->not->toContain('SensitivePurchase');
});

test('confirming a proposal applies it exactly once', function () {
    $user = User::factory()->withModules()->create();
    $category = Category::factory()->cost()->create();

    FinanceServer::actingAs($user)->tool(ProposeTransactionTool::class, [
        'action' => 'create',
        'type' => 'cost',
        'category_id' => $category->id,
        'amount' => 250,
        'currency' => 'toman',
        'title' => 'Coffee beans',
        'occurred_at' => '2026-07-20',
    ])->assertOk();

    $proposal = McpProposal::query()->first();

    FinanceServer::actingAs($user)
        ->tool(ConfirmProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertOk()
        ->assertSee('confirmed');

    expect(Transaction::query()->count())->toBe(1)
        ->and($user->transactions()->first()->title)->toBe('Coffee beans')
        ->and($proposal->fresh()->status)->toBe(McpProposalStatus::Confirmed);

    // Replaying the same confirmation must not create a second transaction.
    FinanceServer::actingAs($user)
        ->tool(ConfirmProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertHasErrors();

    expect(Transaction::query()->count())->toBe(1);
});

test('rejecting a proposal records it and never applies', function () {
    $user = User::factory()->withModules()->create();
    $proposal = McpProposal::factory()->create(['user_id' => $user->id]);

    FinanceServer::actingAs($user)
        ->tool(RejectProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertOk()
        ->assertSee('rejected');

    expect($proposal->fresh()->status)->toBe(McpProposalStatus::Rejected)
        ->and(Transaction::query()->count())->toBe(0);

    FinanceServer::actingAs($user)
        ->tool(ConfirmProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertHasErrors();
});

test('expired proposals cannot be confirmed', function () {
    $user = User::factory()->withModules()->create();
    $proposal = McpProposal::factory()->expired()->create(['user_id' => $user->id]);

    FinanceServer::actingAs($user)
        ->tool(ConfirmProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertHasErrors();

    expect($proposal->fresh()->status)->toBe(McpProposalStatus::Expired);
});

test('users cannot confirm or reject another user\'s proposals', function () {
    $userA = User::factory()->withModules()->create();
    $userB = User::factory()->withModules()->create();
    $proposal = McpProposal::factory()->create(['user_id' => $userA->id]);

    FinanceServer::actingAs($userB)
        ->tool(ConfirmProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertHasErrors();

    FinanceServer::actingAs($userB)
        ->tool(RejectProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertHasErrors();

    expect($proposal->fresh()->status)->toBe(McpProposalStatus::Pending);
});

test('proposing an update to another user\'s transaction fails', function () {
    $userA = User::factory()->withModules()->create();
    $userB = User::factory()->withModules()->create();
    $transaction = Transaction::factory()->cost()->create(['user_id' => $userA->id]);

    FinanceServer::actingAs($userB)
        ->tool(ProposeTransactionTool::class, [
            'action' => 'delete',
            'transaction_id' => $transaction->id,
        ])
        ->assertHasErrors();
});

test('a category type mismatch is rejected at proposal time', function () {
    $user = User::factory()->withModules()->create();
    $incomeCategory = Category::factory()->income()->create();

    FinanceServer::actingAs($user)
        ->tool(ProposeTransactionTool::class, [
            'action' => 'create',
            'type' => 'cost',
            'category_id' => $incomeCategory->id,
            'amount' => 100,
            'currency' => 'toman',
            'title' => 'Wrong category',
            'occurred_at' => '2026-07-20',
        ])
        ->assertHasErrors();
});

test('confirming a bill payment marks the occurrence paid and creates the cost transaction', function () {
    $user = User::factory()->withModules()->create();

    $bill = $user->bills()->create([
        'title' => 'Rent',
        'amount' => 5000,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 5,
    ]);
    $occurrence = $bill->occurrences()->create(['due_date' => now()->toDateString()]);

    FinanceServer::actingAs($user)
        ->tool(ProposePayBillTool::class, ['bill_id' => $bill->id])
        ->assertOk();

    $proposal = McpProposal::query()->first();

    FinanceServer::actingAs($user)
        ->tool(ConfirmProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertOk();

    expect($occurrence->fresh()->isPaid())->toBeTrue()
        ->and($user->transactions()->count())->toBe(1)
        ->and($user->transactions()->first()->title)->toBe('Rent');
});

test('confirming a bill creation generates its initial occurrence', function () {
    $user = User::factory()->withModules()->create();

    FinanceServer::actingAs($user)
        ->tool(ProposeBillTool::class, [
            'action' => 'create',
            'title' => 'Internet',
            'amount' => 1200,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 10,
        ])
        ->assertOk();

    $proposal = McpProposal::query()->first();

    FinanceServer::actingAs($user)
        ->tool(ConfirmProposalTool::class, ['proposal_id' => $proposal->id])
        ->assertOk();

    $bill = $user->bills()->first();
    expect($bill)->not->toBeNull()
        ->and($bill->title)->toBe('Internet')
        ->and($bill->occurrences()->count())->toBe(1);
});

test('custom assets cannot be proposed with URL price sources', function () {
    $user = User::factory()->withModules()->create();

    FinanceServer::actingAs($user)
        ->tool(ProposeCustomAssetTool::class, [
            'name' => 'Sneaky asset',
            'unit' => 'units',
            'price_source_type' => 'json',
        ])
        ->assertHasErrors();
});

test('duplicate categories are rejected at proposal time', function () {
    $user = User::factory()->withModules()->create();
    Category::factory()->cost()->create(['name' => 'Food']);

    FinanceServer::actingAs($user)
        ->tool(ProposeCategoryTool::class, [
            'type' => 'cost',
            'name' => 'Food',
        ])
        ->assertHasErrors();
});

test('list-pending-proposals shows only the user\'s unexpired pending proposals', function () {
    $user = User::factory()->withModules()->create();
    $other = User::factory()->withModules()->create();

    $pending = McpProposal::factory()->create(['user_id' => $user->id]);
    McpProposal::factory()->expired()->create(['user_id' => $user->id]);
    McpProposal::factory()->confirmed()->create(['user_id' => $user->id]);
    McpProposal::factory()->create(['user_id' => $other->id]);

    FinanceServer::actingAs($user)
        ->tool(ListPendingProposalsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->count('proposals', 1)
            ->where('proposals.0.proposal_id', $pending->id)
            ->etc());
});
