<?php

use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TelegramReportService;
use Carbon\Carbon;

test('telegram weekly report is readable and ascii only', function () {
    $user = User::factory()->create();
    $incomeCategory = Category::factory()->income()->forUser($user)->create(['name' => 'Salary']);
    $costCategory = Category::factory()->cost()->forUser($user)->create(['name' => 'Bills']);

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'amount' => 5000000,
            'currency' => Currency::Toman,
            'occurred_at' => '2026-06-03',
        ]);

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($costCategory)
        ->create([
            'amount' => 1000000,
            'currency' => Currency::Toman,
            'occurred_at' => '2026-06-04',
        ]);

    Investment::create([
        'user_id' => $user->id,
        'investment_asset_id' => InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail()->id,
        'asset_type' => AssetType::Gold->value,
        'quantity' => 1,
        'occurred_at' => '2026-06-05',
    ]);

    $report = app(TelegramReportService::class)->weekly($user, Carbon::parse('2026-06-06'));

    expect($report)->toContain('Weekly Report - Jun 6 week')
        ->toContain('Income: *5,000,000 T*')
        ->toContain('Costs: *1,000,000 T*')
        ->toContain('Net: *4,000,000 T*')
        ->toContain('- Bills: 1,000,000 T')
        ->toContain('- Gold: 1 g');

    expect(preg_match('/[^\x00-\x7F]/', $report))->toBe(0);
});

test('telegram monthly report follows the preferred jalali calendar', function () {
    $user = User::factory()->create(['calendar' => 'jalali']);
    $incomeCategory = Category::factory()->income()->forUser($user)->create(['name' => 'Salary']);

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'amount' => 1000000,
            'currency' => Currency::Toman,
            'occurred_at' => '2026-07-22',
        ]);

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'amount' => 2000000,
            'currency' => Currency::Toman,
            'occurred_at' => '2026-07-23',
        ]);

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'amount' => 3000000,
            'currency' => Currency::Toman,
            'occurred_at' => '2026-08-01',
        ]);

    $report = app(TelegramReportService::class)->monthly($user, Carbon::parse('2026-08-01'));

    expect($report)->toContain('Monthly Report - 1405-05')
        ->toContain('Income: *5,000,000 T*')
        ->toContain('Net: *5,000,000 T*')
        ->not->toContain('Income: *6,000,000 T*');
});
