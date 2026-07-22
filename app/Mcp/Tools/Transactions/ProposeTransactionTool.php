<?php

namespace App\Mcp\Tools\Transactions;

use App\Mcp\Support\ProposalService;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Propose creating, updating, or deleting a transaction. This does NOT change any data: it validates the change, stores a proposal, and returns a diff plus a proposal_id. Show the diff to the user, get their approval, then call confirm-proposal. Proposals expire after 10 minutes.')]
class ProposeTransactionTool extends Tool
{
    public function __construct(private readonly ProposalService $proposals) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $action = $request->get('action');

        if (! in_array($action, ['create', 'update', 'delete'], true)) {
            return Response::error('action must be one of: create, update, delete.');
        }

        $transaction = null;

        if (in_array($action, ['update', 'delete'], true)) {
            $transaction = $user->transactions()->find($request->get('transaction_id'));

            if (! $transaction instanceof Transaction) {
                return Response::error('Transaction not found. Use list-transactions to find valid ids.');
            }
        }

        if ($action === 'delete') {
            $proposal = $this->proposals->propose($user, 'delete', 'transaction', $transaction->id, [], [
                'deleting' => [
                    'title' => $transaction->title,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency->value,
                    'occurred_at' => $transaction->occurred_at->toDateString(),
                ],
            ]);

            return $this->proposals->toResponse($proposal);
        }

        $validated = $request->validate(TransactionRules::rules());

        if ($errors = TransactionRules::categoryErrors($user, $validated['category_id'], $validated['type'])) {
            return Response::error(implode(' ', $errors));
        }

        $payload = [...$validated, 'description' => $validated['description'] ?? null];

        $old = $transaction ? [
            'type' => $transaction->type->value,
            'category_id' => $transaction->category_id,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency->value,
            'title' => $transaction->title,
            'description' => $transaction->description,
            'occurred_at' => $transaction->occurred_at->toDateString(),
        ] : [];

        $proposal = $this->proposals->propose(
            $user,
            $action,
            'transaction',
            $transaction?->id,
            $payload,
            $this->proposals->diff($payload, $old),
        );

        return $this->proposals->toResponse($proposal);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'delete'])->description('What to do with the transaction.')->required(),
            'transaction_id' => $schema->integer()->description('Required for update and delete: the transaction id.'),
            'type' => $schema->string()->enum(['cost', 'income'])->description('Transaction type (required for create/update).'),
            'category_id' => $schema->integer()->description('Category id matching the transaction type (see list-categories). Required for create/update.'),
            'amount' => $schema->number()->description('Amount, greater than zero (required for create/update).'),
            'currency' => $schema->string()->enum(['toman', 'usd', 'eur'])->description('Currency (required for create/update).'),
            'title' => $schema->string()->description('Short title (required for create/update).'),
            'description' => $schema->string()->description('Optional longer note.'),
            'occurred_at' => $schema->string()->description('Date of the transaction (YYYY-MM-DD, Gregorian). Required for create/update.'),
        ];
    }
}
