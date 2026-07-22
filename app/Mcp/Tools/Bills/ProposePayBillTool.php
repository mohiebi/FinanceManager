<?php

namespace App\Mcp\Tools\Bills;

use App\Mcp\Support\ProposalService;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Propose marking a bill occurrence as paid, which also records the matching cost transaction. This does NOT change any data: it returns a proposal_id — show it to the user, get approval, then call confirm-proposal. Omit occurrence_id to pay the next unpaid occurrence.')]
class ProposePayBillTool extends Tool
{
    public function __construct(private readonly ProposalService $proposals) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'bill_id' => ['required', 'integer'],
            'occurrence_id' => ['nullable', 'integer'],
        ]);

        $bill = $user->bills()->find($validated['bill_id']);

        if (! $bill instanceof Bill) {
            return Response::error('Bill not found. Use list-bills to find valid ids.');
        }

        $occurrence = isset($validated['occurrence_id'])
            ? $bill->occurrences()->whereKey($validated['occurrence_id'])->first()
            : $bill->occurrences()->whereNull('paid_at')->orderBy('due_date')->first();

        if (! $occurrence) {
            return Response::error('No matching occurrence found for this bill.');
        }

        if ($occurrence->isPaid()) {
            return Response::error('This occurrence is already paid.');
        }

        $proposal = $this->proposals->propose(
            $user,
            'pay',
            'bill_occurrence',
            $bill->id,
            ['occurrence_id' => $occurrence->id],
            [
                'paying' => [
                    'bill' => $bill->title,
                    'amount' => (float) $bill->amount,
                    'currency' => $bill->currency,
                    'due_date' => $occurrence->due_date->toDateString(),
                ],
            ],
        );

        return $this->proposals->toResponse($proposal);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'bill_id' => $schema->integer()->description('The bill to pay (see list-bills).')->required(),
            'occurrence_id' => $schema->integer()->description('Specific occurrence id to pay. Defaults to the next unpaid occurrence.'),
        ];
    }
}
