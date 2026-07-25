<?php

namespace App\Mcp\Tools\Investments;

use App\Actions\Investments\SaveInvestment;
use App\Enums\Feature;
use App\Mcp\Concerns\RequiresFeature;
use App\Mcp\Support\ProposalService;
use App\Models\Investment;
use App\Models\User;
use App\Support\CalendarDates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Propose recording, updating, or deleting an investment entry. To record a partial sale, update the entry with a reduced quantity; delete the entry for a full sale. This does NOT change any data: it returns a diff and a proposal_id — show it to the user, get approval, then call confirm-proposal.')]
class ProposeInvestmentTool extends Tool
{
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Investments];
    }

    public function __construct(private readonly ProposalService $proposals) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $action = $request->get('action');

        if (! in_array($action, ['create', 'update', 'delete'], true)) {
            return Response::error('action must be one of: create, update, delete.');
        }

        $investment = null;

        if (in_array($action, ['update', 'delete'], true)) {
            $investment = $user->investments()->with('asset')->find($request->get('investment_id'));

            if (! $investment instanceof Investment) {
                return Response::error('Investment entry not found. Use list-investments to find valid ids.');
            }
        }

        if ($action === 'delete') {
            $proposal = $this->proposals->propose($user, 'delete', 'investment', $investment->id, [], [
                'deleting' => [
                    'asset' => $investment->asset?->label() ?? $investment->asset_type,
                    'quantity' => (float) $investment->quantity,
                    'occurred_at' => $investment->occurred_at->toDateString(),
                ],
            ]);

            return $this->proposals->toResponse($proposal);
        }

        // Jalali dates are converted server-side before validation so they
        // are never misread as ancient Gregorian dates.
        $request->merge([
            'occurred_at' => CalendarDates::normalizeToGregorian($request->get('occurred_at')),
        ]);

        $validated = $request->validate(SaveInvestment::rules());

        try {
            $payload = SaveInvestment::normalize($user, $validated);
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        }

        $old = $investment ? [
            'investment_asset_id' => $investment->investment_asset_id,
            'asset_type' => $investment->asset_type,
            'quantity' => (float) $investment->quantity,
            'cost_basis' => $investment->cost_basis !== null ? (float) $investment->cost_basis : null,
            'cost_basis_currency' => $investment->cost_basis_currency,
            'note' => $investment->note,
            'occurred_at' => $investment->occurred_at->toDateString(),
        ] : [];

        $diff = $this->proposals->diff($payload, $old);

        // Jalali users approve the diff in chat, so date changes carry their
        // calendar's representation alongside the stored Gregorian value.
        if (CalendarDates::isJalaliUser($user) && isset($diff['occurred_at'])) {
            $diff['occurred_at']['new_jalali'] = CalendarDates::toJalali($diff['occurred_at']['new']);
            $diff['occurred_at']['old_jalali'] = CalendarDates::toJalali($diff['occurred_at']['old']);
        }

        $proposal = $this->proposals->propose(
            $user,
            $action,
            'investment',
            $investment?->id,
            $payload,
            $diff,
        );

        return $this->proposals->toResponse($proposal);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'delete'])->description('What to do with the investment entry.')->required(),
            'investment_id' => $schema->integer()->description('Required for update and delete: the investment entry id.'),
            'investment_asset_id' => $schema->integer()->description('Asset id (see list-investments available_assets). Either this or asset_type is required for create/update.'),
            'asset_type' => $schema->string()->description('Asset slug, e.g. bitcoin or gold. Alternative to investment_asset_id.'),
            'quantity' => $schema->number()->description('Quantity held (must be positive; reduce it to record a partial sale). Required for create/update.'),
            'total_cost' => $schema->number()->description('Total amount paid for this entry; cost basis per unit is derived from it.'),
            'cost_basis' => $schema->number()->description('Cost per unit. Ignored when total_cost is given.'),
            'cost_basis_currency' => $schema->string()->enum(['toman', 'usd', 'eur'])->description('Currency of the cost basis.'),
            'note' => $schema->string()->description('Optional note.'),
            'occurred_at' => $schema->string()->description('Date acquired (YYYY-MM-DD, not in the future). Gregorian or Jalali — Jalali years (1100-1599) are auto-detected and converted server-side. Required for create/update.'),
        ];
    }
}
