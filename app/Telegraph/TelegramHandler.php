<?php

namespace App\Telegraph;

use App\Actions\Bills\MarkBillOccurrencePaid;
use App\Actions\Bills\SyncBillOccurrence;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\BillRecurrenceType;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TelegramReportService;
use App\Support\DateFormatter;
use App\Support\FrontendLocalization;
use Carbon\Carbon;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Morilog\Jalali\Jalalian;

class TelegramHandler extends WebhookHandler
{
    public function start(string $token = ''): void
    {
        if ($token !== '') {
            $this->linkAccount($token);

            return;
        }

        $user = $this->resolveUser();

        if (! $user) {
            $this->chat->message(
                "Welcome! To get started, link your account:\n\n".
                'Go to Settings > Telegram in the app and click Connect Telegram.'
            )->send();

            return;
        }

        $this->sendHelp($user);
    }

    public function help(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $this->sendHelp($user);
    }

    public function add_cost(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $this->clearActiveWizards();
        $this->chat->storage()->set('wizard', [
            'type' => 'cost',
            'step' => 'amount',
            'user_id' => $user->id,
        ]);

        $this->chat->message("*Add cost*\n\nEnter the amount, for example: 50000")
            ->keyboard($this->cancelToMenuKeyboard())
            ->send();
    }

    public function add_income(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $this->clearActiveWizards();
        $this->chat->storage()->set('wizard', [
            'type' => 'income',
            'step' => 'amount',
            'user_id' => $user->id,
        ]);

        $this->chat->message("*Add income*\n\nEnter the amount, for example: 5000000")
            ->keyboard($this->cancelToMenuKeyboard())
            ->send();
    }

    public function add_investment(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $buttons = InvestmentAsset::query()
            ->availableFor($user)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (InvestmentAsset $asset): Button => Button::make($asset->label())->action('inv_asset')->param('asset', (string) $asset->id));

        $this->clearActiveWizards();
        $this->chat->storage()->set('inv_wizard', ['step' => 'asset', 'user_id' => $user->id]);

