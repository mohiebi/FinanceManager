<?php

namespace App\Mcp\Tools\Bills;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the user\'s bills with their pending (unpaid) occurrences and next due dates. Bills are either monthly (recurring on a day of the month) or one-time (single due date).')]
class ListBillsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'only_active' => ['nullable', 'boolean'],
        ]);

        // Bill amounts are encrypted at rest, so all shaping happens in PHP.
        $bills = $user->bills()
            ->with(['category', 'occurrences' => fn ($query) => $query->whereNull('paid_at')->orderBy('due_date')])
            ->when($validated['only_active'] ?? true, fn ($query) => $query->where('is_active', true))
            ->get()
            ->map(function (Bill $bill): array {
                $next = $bill->occurrences->first();

                return [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'amount' => (float) $bill->amount,
                    'currency' => $bill->currency,
                    'recurrence_type' => $bill->recurrence_type->value,
                    'due_day_of_month' => $bill->due_day_of_month,
                    'due_date' => $bill->due_date?->toDateString(),
                    'category' => $bill->category?->name,
                    'is_active' => $bill->is_active,
                    'next_due_date' => $next?->due_date->toDateString(),
                    'pending_occurrences' => $bill->occurrences
                        ->map(fn ($occurrence): array => [
                            'id' => $occurrence->id,
                            'due_date' => $occurrence->due_date->toDateString(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy(fn (array $bill): string => $bill['next_due_date'] ?? '9999-12-31')
            ->values();

        return Response::structured(['bills' => $bills->all()]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'only_active' => $schema->boolean()->description('When true (default), only active bills are returned.'),
        ];
    }
}
