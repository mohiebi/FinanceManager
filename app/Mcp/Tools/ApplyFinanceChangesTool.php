<?php

namespace App\Mcp\Tools;

use App\Enums\AssetClass;
use App\Exceptions\FeatureDisabledException;
use App\Mcp\Support\FinanceMutationApplier;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description('Apply one explicitly approved batch of CashPilot changes immediately. BEFORE calling, summarize the complete batch to the user and obtain clear confirmation. AFTER approval, put every row in this single operations array; never call this tool once per row and never ask for a second confirmation inside the approved batch. The entire batch is atomic: if any operation fails, none are saved. Supports transaction create/update/delete, category create, bill create/update, bill occurrence pay, investment create/update/delete, and custom investment asset create.')]
class ApplyFinanceChangesTool extends Tool
{
    public function __construct(private readonly FinanceMutationApplier $applier) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*' => ['required', 'array'],
            'operations.*.resource' => ['required', 'string'],
            'operations.*.action' => ['required', 'string'],
        ]);

        /** @var array<int, array<string, mixed>> $operations */
        $operations = $request->get('operations');

        $operationIndex = 0;

        try {
            $results = DB::transaction(function () use ($user, $operations, &$operationIndex): array {
                $applied = [];

                foreach ($operations as $index => $operation) {
                    $operationIndex = $index;
                    $applied[] = [
                        'index' => $index,
                        'resource' => $operation['resource'],
                        'action' => $operation['action'],
                        'result' => $this->applier->apply($user, $operation),
                    ];
                }

                return $applied;
            });
        } catch (ValidationException $exception) {
            return $this->batchError($operationIndex, implode(' ', $exception->validator->errors()->all()));
        } catch (FeatureDisabledException|InvalidArgumentException $exception) {
            return $this->batchError($operationIndex, $exception->getMessage());
        }

        return Response::structured([
            'status' => 'applied',
            'applied_count' => count($results),
            'results' => $results,
            'confirmation_required' => false,
        ]);
    }

    private function batchError(int $operationIndex, string $message): Response
    {
        return Response::error(
            'Batch failed at operation '.($operationIndex + 1).": {$message} No changes were saved.",
        );
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operations' => $schema->array()
                ->min(1)
                ->max(100)
                ->items($schema->object([
                    'resource' => $schema->string()->enum([
                        'transaction',
                        'category',
                        'bill',
                        'bill_occurrence',
                        'investment',
                        'investment_asset',
                    ])->description('Entity type.')->required(),
                    'action' => $schema->string()->enum(['create', 'update', 'delete', 'pay'])->description('Action. Supported combinations are listed in the tool description.')->required(),
                    'id' => $schema->integer()->description('Existing transaction, bill, or investment id. Required for update/delete and for paying a bill occurrence.'),
                    'occurrence_id' => $schema->integer()->description('Optional specific bill occurrence id; otherwise the next unpaid occurrence is used.'),
                    'type' => $schema->string()->enum(['cost', 'income'])->description('Transaction or category type.'),
                    'category_id' => $schema->integer()->description('Category id from list-categories.'),
                    'amount' => $schema->number()->description('Transaction or bill amount.'),
                    'currency' => $schema->string()->enum(['toman', 'usd', 'eur'])->description('Currency.'),
                    'title' => $schema->string()->description('Transaction or bill title.'),
                    'description' => $schema->string()->description('Optional transaction description.'),
                    'occurred_at' => $schema->string()->description('Transaction or investment date (YYYY-MM-DD). Pass Gregorian or Jalali dates unchanged.'),
                    'name' => $schema->string()->description('New category or custom asset name.'),
                    'parent_id' => $schema->integer()->description('New category only: optional parent category id to create it as a subcategory. One level only — the parent must be top-level and allow the category\'s type.'),
                    'for_both_types' => $schema->boolean()->description('New category only: optional, make it usable for both costs and income.'),
                    'recurrence_type' => $schema->string()->enum(['one_time', 'monthly'])->description('Bill recurrence.'),
                    'due_day_of_month' => $schema->integer()->description('Monthly bill due day (1-31).'),
                    'due_date' => $schema->string()->description('One-time bill due date (Gregorian or Jalali YYYY-MM-DD).'),
                    'recurrence_limit_type' => $schema->string()->enum(['infinite', 'count', 'date'])->description('Monthly bill duration: infinite, a payment count, or an end date.'),
                    'recurrence_count' => $schema->integer()->description('Total payments when recurrence_limit_type is count.'),
                    'recurrence_end_date' => $schema->string()->description('Last allowed payment date when recurrence_limit_type is date.'),
                    'telegram_reminder_enabled' => $schema->boolean()->description('Whether a bill sends Telegram reminders.'),
                    'reminder_time' => $schema->string()->description('Bill reminder time as HH:MM.'),
                    'reminder_timezone' => $schema->string()->description('Bill reminder IANA timezone.'),
                    'investment_asset_id' => $schema->integer()->description('Asset id from list-investments available_assets.'),
                    'asset_type' => $schema->string()->description('Asset slug, used instead of investment_asset_id.'),
                    'quantity' => $schema->number()->description('Investment quantity.'),
                    'total_cost' => $schema->number()->description('Total investment acquisition cost.'),
                    'cost_basis' => $schema->number()->description('Investment cost per unit when total_cost is absent.'),
                    'cost_basis_currency' => $schema->string()->enum(['toman', 'usd', 'eur'])->description('Investment cost-basis currency.'),
                    'note' => $schema->string()->description('Optional investment note.'),
                    'unit' => $schema->string()->description('Custom asset unit, such as shares or grams.'),
                    'price_source_type' => $schema->string()->enum(['manual', 'formula'])->description('Custom asset price source.'),
                    'price' => $schema->number()->description('Manual custom-asset price per unit in toman.'),
                    'formula' => $schema->string()->description('Custom-asset formula over existing asset slugs.'),
                    'asset_class' => $schema->string()->enum(AssetClass::values())->description('Custom asset family, e.g. metal for a gold coin or a silver bar.'),
                    'tracks_asset_slug' => $schema->string()->description('Slug of the asset whose market a custom asset really follows, e.g. "gold" for a half gold coin. Set it whenever the new asset is a form of an existing one, so the portfolio counts them as one exposure.'),
                    'units_of_tracked_asset_each' => $schema->number()->description('Optional. Units of the tracked asset per unit of the custom asset, e.g. 4.6 grams of gold per half coin.'),
                ])->withoutAdditionalProperties())
                ->description('Every change the user approved. Send the complete batch in one tool call.')
                ->required(),
        ];
    }
}
