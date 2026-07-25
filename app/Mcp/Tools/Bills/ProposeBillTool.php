<?php

namespace App\Mcp\Tools\Bills;

use App\Actions\Bills\SaveBill;
use App\Enums\Feature;
use App\Mcp\Concerns\RequiresFeature;
use App\Mcp\Support\ProposalService;
use App\Models\Bill;
use App\Models\User;
use App\Support\CalendarDates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Propose creating or updating a bill (recurring monthly or one-time). This does NOT change any data: it returns a diff and a proposal_id — show it to the user, get approval, then call confirm-proposal.')]
class ProposeBillTool extends Tool
{
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Bills];
    }

    public function __construct(private readonly ProposalService $proposals) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $action = $request->get('action');

        if (! in_array($action, ['create', 'update'], true)) {
            return Response::error('action must be one of: create, update.');
        }

        $bill = null;

        if ($action === 'update') {
            $bill = $user->bills()->find($request->get('bill_id'));

            if (! $bill instanceof Bill) {
                return Response::error('Bill not found. Use list-bills to find valid ids.');
            }
        }

        // Jalali dates are converted server-side before validation so they
        // are never misread as ancient Gregorian dates.
        $request->merge([
            'due_date' => CalendarDates::normalizeToGregorian($request->get('due_date')),
        ]);

        $validated = $request->validate(SaveBill::rules($user));
        $payload = SaveBill::normalize(
            $validated,
            $request->filled('category_id'),
            $request->has('telegram_reminder_enabled') ? (bool) $request->get('telegram_reminder_enabled') : null,
        );

        $old = $bill ? [
            'title' => $bill->title,
            'amount' => (float) $bill->amount,
            'currency' => $bill->currency,
            'category_id' => $bill->category_id,
            'recurrence_type' => $bill->recurrence_type->value,
            'due_day_of_month' => $bill->due_day_of_month,
            'due_date' => $bill->due_date?->toDateString(),
        ] : [];

        $diff = $this->proposals->diff($payload, $old);

        // Jalali users approve the diff in chat, so date changes carry their
        // calendar's representation alongside the stored Gregorian value.
        if (CalendarDates::isJalaliUser($user) && isset($diff['due_date'])) {
            $diff['due_date']['new_jalali'] = CalendarDates::toJalali($diff['due_date']['new']);
            $diff['due_date']['old_jalali'] = CalendarDates::toJalali($diff['due_date']['old']);
        }

        $proposal = $this->proposals->propose(
            $user,
            $action,
            'bill',
            $bill?->id,
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
            'action' => $schema->string()->enum(['create', 'update'])->description('What to do with the bill.')->required(),
            'bill_id' => $schema->integer()->description('Required for update: the bill id.'),
            'title' => $schema->string()->description('Bill title.')->required(),
            'amount' => $schema->number()->description('Amount due, greater than zero.')->required(),
            'currency' => $schema->string()->enum(['toman', 'usd', 'eur'])->description('Currency of the bill.')->required(),
            'category_id' => $schema->integer()->description('Optional cost-category id (see list-categories).'),
            'recurrence_type' => $schema->string()->enum(['one_time', 'monthly'])->description('Whether the bill repeats monthly or is due once.')->required(),
            'due_day_of_month' => $schema->integer()->description('For monthly bills: day of month it is due (1-31).'),
            'due_date' => $schema->string()->description('For one-time bills: due date (YYYY-MM-DD). Gregorian or Jalali — Jalali years (1100-1599) are auto-detected and converted server-side.'),
            'telegram_reminder_enabled' => $schema->boolean()->description('Send Telegram reminders for this bill (defaults to true).'),
            'reminder_time' => $schema->string()->description('Reminder time as HH:MM (24h).'),
            'reminder_timezone' => $schema->string()->description('IANA timezone for the reminder, e.g. Asia/Tehran.'),
        ];
    }
}
