<?php

namespace App\Telegraph;

use App\Actions\Bills\MarkBillOccurrencePaid;
use App\Actions\Bills\SaveBill;
use App\Actions\Budgets\BuildBudgetProgress;
use App\Actions\Gamification\RecordNoSpendDay;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\BillRecurrenceLimitType;
use App\Enums\BillRecurrenceType;
use App\Enums\BudgetRuleType;
use App\Enums\Currency;
use App\Enums\Feature;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TelegramReportService;
use App\Support\DateFormatter;
use App\Support\FrontendLocalization;
use App\Support\StreakCalculator;
use Carbon\Carbon;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

class TelegramHandler extends WebhookHandler
{
    private ?User $resolvedUser = null;

    private bool $userResolved = false;

    public function start(string $token = ''): void
    {
        if ($token !== '') {
            $this->linkAccount($token);

            return;
        }

        $user = $this->resolveUser();

        if (! $user) {
            $this->chat->message(__('telegram.link.welcome'))->send();

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

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $this->clearActiveWizards();
        $this->chat->storage()->set('wizard', [
            'type' => 'cost',
            'step' => 'amount',
            'user_id' => $user->id,
        ]);

        $this->chat->message(__('telegram.transaction.add_cost'))
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

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $this->clearActiveWizards();
        $this->chat->storage()->set('wizard', [
            'type' => 'income',
            'step' => 'amount',
            'user_id' => $user->id,
        ]);

        $this->chat->message(__('telegram.transaction.add_income'))
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

        if ($this->featureLocked($user, Feature::Investments)) {
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

        $this->chat->message(__('telegram.investment.start'))
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

        if ($this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $this->clearActiveWizards();
        $this->chat->storage()->set('bill_wizard', ['step' => 'title', 'user_id' => $user->id]);

        $this->chat->message(__('telegram.bill.start'))
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

        if ($this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $bills = $user->bills()
            ->where('is_active', true)
            ->with(['occurrences' => fn ($query) => $query->whereNull('paid_at')->orderBy('due_date')])
            ->withCount(['occurrences as paid_occurrence_count' => fn ($query) => $query->whereNotNull('paid_at')])
            ->get()
            ->sortBy(function ($bill) {
                $next = $bill->occurrences->first();

                return $next ? $next->due_date->toDateString() : '9999-12-31';
            });

        if ($bills->isEmpty()) {
            $this->chat->message(__('telegram.bill.empty'))->keyboard($this->mainKeyboard())->send();

            return;
        }

        $lines = [__('telegram.bill.list_title')];
        $payButtons = [];
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        foreach ($bills as $bill) {
            $next = $bill->occurrences->first();
            $amount = $this->fmtAmount((float) $bill->amount, Currency::from($bill->currency));

            if ($next) {
                $line = __('telegram.bill.line', [
                    'title' => $bill->title,
                    'amount' => $amount,
                    'date' => DateFormatter::format($next->due_date, $calendar, 'Y-m-d'),
                ]);
                $lines[] = $bill->recurrence_count !== null
                    ? $line.' — '.__('telegram.bill.payment_progress', [
                        'current' => (int) $bill->paid_occurrence_count + 1,
                        'total' => $bill->recurrence_count,
                    ])
                    : $line;
                $payButtons[] = Button::make(__('telegram.buttons.mark_paid', ['title' => $bill->title]))
                    ->action('pay_bill')
                    ->param('bill', (string) $bill->id)
                    ->param('occurrence', (string) $next->id);
            } else {
                $lines[] = __('telegram.bill.line_no_due', ['title' => $bill->title, 'amount' => $amount]);
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

        if ($this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $billModel = $user->bills()->find((int) ($bill ?? $this->data->get('bill')));
        $occurrenceModel = $billModel?->occurrences()->find((int) ($occurrence ?? $this->data->get('occurrence')));

        if (! $billModel || ! $occurrenceModel) {
            $this->reply(__('telegram.bill.not_found'));

            return;
        }

        if ($occurrenceModel->isPaid()) {
            $this->reply(__('telegram.bill.already_paid', ['title' => $billModel->title]));

            return;
        }

        app(MarkBillOccurrencePaid::class)($billModel, $occurrenceModel);

        $this->chat->message(__('telegram.bill.marked_paid', [
            'title' => $billModel->title,
            'amount' => $this->fmtAmount((float) $billModel->amount, Currency::from($billModel->currency)),
        ]))
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

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $transactions = $user->transactions()
            ->with('category')
            ->latest('occurred_at')
            ->latest()
            ->take(10)
            ->get();

        if ($transactions->isEmpty()) {
            $this->chat->message(__('telegram.transaction.empty'))->keyboard($this->mainKeyboard())->send();

            return;
        }

        $lines = [__('telegram.transaction.list_title')];
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        foreach ($transactions as $transaction) {
            $sign = $transaction->type === TransactionType::Cost ? '−' : '+';
            $date = DateFormatter::format($transaction->occurred_at, $calendar, 'M j');
            $lines[] = "{$sign} *{$transaction->title}* — {$this->fmtAmount((float) $transaction->amount, $transaction->currency)} ({$date})";
        }

        $keyboard = Keyboard::make()->buttons([
            Button::make(__('telegram.buttons.back_to_menu'))->action('help'),
        ]);

        $this->chat->message(implode("\n", $lines))->keyboard($keyboard)->send();
    }

    /**
     * Mark today as spend-free, keeping the run alive without inventing a
     * transaction that would then pollute every report.
     */
    public function no_spend(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        if ($this->featureLocked($user, Feature::Gamification)) {
            return;
        }

        $marked = app(RecordNoSpendDay::class)->handle($user);
        $streak = app(StreakCalculator::class)->for($user);

        $message = $marked === null
            ? __('telegram.streak.already_recorded')
            : __('telegram.streak.marked');

        $this->chat
            ->message(__('telegram.streak.run', ['message' => $message, 'days' => $streak->currentRun]))
            ->keyboard($this->mainKeyboard())
            ->send();
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

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $today = Carbon::today();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        $locale = $this->locale();
        $buttons = collect();

        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->subDays($i);

            if ($calendar === 'jalali') {
                $shortLabel = Jalalian::fromCarbon($date)->format('j M');
                $fullLabel = $shortLabel;
            } else {
                $shortLabel = $date->locale($locale)->translatedFormat('D');
                $fullLabel = $date->locale($locale)->translatedFormat('D, M j');
            }

            $label = match ($i) {
                0 => __('telegram.buttons.today', ['date' => $shortLabel]),
                1 => __('telegram.buttons.yesterday', ['date' => $shortLabel]),
                default => $fullLabel,
            };

            $buttons->push(
                Button::make($label)->action('report_day_pick')->param('date', $date->toDateString())->width(0.5)
            );
        }

        $this->chat->message(__('telegram.report.choose_day'))
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

        if ($this->featureLocked($user, Feature::Portfolio)) {
            return;
        }

        /** @var BuildPortfolioBreakdown $breakdown */
        $breakdown = app(BuildPortfolioBreakdown::class);
        $entries = $breakdown->entriesFor($user);

        if ($entries->isEmpty()) {
            $this->chat->message(__('telegram.portfolio.empty'))
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $currency = $user->default_currency
            ? Currency::from($user->default_currency)
            : Currency::Toman;
        $withCurrency = fn (string $formatted): string => __("telegram.amount.{$currency->value}", ['amount' => $formatted]);
        $result = $breakdown->handle($entries, $currency);
        $summary = $result['summary'];

        $lines = [];
        $lines[] = __('telegram.portfolio.title');
        $lines[] = '';
        $lines[] = __('telegram.portfolio.net_worth', [
            'amount' => $withCurrency($summary['total_current_value_formatted']),
        ]);

        if ($summary['has_cost_basis_data'] && $summary['total_pnl_formatted'] !== null) {
            $lines[] = __('telegram.portfolio.profit_loss', [
                'arrow' => $summary['total_pnl_is_positive'] ? '▲' : '▼',
                'amount' => $withCurrency($summary['total_pnl_formatted']),
                'percent' => $summary['total_pnl_percent'],
            ]);
        }

        $lines[] = '';
        $lines[] = '───────────';

        foreach ($result['assets'] as $asset) {
            $qty = $asset['quantity'];
            $qtyDisplay = ($qty == floor($qty)) ? (int) $qty : round($qty, 4);
            $lines[] = '';
            $lines[] = $asset['label'].'  '.$qtyDisplay.' '.$asset['unit'];

            if ($asset['price_available']) {
                $valueLine = '→ '.$withCurrency($asset['current_value_formatted']);

                if ($asset['pnl'] !== null && $asset['pnl_formatted'] !== null) {
                    $sign = $asset['pnl_is_positive'] ? '+' : '-';
                    $valueLine .= '  ('.$sign.$withCurrency($asset['pnl_formatted']).')';
                }

                $lines[] = $valueLine;
            } else {
                $lines[] = __('telegram.portfolio.price_unavailable');
            }
        }

        $this->chat->message(implode("\n", $lines))->keyboard($this->mainKeyboard())->send();
    }

    /**
     * This period's plan: what each line may spend, and what is left of it.
     *
     * No vault branch, unlike the web page: Feature::Vault conflicts with
     * Feature::TelegramBot, so a user whose server cannot read their amounts has
     * no linked chat to send this to in the first place.
     */
    public function budget(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        if ($this->featureLocked($user, Feature::Budgets)) {
            return;
        }

        $budget = $user->budgets()
            ->active()
            ->with('lines.category')
            ->latest('id')
            ->first();

        if (! $budget instanceof Budget) {
            $this->chat->message(__('telegram.budget.empty'))
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $progress = app(BuildBudgetProgress::class)->handle($user, $budget);
        $currency = $budget->currency;

        $lines = [];
        $lines[] = __('telegram.budget.title', ['period' => $progress['period']['label']]);
        $lines[] = '';
        $lines[] = __('telegram.budget.income', ['amount' => $this->fmtAmount((float) $progress['income'], $currency)]);
        $lines[] = __('telegram.budget.allocated', ['amount' => $this->fmtAmount((float) $progress['allocated'], $currency)]);
        $lines[] = __('telegram.budget.spent', ['amount' => $this->fmtAmount((float) $progress['actual'], $currency)]);

        if ((float) $progress['over_allocated'] > 0) {
            $lines[] = __('telegram.budget.over_allocated', [
                'amount' => $this->fmtAmount((float) $progress['over_allocated'], $currency),
            ]);
        }

        $lines[] = '';
        $lines[] = '───────────';

        foreach ($progress['lines'] as $line) {
            $label = $line['category']['name'] ?? __('telegram.budget.everything_else');
            $rule = $line['rule_type'] === BudgetRuleType::Percent->value && $line['percent'] !== null
                ? ' ('.rtrim(rtrim(number_format((float) $line['percent'], 2, '.', ''), '0'), '.').'%)'
                : '';

            $lines[] = '';
            $lines[] = $label.$rule;
            $lines[] = __('telegram.budget.line_progress', [
                'actual' => $this->fmtAmount((float) $line['actual'], $currency),
                'allocated' => $this->fmtAmount((float) $line['allocated'], $currency),
            ]);

            // The number the user actually came for: what is still spendable.
            $lines[] = $line['over']
                ? __('telegram.budget.line_over', ['amount' => $this->fmtAmount(abs((float) $line['remaining']), $currency)])
                : __('telegram.budget.line_left', ['amount' => $this->fmtAmount((float) $line['remaining'], $currency)]);
        }

        $lines[] = '';
        $lines[] = __('telegram.budget.days_left', ['days' => $progress['period']['days_remaining']]);

        $this->chat->message(implode("\n", $lines))->keyboard($this->mainKeyboard())->send();
    }

    public function delete_tx(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $id = $this->data->get('id');
        $transaction = Transaction::query()->where('user_id', $user->id)->find($id);

        if (! $transaction) {
            $this->reply(__('telegram.transaction.not_found'));

            return;
        }

        $transaction->delete();
        $this->reply(__('telegram.transaction.deleted', ['id' => $id]));
    }

    public function inv_asset(?string $asset = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        if ($this->featureLocked($user, Feature::Investments)) {
            return;
        }

        $asset ??= $this->data->get('asset');
        $investmentAsset = InvestmentAsset::query()
            ->availableFor($user)
            ->whereKey((int) $asset)
            ->first();

        if (! $investmentAsset) {
            $this->chat->message(__('telegram.investment.asset_not_found'))
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

        $this->chat->message(__('telegram.investment.enter_quantity', ['asset' => $investmentAsset->label()]))
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

        if ($this->featureLocked($user, Feature::Investments)) {
            return;
        }

        $currency = Currency::tryFrom($currency ?? (string) $this->data->get('currency'));
        $wizard = $this->chat->storage()->get('inv_wizard', []);

        if (! $currency || ($wizard['step'] ?? '') !== 'cost_basis_currency') {
            $this->chat->message(__('telegram.investment.draft_expired'))
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
        $this->chat->message(__('telegram.investment.saved'))->keyboard($this->mainKeyboard())->send();
    }

    protected function handleChatMessage(\Stringable $text): void
    {
        $text = (string) $text;
        $user = $this->resolveUser();

        if (! $user) {
            $this->chat->message(__('telegram.link.link_first'))->send();

            return;
        }

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $wizard = $this->chat->storage()->get('wizard');
        if ($wizard) {
            $this->handleTransactionWizard($wizard, $text, $user);

            return;
        }

        // A wizard can outlive the module being switched off in the web app, so
        // re-check before letting an in-flight one write anything.
        $invWizard = $this->chat->storage()->get('inv_wizard');
        if ($invWizard) {
            if ($this->featureLocked($user, Feature::Investments)) {
                $this->clearActiveWizards();

                return;
            }

            $this->handleInvestmentWizard($invWizard, $text, $user);

            return;
        }

        $billWizard = $this->chat->storage()->get('bill_wizard');
        if ($billWizard) {
            if ($this->featureLocked($user, Feature::Bills)) {
                $this->clearActiveWizards();

                return;
            }

            $this->handleBillWizard($billWizard, $text, $user);

            return;
        }

        $this->chat->message(__('telegram.menu.choose_action'))->keyboard($this->mainKeyboard())->send();
    }

    private function handleTransactionWizard(array $wizard, string $text, User $user): void
    {
        switch ($wizard['step']) {
            case 'amount':
                if (! is_numeric($text)) {
                    $this->chat->message(__('telegram.shared.invalid_number'))
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['amount'] = (float) $text;
                $wizard['step'] = 'currency';
                $this->chat->storage()->set('wizard', $wizard);

                $this->chat->message(__('telegram.shared.choose_currency'))
                    ->keyboard($this->currencyKeyboard())
                    ->send();
                break;

            case 'currency':
                $this->chat->message(__('telegram.shared.use_currency_buttons'))
                    ->keyboard($this->currencyKeyboard())
                    ->send();
                break;

            case 'category':
                $this->chat->message(__('telegram.shared.use_category_buttons'))
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();
                break;

            case 'title':
                $wizard['title'] = $text;
                $wizard['step'] = 'confirm';
                $this->chat->storage()->set('wizard', $wizard);

                $keyboard = Keyboard::make()
                    ->button(__('telegram.buttons.confirm'))->action('confirm_tx')->param('ok', '1')
                    ->button(__('telegram.buttons.cancel'))->action('cancel_tx')->param('ok', '0');

                $summary = __('telegram.transaction.confirm', [
                    'type' => __("telegram.transaction.types.{$wizard['type']}"),
                    'amount' => $this->formatWizardAmount($wizard),
                    'title' => $wizard['title'],
                ]);

                $this->chat->message($summary)->keyboard($keyboard)->send();
                break;
        }
    }

    private function handleInvestmentWizard(array $wizard, string $text, User $user): void
    {
        if ($wizard['step'] === 'quantity') {
            if (! is_numeric($text) || (float) $text <= 0) {
                $this->chat->message(__('telegram.shared.invalid_positive_number'))
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();

                return;
            }

            $wizard['quantity'] = (float) $text;
            $wizard['step'] = 'cost_basis';
            $this->chat->storage()->set('inv_wizard', $wizard);

            $this->chat->message(__('telegram.investment.enter_cost_basis'))
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

                $this->chat->message(__('telegram.investment.choose_cost_basis_currency'))
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
            $this->chat->message(__('telegram.investment.saved'))->keyboard($this->mainKeyboard())->send();
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
                    $this->chat->message(__('telegram.bill.empty_title'))
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['title'] = $title;
                $wizard['step'] = 'amount';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->chat->message(__('telegram.bill.enter_amount'))
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();
                break;

            case 'amount':
                if (! is_numeric($text) || (float) $text <= 0) {
                    $this->chat->message(__('telegram.shared.invalid_positive_number'))
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['amount'] = (float) $text;
                $wizard['step'] = 'currency';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->chat->message(__('telegram.shared.choose_currency'))
                    ->keyboard($this->billCurrencyKeyboard())
                    ->send();
                break;

            case 'currency':
                $this->chat->message(__('telegram.shared.use_currency_buttons'))
                    ->keyboard($this->billCurrencyKeyboard())
                    ->send();
                break;

            case 'category':
                $this->chat->message(__('telegram.shared.use_category_buttons'))
                    ->keyboard($this->cancelToMenuKeyboard())
                    ->send();
                break;

            case 'recurrence':
                $this->chat->message(__('telegram.bill.use_recurrence_buttons'))
                    ->keyboard($this->billRecurrenceKeyboard())
                    ->send();
                break;

            case 'recurrence_limit':
                $this->chat->message(__('telegram.bill.use_limit_buttons'))
                    ->keyboard($this->billRecurrenceLimitKeyboard())
                    ->send();
                break;

            case 'due_day':
                $trimmed = trim($text);

                if (! ctype_digit($trimmed) || (int) $trimmed < 1 || (int) $trimmed > 31) {
                    $this->chat->message(__('telegram.bill.invalid_due_day'))
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['due_day_of_month'] = (int) $trimmed;
                $wizard['step'] = 'recurrence_limit';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->chat->message(__('telegram.bill.choose_limit'))
                    ->keyboard($this->billRecurrenceLimitKeyboard())
                    ->send();
                break;

            case 'recurrence_count':
                $trimmed = trim($text);

                if (! ctype_digit($trimmed) || (int) $trimmed < 1 || (int) $trimmed > 600) {
                    $this->chat->message(__('telegram.bill.invalid_recurrence_count'))
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['recurrence_count'] = (int) $trimmed;
                $wizard['step'] = 'confirm';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->sendBillConfirmation($wizard);
                break;

            case 'recurrence_end_date':
                $date = $this->parseBillDate($text);

                if ($date === null) {
                    $this->chat->message(__('telegram.bill.invalid_due_date'))
                        ->keyboard($this->cancelToMenuKeyboard())
                        ->send();

                    return;
                }

                $wizard['recurrence_end_date'] = $date;
                $wizard['step'] = 'confirm';
                $this->chat->storage()->set('bill_wizard', $wizard);

                $this->sendBillConfirmation($wizard);
                break;

            case 'due_date':
                $date = $this->parseBillDate($text);

                if ($date === null) {
                    $this->chat->message(__('telegram.bill.invalid_due_date'))
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
            ->button(__('telegram.buttons.confirm'))->action('confirm_bill')->param('ok', '1')
            ->button(__('telegram.buttons.cancel'))->action('cancel_bill')->param('ok', '0');

        if ($wizard['recurrence_type'] === BillRecurrenceType::Monthly->value) {
            $limit = match ($wizard['recurrence_limit_type'] ?? BillRecurrenceLimitType::Infinite->value) {
                BillRecurrenceLimitType::Count->value => __('telegram.bill.limit_count', ['count' => $wizard['recurrence_count']]),
                BillRecurrenceLimitType::Date->value => __('telegram.bill.limit_date', ['date' => $wizard['recurrence_end_date']]),
                default => __('telegram.bill.limit_infinite'),
            };
            $recurrence = __('telegram.bill.recurrence_monthly', ['day' => $wizard['due_day_of_month']]).' — '.$limit;
        } else {
            $recurrence = __('telegram.bill.recurrence_one_time', ['date' => $wizard['due_date']]);
        }

        $summary = __('telegram.bill.confirm', [
            'title' => $wizard['title'],
            'amount' => $this->fmtAmount((float) $wizard['amount'], Currency::from($wizard['currency'])),
            'recurrence' => $recurrence,
        ]);

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

        if ($this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $currency = $currency ?? $this->data->get('currency');
        $currency = Currency::tryFrom((string) $currency);
        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (! $currency || ! isset($wizard['amount'], $wizard['title'])) {
            $this->chat->message(__('telegram.bill.draft_expired'))
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
            ->button(__('telegram.buttons.no_category'))->action('bill_pick_category')->param('cat_id', '0')->width(1);

        foreach ($categories as $category) {
            $keyboard = $keyboard
                ->button($category->name)
                ->action('bill_pick_category')
                ->param('cat_id', (string) $category->id)
                ->width(0.5);
        }

        $this->chat->message(__('telegram.shared.choose_category'))->keyboard($this->withCancelToMenu($keyboard))->send();
    }

    public function bill_pick_category(?string $cat_id = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        if ($this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $catId = $cat_id ?? $this->data->get('cat_id');
        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (! isset($wizard['amount'], $wizard['currency'], $wizard['title'])) {
            $this->chat->message(__('telegram.bill.draft_expired'))
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['category_id'] = ($catId === '0' || $catId === null) ? null : (int) $catId;
        $wizard['step'] = 'recurrence';
        $this->chat->storage()->set('bill_wizard', $wizard);

        $this->chat->message(__('telegram.bill.choose_recurrence'))->keyboard($this->billRecurrenceKeyboard())->send();
    }

    public function bill_pick_recurrence(?string $type = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        if ($this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $type = $type ?? $this->data->get('type');
        $recurrence = BillRecurrenceType::tryFrom((string) $type);
        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (! $recurrence || ! isset($wizard['amount'], $wizard['currency'], $wizard['title'])) {
            $this->chat->message(__('telegram.bill.draft_expired'))
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['recurrence_type'] = $recurrence->value;

        if ($recurrence === BillRecurrenceType::Monthly) {
            $wizard['step'] = 'due_day';
            $this->chat->storage()->set('bill_wizard', $wizard);
            $this->chat->message(__('telegram.bill.enter_due_day'))
                ->keyboard($this->cancelToMenuKeyboard())
                ->send();

            return;
        }

        $wizard['step'] = 'due_date';
        $this->chat->storage()->set('bill_wizard', $wizard);
        $this->chat->message(__('telegram.bill.enter_due_date'))
            ->keyboard($this->cancelToMenuKeyboard())
            ->send();
    }

    public function bill_pick_limit(?string $type = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user || $this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $type = $type ?? $this->data->get('type');
        $limitType = BillRecurrenceLimitType::tryFrom((string) $type);
        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (! $limitType || ($wizard['step'] ?? null) !== 'recurrence_limit') {
            $this->chat->message(__('telegram.bill.draft_expired'))
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['recurrence_limit_type'] = $limitType->value;

        if ($limitType === BillRecurrenceLimitType::Count) {
            $wizard['step'] = 'recurrence_count';
            $this->chat->storage()->set('bill_wizard', $wizard);
            $this->chat->message(__('telegram.bill.enter_recurrence_count'))
                ->keyboard($this->cancelToMenuKeyboard())
                ->send();

            return;
        }

        if ($limitType === BillRecurrenceLimitType::Date) {
            $wizard['step'] = 'recurrence_end_date';
            $this->chat->storage()->set('bill_wizard', $wizard);
            $this->chat->message(__('telegram.bill.enter_recurrence_end_date'))
                ->keyboard($this->cancelToMenuKeyboard())
                ->send();

            return;
        }

        $wizard['step'] = 'confirm';
        $this->chat->storage()->set('bill_wizard', $wizard);
        $this->sendBillConfirmation($wizard);
    }

    public function confirm_bill(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        if ($this->featureLocked($user, Feature::Bills)) {
            return;
        }

        $wizard = $this->chat->storage()->get('bill_wizard', []);

        if (empty($wizard) || ! isset($wizard['title'], $wizard['amount'], $wizard['currency'], $wizard['recurrence_type'])) {
            $this->reply(__('telegram.bill.none_active'));

            return;
        }

        $data = SaveBill::normalize([
            'title' => $wizard['title'],
            'amount' => $wizard['amount'],
            'currency' => $wizard['currency'],
            'category_id' => $wizard['category_id'] ?? null,
            'recurrence_type' => $wizard['recurrence_type'],
            'due_day_of_month' => $wizard['due_day_of_month'] ?? null,
            'due_date' => $wizard['due_date'] ?? null,
            'recurrence_limit_type' => $wizard['recurrence_limit_type'] ?? null,
            'recurrence_count' => $wizard['recurrence_count'] ?? null,
            'recurrence_end_date' => $wizard['recurrence_end_date'] ?? null,
            'telegram_reminder_enabled' => true,
        ], $user, isset($wizard['category_id']), true);

        try {
            $bill = app(SaveBill::class)->create(
                $user,
                $data,
                FrontendLocalization::normalizeCalendar($user->calendar),
            );
        } catch (ValidationException $exception) {
            $this->chat->message($exception->validator->errors()->first())
                ->keyboard($this->cancelToMenuKeyboard())
                ->send();

            return;
        }

        $this->chat->storage()->forget('bill_wizard');
        $this->chat->message(__('telegram.bill.saved', ['title' => $bill->title]))->keyboard($this->mainKeyboard())->send();
    }

    public function cancel_bill(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if ($user && $this->featureLocked($user, Feature::TelegramBot)) {
            $this->clearActiveWizards();

            return;
        }

        $this->clearActiveWizards();
        $this->chat->message(__('telegram.menu.cancelled'))->keyboard($this->mainKeyboard())->send();
    }

    public function pick_category(?string $cat_id = null): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $catId = $cat_id ?? $this->data->get('cat_id');
        $wizard = $this->chat->storage()->get('wizard', []);

        if (! isset($wizard['amount'], $wizard['currency'], $wizard['type'])) {
            $this->chat->message(__('telegram.transaction.draft_expired'))
                ->keyboard($this->mainKeyboard())
                ->send();

            return;
        }

        $wizard['category_id'] = $catId;
        $wizard['step'] = 'title';
        $this->chat->storage()->set('wizard', $wizard);

        $this->chat->message(__('telegram.transaction.enter_title'))
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

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $currency = $currency ?? $this->data->get('currency');
        $currency = Currency::tryFrom((string) $currency);
        $wizard = $this->chat->storage()->get('wizard', []);

        if (! $currency || ! isset($wizard['amount'], $wizard['type'])) {
            $this->chat->message(__('telegram.transaction.draft_expired'))
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

        $this->chat->message(__('telegram.shared.choose_category'))
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

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $wizard = $this->chat->storage()->get('wizard', []);

        if (empty($wizard)) {
            $this->reply(__('telegram.transaction.none_active'));

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
        $this->chat->message(__('telegram.transaction.saved'))->keyboard($this->mainKeyboard())->send();
    }

    public function cancel_tx(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if ($user && $this->featureLocked($user, Feature::TelegramBot)) {
            $this->clearActiveWizards();

            return;
        }

        $this->clearActiveWizards();
        $this->chat->message(__('telegram.menu.cancelled'))->keyboard($this->mainKeyboard())->send();
    }

    public function cancel_current(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if ($user && $this->featureLocked($user, Feature::TelegramBot)) {
            $this->clearActiveWizards();

            return;
        }

        $this->clearActiveWizards();
        $this->chat->message(__('telegram.menu.cancelled_choose_action'))->keyboard($this->mainKeyboard())->send();
    }

    private function linkAccount(string $token): void
    {
        $user = User::query()->where('telegram_connect_token', $token)->first();

        if (! $user) {
            $this->chat->message(__('telegram.link.invalid_token'))->send();

            return;
        }

        $user->update([
            'telegram_chat_id' => (string) $this->chat->chat_id,
            'telegram_connect_token' => null,
        ]);

        $this->resolvedUser = $user->refresh();
        $this->userResolved = true;
        $this->applyUserLocale();

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $this->chat->message(__('telegram.link.success', ['name' => $user->name]))
            ->keyboard($this->mainKeyboard())
            ->send();
    }

    private function resolveUser(): ?User
    {
        if (! $this->userResolved) {
            $this->resolvedUser = User::query()->where('telegram_chat_id', (string) $this->chat->chat_id)->first();
            $this->userResolved = true;
            $this->applyUserLocale();
        }

        return $this->resolvedUser;
    }

    /**
     * A webhook carries no session, so nothing has set a locale for this request.
     * The account behind the chat id is the only thing that knows which language
     * the reply belongs in, so apply it the moment that account is known — before
     * any message or button label is built.
     */
    private function applyUserLocale(): void
    {
        app()->setLocale($this->locale());
    }

    private function locale(): string
    {
        return FrontendLocalization::normalizeLocale($this->resolvedUser?->locale);
    }

    /**
     * Replies with a "switch this on first" message when the module is off.
     *
     * Telegram keyboards persist in old messages and callbacks can arrive long
     * after a module was disabled, so filtering the keyboard is not enough on its
     * own — every entry point needs this guard too.
     */
    private function featureLocked(User $user, Feature $feature): bool
    {
        if ($feature !== Feature::TelegramBot && ! $user->hasFeature(Feature::TelegramBot)) {
            return $this->featureLocked($user, Feature::TelegramBot);
        }

        if ($user->hasFeature($feature)) {
            return false;
        }

        $this->chat
            ->message(__('modules.telegram_locked', ['module' => $feature->label()]))
            ->keyboard($this->mainKeyboard())
            ->send();

        return true;
    }

    private function sendNotLinked(): void
    {
        $this->chat->message(__('telegram.link.not_linked'))->send();
    }

    private function sendHelp(User $user): void
    {
        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $this->chat->message(__('telegram.menu.greeting', ['name' => $user->name]))
            ->keyboard($this->mainKeyboard())
            ->send();
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

        if ($this->featureLocked($user, Feature::TelegramBot)) {
            return;
        }

        $this->chat->message($reportBuilder($user))->keyboard($this->mainKeyboard())->send();
    }

    private function mainKeyboard(): Keyboard
    {
        $user = $this->resolveUser();

        if ($user && ! $user->hasFeature(Feature::TelegramBot)) {
            return Keyboard::make();
        }

        $buttons = [
            Button::make(__('telegram.buttons.add_cost'))->action('add_cost')->width(0.5),
            Button::make(__('telegram.buttons.add_income'))->action('add_income')->width(0.5),
            Button::make(__('telegram.buttons.list_transactions'))->action('list')->width(0.5),
        ];

        if ($user?->hasFeature(Feature::Investments)) {
            $buttons[] = Button::make(__('telegram.buttons.add_investment'))->action('add_investment')->width(0.5);
        }

        if ($user?->hasFeature(Feature::Bills)) {
            $buttons[] = Button::make(__('telegram.buttons.add_bill'))->action('add_bill')->width(0.5);
            $buttons[] = Button::make(__('telegram.buttons.list_bills'))->action('list_bills')->width(0.5);
        }

        if ($user?->hasFeature(Feature::Portfolio)) {
            $buttons[] = Button::make(__('telegram.buttons.portfolio'))->action('portfolio')->width(0.5);
        }

        if ($user?->hasFeature(Feature::Budgets)) {
            $buttons[] = Button::make(__('telegram.buttons.budget'))->action('budget')->width(0.5);
        }

        if ($user?->hasFeature(Feature::Gamification)) {
            $buttons[] = Button::make(__('telegram.buttons.no_spend'))->action('no_spend')->width(0.5);
        }

        // Reports are core — always offered.
        $buttons[] = Button::make(__('telegram.buttons.report_daily'))->action('report_daily')->width(0.5);
        $buttons[] = Button::make(__('telegram.buttons.report_weekly'))->action('report_week')->width(0.5);
        $buttons[] = Button::make(__('telegram.buttons.report_monthly'))->action('report_month')->width(0.5);

        return Keyboard::make()->buttons($buttons);
    }

    private function currencyKeyboard(): Keyboard
    {
        return $this->currencyKeyboardFor('pick_currency');
    }

    private function billCurrencyKeyboard(): Keyboard
    {
        return $this->currencyKeyboardFor('bill_pick_currency');
    }

    private function invCurrencyKeyboard(): Keyboard
    {
        return $this->currencyKeyboardFor('inv_pick_currency');
    }

    /**
     * Currency names come from the shared finance strings the web app uses, so a
     * Persian user is offered "تومان" here and on the transactions page alike.
     */
    private function currencyKeyboardFor(string $action): Keyboard
    {
        return $this->withCancelToMenu(Keyboard::make()->buttons(
            collect(Currency::cases())
                ->map(fn (Currency $currency): Button => Button::make(__("finance.currencies.{$currency->value}"))
                    ->action($action)
                    ->param('currency', $currency->value)
                    ->width(1 / 3))
        ));
    }

    private function billRecurrenceKeyboard(): Keyboard
    {
        return $this->withCancelToMenu(
            Keyboard::make()
                ->button(__('telegram.buttons.monthly'))->action('bill_pick_recurrence')->param('type', BillRecurrenceType::Monthly->value)->width(0.5)
                ->button(__('telegram.buttons.one_time'))->action('bill_pick_recurrence')->param('type', BillRecurrenceType::OneTime->value)->width(0.5)
        );
    }

    private function billRecurrenceLimitKeyboard(): Keyboard
    {
        return $this->withCancelToMenu(
            Keyboard::make()
                ->button(__('telegram.buttons.limit_infinite'))->action('bill_pick_limit')->param('type', BillRecurrenceLimitType::Infinite->value)->width(1)
                ->button(__('telegram.buttons.limit_count'))->action('bill_pick_limit')->param('type', BillRecurrenceLimitType::Count->value)->width(0.5)
                ->button(__('telegram.buttons.limit_date'))->action('bill_pick_limit')->param('type', BillRecurrenceLimitType::Date->value)->width(0.5)
        );
    }

    private function cancelToMenuKeyboard(): Keyboard
    {
        return $this->withCancelToMenu(Keyboard::make());
    }

    private function withCancelToMenu(Keyboard $keyboard): Keyboard
    {
        return $keyboard
            ->button(__('telegram.buttons.cancel_to_menu'))
            ->action('cancel_current')
            ->width(1);
    }

    private function withBackToMenu(Keyboard $keyboard): Keyboard
    {
        return $keyboard
            ->button(__('telegram.buttons.back_to_menu'))
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
        $formatted = match ($currency) {
            Currency::Toman => number_format((int) round($amount), 0, '.', ','),
            Currency::Usd, Currency::Eur => number_format($amount, 2, '.', ','),
        };

        return __("telegram.amount.{$currency->value}", ['amount' => $formatted]);
    }

    private function deleteKeyboardIfCallback(): void
    {
        if (! isset($this->callbackQueryId)) {
            return;
        }

        $this->deleteKeyboard();
    }
}