        $this->chat->message("*Add investment*\n\nChoose asset type:")
            ->keyboard($this->withCancelToMenu(Keyboard::make()->buttons($buttons)->chunk(2)))
            ->send();
    }

    public function add_bill(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $this->clearActiveWizards();
        $this->chat->storage()->set('bill_wizard', ['step' => 'title', 'user_id' => $user->id]);

        $this->chat->message("*Add bill*\n\nEnter a title, for example: Rent")
            ->keyboard($this->cancelToMenuKeyboard())
            ->send();
    }

    public function list_bills(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $bills = $user->bills()
            ->where('is_active', true)
            ->with(['occurrences' => fn ($query) => $query->whereNull('paid_at')->orderBy('due_date')])
            ->get()
            ->sortBy(function ($bill) {
                $next = $bill->occurrences->first();

                return $next ? $next->due_date->toDateString() : '9999-12-31';
            });

        if ($bills->isEmpty()) {
            $this->chat->message('No bills yet. Choose Add bill to set one up.')->keyboard($this->mainKeyboard())->send();

            return;
        }

        $lines = ["*Your bills:*\n"];
        $payButtons = [];
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        foreach ($bills as $bill) {
            $next = $bill->occurrences->first();
            $amount = $this->fmtAmount((float) $bill->amount, Currency::from($bill->currency));

            if ($next) {
                $dueDate = DateFormatter::format($next->due_date, $calendar, 'Y-m-d');
                $lines[] = "• *{$bill->title}* — {$amount} — due {$dueDate}";
                $payButtons[] = Button::make("Mark paid: {$bill->title}")
                    ->action('pay_bill')
                    ->param('bill', (string) $bill->id)
                    ->param('occurrence', (string) $next->id);
            } else {
                $lines[] = "• *{$bill->title}* — {$amount} — no upcoming due date";
            }
        }

        $keyboard = $this->withBackToMenu(Keyboard::make()->buttons($payButtons)->chunk(1));

        $this->chat->message(implode("\n", $lines))->keyboard($keyboard)->send();
    }

    public function pay_bill(?string $bill = null, ?string $occurrence = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $billModel = $user->bills()->find((int) ($bill ?? $this->data->get('bill')));
        $occurrenceModel = $billModel?->occurrences()->find((int) ($occurrence ?? $this->data->get('occurrence')));

        if (! $billModel || ! $occurrenceModel) {
            $this->reply('Bill not found.');

            return;
        }

        if ($occurrenceModel->isPaid()) {
            $this->reply("\"{$billModel->title}\" is already marked as paid.");

            return;
        }

        app(MarkBillOccurrencePaid::class)($billModel, $occurrenceModel);

        $amount = $this->fmtAmount((float) $billModel->amount, Currency::from($billModel->currency));
        $this->chat->message("✓ *{$billModel->title}* marked as paid ({$amount}). A transaction was added.")
            ->keyboard($this->mainKeyboard())
            ->send();
    }

    public function list(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $transactions = $user->transactions()
            ->with('category')
            ->latest('occurred_at')
            ->latest()
            ->take(10)
            ->get();

        if ($transactions->isEmpty()) {
            $this->chat->message('No transactions found.')->keyboard($this->mainKeyboard())->send();

            return;
        }

        $lines = ["*Last 10 transactions:*\n"];
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        foreach ($transactions as $transaction) {
            $sign = $transaction->type === TransactionType::Cost ? '−' : '+';
            $date = DateFormatter::format($transaction->occurred_at, $calendar, 'M j');
            $lines[] = "{$sign} *{$transaction->title}* — {$this->fmtAmount((float) $transaction->amount, $transaction->currency)} ({$date})";
        }

        $keyboard = Keyboard::make()->buttons([
            Button::make('Back to menu')->action('help'),
        ]);

        $this->chat->message(implode("\n", $lines))->keyboard($keyboard)->send();
    }

    public function report_today(): void
    {
        $this->sendReport(fn (User $user): string => app(TelegramReportService::class)->daily($user, Carbon::today()));
    }

    public function report_week(): void
    {
        $this->sendReport(fn (User $user): string => app(TelegramReportService::class)->weekly($user, Carbon::today()));
    }

    public function report_month(): void
    {
        $this->sendReport(fn (User $user): string => app(TelegramReportService::class)->monthly($user, Carbon::today()));
    }

    public function report_daily(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $today = Carbon::today();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        $buttons = collect();

        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->subDays($i);

            if ($calendar === 'jalali') {
                $j = Jalalian::fromCarbon($date);
                $jLabel = $j->format('j M');
                $label = match ($i) {
                    0 => 'Today ('.$jLabel.')',
                    1 => 'Yesterday ('.$jLabel.')',
                    default => $jLabel,
                };
            } else {
                $label = match ($i) {
                    0 => 'Today ('.$date->format('D').')',
                    1 => 'Yesterday ('.$date->format('D').')',
                    default => $date->format('D, M j'),
                };
            }

            $buttons->push(
                Button::make($label)->action('report_day_pick')->param('date', $date->toDateString())->width(0.5)
            );
        }

        $this->chat->message('Choose a day:')
            ->keyboard($this->withCancelToMenu(Keyboard::make()->buttons($buttons->all())))
            ->send();
    }

    public function report_day_pick(?string $date = null): void
    {
        $dateStr = $date ?? (string) $this->data->get('date');
        $carbonDate = Carbon::parse($dateStr);

        $this->sendReport(fn (User $user): string => app(TelegramReportService::class)->daily($user, $carbonDate));
    }

    public function portfolio(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        /** @var BuildPortfolioBreakdown $breakdown */
        $breakdown = app(BuildPortfolioBreakdown::class);
        $entries = $breakdown->entriesFor($user);

        if ($entries->isEmpty()) {
            $this->chat->message('No investments recorded yet. Use Add investment from the menu to get started.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $currency = $user->default_currency
            ? Currency::from($user->default_currency)
            : Currency::Toman;
        $currencyLabel = strtoupper($currency->value === 'toman' ? 'T' : $currency->value);
        $result = $breakdown->handle($entries, $currency);
        $summary = $result['summary'];

        $lines = [];
        $lines[] = '📊 Portfolio';
        $lines[] = '';
        $lines[] = '💰 Net worth: '.$summary['total_current_value_formatted'].' '.$currencyLabel;

        if ($summary['has_cost_basis_data'] && $summary['total_pnl_formatted'] !== null) {
            $arrow = $summary['total_pnl_is_positive'] ? '▲' : '▼';
            $lines[] = "P/L: {$arrow} ".$summary['total_pnl_formatted'].' '.$currencyLabel.' ('.$summary['total_pnl_percent'].'%)';
        }

        $lines[] = '';
        $lines[] = '───────────';

        foreach ($result['assets'] as $asset) {
            $qty = $asset['quantity'];
            $qtyDisplay = ($qty == floor($qty)) ? (int) $qty : round($qty, 4);
            $lines[] = '';
            $lines[] = $asset['label'].'  '.$qtyDisplay.' '.$asset['unit'];

            if ($asset['price_available']) {
                $valueLine = '→ '.$asset['current_value_formatted'].' '.$currencyLabel;

                if ($asset['pnl'] !== null && $asset['pnl_formatted'] !== null) {
                    $sign = $asset['pnl_is_positive'] ? '+' : '-';
                    $valueLine .= '  ('.$sign.$asset['pnl_formatted'].' '.$currencyLabel.')';
                }

                $lines[] = $valueLine;
            } else {
                $lines[] = '→ Price unavailable';
            }
        }

        $this->chat->message(implode("\n", $lines))->keyboard($this->mainKeyboard())->send();
    }

    public function delete_tx(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $id = $this->data->get('id');
        $transaction = Transaction::query()->where('user_id', $user->id)->find($id);

        if (! $transaction) {
            $this->reply('Transaction not found.');

            return;
        }

        $transaction->delete();
        $this->reply("Transaction #{$id} deleted.");
    }

    public function inv_asset(?string $asset = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $asset ??= $this->data->get('asset');
        $investmentAsset = InvestmentAsset::query()
            ->availableFor($user)
            ->whereKey((int) $asset)
            ->first();

        if (! $investmentAsset) {
            $this->chat->message('Investment asset not found. Choose Add investment to start again.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard = $this->chat->storage()->get('inv_wizard', []);
        $wizard['asset_id'] = $investmentAsset->id;
        $wizard['asset_slug'] = $investmentAsset->slug;
        $wizard['asset_label'] = $investmentAsset->label();
        $wizard['step'] = 'quantity';
        $this->chat->storage()->set('inv_wizard', $wizard);

        $this->chat->message("Enter quantity for {$investmentAsset->label()}:")
            ->keyboard($this->cancelToMenuKeyboard())
            ->send();
    }

    public function inv_pick_currency(?string $currency = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $currency = Currency::tryFrom($currency ?? (string) $this->data->get('currency'));
        $wizard = $this->chat->storage()->get('inv_wizard', []);

        if (! $currency || ($wizard['step'] ?? '') !== 'cost_basis_currency') {
            $this->chat->message('The investment draft expired. Choose Add investment to start again.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        Investment::create([
            'user_id' => $wizard['user_id'],
            'investment_asset_id' => $wizard['asset_id'],
            'asset_type' => $wizard['asset_slug'],
            'quantity' => $wizard['quantity'],
            'cost_basis' => $wizard['cost_basis'],
            'cost_basis_currency' => $currency->value,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->chat->storage()->forget('inv_wizard');
        $this->chat->message('Investment saved.')->keyboard($this->mainKeyboard())->send();
    }

    protected function handleChatMessage(\Stringable $text): void
    {
        $text = (string) $text;
        $user = $this->resolveUser();

        if (! $user) {
            $this->chat->message('Please link your account first via Settings > Telegram in the app.')->send();

            return;
        }

        $wizard = $this->chat->storage()->get('wizard');
        if ($wizard) {
            $this->handleTransactionWizard($wizard, $text, $user);

            return;
        }

        $invWizard = $this->chat->storage()->get('inv_wizard');
        if ($invWizard) {
            $this->handleInvestmentWizard($invWizard, $text, $user);

            return;
        }

        $billWizard = $this->chat->storage()->get('bill_wizard');
        if ($billWizard) {
            $this->handleBillWizard($billWizard, $text, $user);

            return;
        }

        $this->chat->message('Choose an action:')->keyboard($this->mainKeyboard())->send();
    }

    private function handleTransactionWizard(array $wizard, string $text, User $user): void
    {
        switch ($wizard['step']) {
            case 'amount':
                if (! is_numeric($text)) {
                    $this->chat->message('Please enter a valid number.')
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['amount'] = (float) $text;
                $wizard['step'] = 'currency';
                $this->chat->storage()->set('wizard', $wizard);

                $this->chat->message('Choose a currency:')
                    ->keyboard($this->currencyKeyboard())
                    ->send();
                break;

            case 'currency':
                $this->chat->message('Please choose a currency using the buttons above.')
                    ->keyboard($this->currencyKeyboard())
                    ->send();
                break;

            case 'category':
                $this->chat->message('Please choose a category using the buttons above.')
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();
                break;

            case 'title':
                $wizard['title'] = $text;
                $wizard['step'] = 'confirm';
                $this->chat->storage()->set('wizard', $wizard);

                $keyboard = Keyboard::make()
                    ->button('Confirm')->action('confirm_tx')->param('ok', '1')
                    ->button('Cancel')->action('cancel_tx')->param('ok', '0');

                $summary = "*Confirm transaction:*\n".
                    "Type: {$wizard['type']}\n".
                    'Amount: '.$this->formatWizardAmount($wizard)."\n".
                    "Title: {$wizard['title']}\n".
                    'Date: today';

                $this->chat->message($summary)->keyboard($keyboard)->send();
                break;
        }
    }

    private function handleInvestmentWizard(array $wizard, string $text, User $user): void
    {
        if ($wizard['step'] === 'quantity') {
            if (! is_numeric($text) || (float) $text <= 0) {
                $this->chat->message('Please enter a valid positive number.')
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();

                return;
            }

            $wizard['quantity'] = (float) $text;
            $wizard['step'] = 'cost_basis';
            $this->chat->storage()->set('inv_wizard', $wizard);

            $this->chat->message('Enter cost basis per unit. Send 0 to skip.')
                ->keyboard($this->cancelToMenuKeyboard())
                ->send();

            return;
        }

        if ($wizard['step'] === 'cost_basis') {
            $costBasis = is_numeric($text) && (float) $text > 0 ? (float) $text : null;

            if ($costBasis !== null) {
                $wizard['cost_basis'] = $costBasis;
                $wizard['step'] = 'cost_basis_currency';
                $this->chat->storage()->set('inv_wizard', $wizard);

                $this->chat->message('Select the currency for the cost basis:')
                    ->keyboard($this->invCurrencyKeyboard())
                    ->send();

                return;
            }

            Investment::create([
                'user_id' => $user->id,
                'investment_asset_id' => $wizard['asset_id'],
                'asset_type' => $wizard['asset_slug'],
                'quantity' => $wizard['quantity'],
                'cost_basis' => null,
                'occurred_at' => now()->toDateString(),
            ]);

            $this->chat->storage()->forget('inv_wizard');
            $this->chat->message('Investment saved.')->keyboard($this->mainKeyboard())->send();
        }
    }

    /**
     * @param  array<string, mixed>  $wizard
     */
    private function handleBillWizard(array $wizard, string $text, User $user): void
    {
        switch ($wizard['step']) {
            case 'title':
                $title = trim($text);

                if ($title === '') {
                    $this->chat->message('Please enter a non-empty title.')
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['title'] = $title;
                $wizard['step'] = 'amount';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->chat->message('Enter the amount, for example: 500000')
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();
                break;

            case 'amount':
                if (! is_numeric($text) || (float) $text <= 0) {
                    $this->chat->message('Please enter a valid positive number.')
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['amount'] = (float) $text;
                $wizard['step'] = 'currency';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->chat->message('Choose a currency:')
                    ->keyboard($this->billCurrencyKeyboard())
                    ->send();
                break;

            case 'currency':
                $this->chat->message('Please choose a currency using the buttons above.')
                    ->keyboard($this->billCurrencyKeyboard())
                    ->send();
                break;

            case 'category':
                $this->chat->message('Please choose a category using the buttons above.')
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();
                break;

            case 'recurrence':
                $this->chat->message('Please choose monthly or one-time using the buttons above.')
                    ->keyboard($this->billRecurrenceKeyboard())
                    ->send();
                break;

            case 'due_day':
                $trimmed = trim($text);

                if (! ctype_digit($trimmed) || (int) $trimmed < 1 || (int) $trimmed > 31) {
                    $this->chat->message('Please enter a valid day of month (1-31).')
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['due_day_of_month'] = (int) $trimmed;
                $wizard['step'] = 'confirm';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->sendBillConfirmation($wizard);
                break;

            case 'due_date':
                $date = $this->parseBillDate($text);

                if ($date === null) {
                    $this->chat->message('Please enter a valid date as YYYY-MM-DD, for example: 2026-08-01')
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['due_date'] = $date;
                $wizard['step'] = 'confirm';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->sendBillConfirmation($wizard);
                break;
        }
    }

    /**
     * @param  array<string, mixed>  $wizard
     */
    private function sendBillConfirmation(array $wizard): void
    {
        $keyboard = Keyboard::make()
            ->button('Confirm')->action('confirm_bill')->param('ok', '1')
            ->button('Cancel')->action('cancel_bill')->param('ok', '0');

        $recurrence = $wizard['recurrence_type'] === BillRecurrenceType::Monthly->value
            ? 'Monthly — day '.$wizard['due_day_of_month']
            : 'One-time — '.$wizard['due_date'];

        $summary = "*Confirm bill:*\n".
            "Title: {$wizard['title']}\n".
            'Amount: '.$this->fmtAmount((float) $wizard['amount'], Currency::from($wizard['currency']))."\n".
            "Recurrence: {$recurrence}";

        $this->chat->message($summary)->keyboard($keyboard)->send();
    }

    private function parseBillDate(string $text): ?string
    {
        $text = trim($text);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function bill_pick_currency(?string $currency = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $currency = $currency ?? $this->data->get('currency');
        $currency = Currency::tryFrom((string) $currency);
        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (! $currency || ! isset($wizard['amount'], $wizard['title'])) {
            $this->chat->message('The bill draft expired. Choose Add bill to start again.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['currency'] = $currency->value;
        $wizard['step'] = 'category';
        $this->chat->storage()->set('bill_wizard', $wizard);

        $categories = Category::query()
            ->availableFor($user)
            ->where('type', TransactionType::Cost)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $keyboard = Keyboard::make()
            ->button('No category')->action('bill_pick_category')->param('cat_id', '0')->width(1);

        foreach ($categories as $category) {
            $keyboard = $keyboard
                ->button($category->name)
                ->action('bill_pick_category')
                ->param('cat_id', (string) $category->id)
                ->width(0.5);
        }

        $this->chat->message('Choose a category:')->keyboard($this->withCancelToMenu($keyboard))->send();
    }

    public function bill_pick_category(?string $cat_id = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $catId = $cat_id ?? $this->data->get('cat_id');
        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (! isset($wizard['amount'], $wizard['currency'], $wizard['title'])) {
            $this->chat->message('The bill draft expired. Choose Add bill to start again.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['category_id'] = ($catId === '0' || $catId === null) ? null : (int) $catId;
        $wizard['step'] = 'recurrence';
        $this->chat->storage()->set('bill_wizard', $wizard);

        $this->chat->message('Choose recurrence:')->keyboard($this->billRecurrenceKeyboard())->send();
    }

    public function bill_pick_recurrence(?string $type = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $type = $type ?? $this->data->get('type');
        $recurrence = BillRecurrenceType::tryFrom((string) $type);
        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (! $recurrence || ! isset($wizard['amount'], $wizard['currency'], $wizard['title'])) {
            $this->chat->message('The bill draft expired. Choose Add bill to start again.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['recurrence_type'] = $recurrence->value;

        if ($recurrence === BillRecurrenceType::Monthly) {
            $wizard['step'] = 'due_day';
            $this->chat->storage()->set('bill_wizard', $wizard);
            $this->chat->message('Enter the day of month it is due (1-31):')
                ->keyboard($this->cancelToMenuKeyboard())
                ->send();

            return;
        }

        $wizard['step'] = 'due_date';
        $this->chat->storage()->set('bill_wizard', $wizard);
        $this->chat->message('Enter the due date as YYYY-MM-DD, for example: 2026-08-01')
            ->keyboard($this->cancelToMenuKeyboard())
            ->send();
    }

    public function confirm_bill(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (empty($wizard) || ! isset($wizard['title'], $wizard['amount'], $wizard['currency'], $wizard['recurrence_type'])) {
            $this->reply('No active bill draft. Choose Add bill to start again.');

            return;
        }

        $bill = $user->bills()->create([
            'title' => $wizard['title'],
            'amount' => $wizard['amount'],
            'currency' => $wizard['currency'],
            'category_id' => $wizard['category_id'] ?? null,
            'recurrence_type' => $wizard['recurrence_type'],
            'due_day_of_month' => $wizard['due_day_of_month'] ?? null,
            'due_date' => $wizard['due_date'] ?? null,
            'telegram_reminder_enabled' => true,
        ]);

        app(SyncBillOccurrence::class)->ensureInitial($bill);

        $this->chat->storage()->forget('bill_wizard');
        $this->chat->message("Bill \"{$bill->title}\" saved.")->keyboard($this->mainKeyboard())->send();
    }

    public function cancel_bill(): void
    {
        $this->deleteKeyboardIfCallback();

        $this->clearActiveWizards();
        $this->chat->message('Cancelled.')->keyboard($this->mainKeyboard())->send();
    }

    public function pick_category(?string $cat_id = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $catId = $cat_id ?? $this->data->get('cat_id');
        $wizard = $this->chat->storage()->get('wizard', []);

        if (! isset($wizard['amount'], $wizard['currency'], $wizard['type'])) {
            $this->chat->message('The transaction draft expired. Choose Add cost or Add income to start again.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['category_id'] = $catId;
        $wizard['step'] = 'title';
        $this->chat->storage()->set('wizard', $wizard);

        $this->chat->message('Enter a title for this transaction:')
            ->keyboard($this->cancelToMenuKeyboard())
            ->send();
    }

    public function pick_currency(?string $currency = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $currency = $currency ?? $this->data->get('currency');
        $currency = Currency::tryFrom((string) $currency);
        $wizard = $this->chat->storage()->get('wizard', []);

        if (! $currency || ! isset($wizard['amount'], $wizard['type'])) {
            $this->chat->message('The transaction draft expired. Choose Add cost or Add income to start again.')
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['currency'] = $currency->value;
        $wizard['step'] = 'category';
        $this->chat->storage()->set('wizard', $wizard);

        $type = TransactionType::from($wizard['type']);
        $categories = Category::query()
            ->availableFor($user)
            ->where('type', $type)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $keyboard = Keyboard::make();
        foreach ($categories as $category) {
            $keyboard = $keyboard
                ->button($category->name)
                ->action('pick_category')
                ->param('cat_id', (string) $category->id)
                ->width(0.5);
        }

        $this->chat->message('Choose a category:')
            ->keyboard($this->withCancelToMenu($keyboard))
            ->send();
    }

    public function confirm_tx(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $wizard = $this->chat->storage()->get('wizard', []);

        if (empty($wizard)) {
            $this->reply('No active transaction. Choose Add cost or Add income.');

            return;
        }

        Transaction::create([
            'user_id' => $user->id,
            'type' => $wizard['type'],
            'amount' => $wizard['amount'],
            'currency' => $wizard['currency'] ?? Currency::Toman->value,
            'title' => $wizard['title'],
            'category_id' => $wizard['category_id'],
            'occurred_at' => now()->toDateString(),
        ]);

        $this->chat->storage()->forget('wizard');
        $this->chat->message('Transaction saved.')->keyboard($this->mainKeyboard())->send();
    }

    public function cancel_tx(): void
    {
        $this->deleteKeyboardIfCallback();

        $this->clearActiveWizards();
        $this->chat->message('Cancelled.')->keyboard($this->mainKeyboard())->send();
    }

    public function cancel_current(): void
    {
        $this->deleteKeyboardIfCallback();

        $this->clearActiveWizards();
        $this->chat->message('Cancelled. Choose an action:')->keyboard($this->mainKeyboard())->send();
    }

    private function linkAccount(string $token): void
    {
        $user = User::query()->where('telegram_connect_token', $token)->first();

        if (! $user) {
            $this->chat->message('Invalid or expired link. Please generate a new one from the app.')->send();

            return;
        }

        $user->update([
            'telegram_chat_id' => (string) $this->chat->chat_id,
            'telegram_connect_token' => null,
        ]);

        $this->chat->message(
            "*Account linked successfully!*\n\n".
            "Welcome, {$user->name}. Choose an action:"
        )->keyboard($this->mainKeyboard())->send();
    }

    private function resolveUser(): ?User
    {
        return User::query()->where('telegram_chat_id', (string) $this->chat->chat_id)->first();
    }

    private function sendNotLinked(): void
    {
        $this->chat->message(
            "Your Telegram is not linked to an account.\n\n".
            'Go to Settings > Telegram in the app to connect.'
        )->send();
    }

    private function sendHelp(User $user): void
    {
        $this->chat->message(
            "Hello, *{$user->name}*.\n\n".
            'Choose an action:'
        )->keyboard($this->mainKeyboard())->send();
    }

    /**
     * @param  callable(User): string  $reportBuilder
     */
    private function sendReport(callable $reportBuilder): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $this->chat->message($reportBuilder($user))->keyboard($this->mainKeyboard())->send();
    }

    private function mainKeyboard(): Keyboard
    {
        return Keyboard::make()->buttons([
            Button::make('Add cost')->action('add_cost')->width(0.5),
            Button::make('Add income')->action('add_income')->width(0.5),
            Button::make('Last 10 transactions')->action('list')->width(0.5),
            Button::make('Add investment')->action('add_investment')->width(0.5),
            Button::make('Add bill')->action('add_bill')->width(0.5),
            Button::make('My bills')->action('list_bills')->width(0.5),
            Button::make('Portfolio')->action('portfolio')->width(0.5),
            Button::make('Daily report')->action('report_daily')->width(0.5),
            Button::make('Weekly report')->action('report_week')->width(0.5),
            Button::make('Monthly report')->action('report_month')->width(0.5),
        ]);
    }

    private function currencyKeyboard(): Keyboard
    {
        return $this->withCancelToMenu(Keyboard::make()->buttons(
            collect(Currency::cases())
                ->map(fn (Currency $currency): Button => Button::make(strtoupper($currency->value))
                    ->action('pick_currency')
                    ->param('currency', $currency->value)
                    ->width(1 / 3))
        ));
    }

    private function billCurrencyKeyboard(): Keyboard
    {
        return $this->withCancelToMenu(Keyboard::make()->buttons(
            collect(Currency::cases())
                ->map(fn (Currency $currency): Button => Button::make(strtoupper($currency->value))
                    ->action('bill_pick_currency')
                    ->param('currency', $currency->value)
                    ->width(1 / 3))
        ));
    }

    private function invCurrencyKeyboard(): Keyboard
    {
        return $this->withCancelToMenu(Keyboard::make()->buttons(
            collect(Currency::cases())
                ->map(fn (Currency $currency): Button => Button::make(strtoupper($currency->value))
                    ->action('inv_pick_currency')
                    ->param('currency', $currency->value)
                    ->width(1 / 3))
        ));
    }

    private function billRecurrenceKeyboard(): Keyboard
    {
        return $this->withCancelToMenu(
            Keyboard::make()
                ->button('Monthly')->action('bill_pick_recurrence')->param('type', BillRecurrenceType::Monthly->value)->width(0.5)
                ->button('One-time')->action('bill_pick_recurrence')->param('type', BillRecurrenceType::OneTime->value)->width(0.5)
        );
    }

    private function cancelToMenuKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->button('Cancel and menu')
            ->action('cancel_current')
            ->width(1);
    }

    private function withCancelToMenu(Keyboard $keyboard): Keyboard
    {
        return $keyboard
            ->button('Cancel and menu')
            ->action('cancel_current')
            ->width(1);
    }

    private function withBackToMenu(Keyboard $keyboard): Keyboard
    {
        return $keyboard
            ->button('Back to menu')
            ->action('help')
            ->width(1);
    }

    private function clearActiveWizards(): void
    {
        $this->chat->storage()->forget('wizard');
        $this->chat->storage()->forget('inv_wizard');
        $this->chat->storage()->forget('bill_wizard');
    }

    /**
     * @param  array<string, mixed>  $wizard
     */
    private function formatWizardAmount(array $wizard): string
    {
        $currency = Currency::tryFrom((string) ($wizard['currency'] ?? Currency::Toman->value))
            ?? Currency::Toman;

        return $this->fmtAmount((float) $wizard['amount'], $currency);
    }

    private function fmtAmount(float $amount, Currency $currency): string
    {
        return match ($currency) {
            Currency::Toman => number_format((int) round($amount), 0, '.', ',').' T',
            Currency::Usd => '$'.number_format($amount, 2, '.', ','),
            Currency::Eur => '€'.number_format($amount, 2, '.', ','),
        };
    }

    private function deleteKeyboardIfCallback(): void
    {
        if (! isset($this->callbackQueryId)) {
            return;
        }

        $this->deleteKeyboard();
    }
}
