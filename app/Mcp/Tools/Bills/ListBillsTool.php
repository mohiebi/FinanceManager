<?php

namespace App\Mcp\Tools\Bills;

use App\Enums\Feature;
use App\Mcp\Concerns\RequiresFeature;
use App\Models\Bill;
use App\Models\User;
use App\Support\CalendarDates;
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
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Bills];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'only_active' => ['nullable', 'boolean'],
        ]);

        $isJalali = CalendarDates::isJalaliUser($user);

        // Bill amounts are encrypted at rest, so all shaping happens in PHP.
        $bills = $user->bills()
            ->with(['category', 'occurrences' => fn ($query) => $query->whereNull('paid_at')->orderBy('due_date')])
            ->withCount(['occurrences as paid_occurrence_count' => fn ($query) => $query->whereNotNull('paid_at')])
            ->when($validated['only_active'] ?? true, fn ($query) => $query->where('is_active', true))
            ->get()
            ->map(function (Bill $bill) use ($isJalali): array {
                $next = $bill->occurrences->first();

                return [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'amount' => (float) $bill->amount,
                    'currency' => $bill->currency,
                    'recurrence_type' => $bill->recurrence_type->value,
                    'due_day_of_month' => $bill->due_day_of_month,
                    'due_date' => $bill->due_date?->toDateString(),
                    'recurrence_limit_type' => $bill->recurrence_limit_type?->value,
                    'recurrence_count' => $bill->recurrence_count,
                    'recurrence_end_date' => $bill->recurrence_end_date?->toDateString(),
                    'payments_made' => (int) $bill->paid_occurrence_count,
                    'category' => $bill->category?->name,
                    'is_active' => $bill->is_active,
                    'next_due_date' => $next?->due_date->toDateString(),
                    'next_due_date_jalali' => $isJalali
                        ? CalendarDates::toJalali($next?->due_date)
                        : null,
                    'pending_occurrences' => $bill->occurrences
                        ->map(fn ($occurrence, int $index): array => [
                            'id' => $occurrence->id,
                            'due_date' => $occurrence->due_date->toDateString(),
                            'payment_number' => $bill->recurrence_count !== null
                                ? (int) $bill->paid_occurrence_count + $index + 1
                                : null,
                            'due_date_jalali' => $isJalali
                                ? CalendarDates::toJalali($occurrence->due_date)
                                : null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy(fn (array $bill): string => $bill['next_due_date'] ?? '9999-12-31')
            ->values();

        return Response::structured([
            'calendar' => $isJalali ? 'jalali' : 'gregorian',
            'bills' => $bills->all(),
        ]);
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
