<?php

use App\Models\User;

beforeEach(function () {
    config(['app.admin_email' => 'admin@example.com']);
});

test('only the configured administrator can export customers', function () {
    $this->get(route('admin.customers.export'))->assertRedirect(route('login'));

    $customer = User::factory()->create();
    $this->actingAs($customer)->get(route('admin.customers.export'))->assertForbidden();
});

test('the export streams the filtered directory as csv', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    User::factory()->create([
        'name' => 'Telegram Customer',
        'email' => 'telegram@example.com',
        'telegram_chat_id' => 'secret-chat-id',
        'signup_source' => 'producthunt',
    ]);
    User::factory()->create([
        'name' => 'Other Customer',
        'email' => 'other@example.com',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.customers.export', ['telegram' => 'connected']))
        ->assertOk()
        ->assertDownload('cashpilot-customers-'.now()->toDateString().'.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('telegram@example.com')
        ->toContain('producthunt')
        ->not->toContain('other@example.com')
        ->not->toContain('secret-chat-id');
});
