<?php

namespace App\Telegraph;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TelegramReportService;
use Carbon\Carbon;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

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

        $this->chat->storage()->forget('inv_wizard');
        $this->chat->storage()->set('wizard', [
            'type' => 'cost',
            'step' => 'amount',
            'user_id' => $user->id,
        ]);

        $this->chat->message("*Add cost*\n\nEnter the amount, for example: 50000")->send();
    }

    public function add_income(): void
    {
        $this->deleteKeyboardIfCallback();

        $user = $this->resolveUser();

        if (! $user) {
            $this->sendNotLinked();

            return;
        }

        $this->chat->storage()->forget('inv_wizard');
        $this->chat->storage()->set('wizard', [
            'type' => 'income',
            'step' => 'amount',
            'user_id' => $user->id,
        ]);

        $this->chat->message("*Add income*\n\nEnter the amount, for example: 5000000")->send();
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

        $this->chat->storage()->forget('wizard');
        $this->chat->storage()->set('inv_wizard', ['step' => 'asset', 'user_id' => $user->id]);

        $this->chat->message("*Add investment*\n\nChoose asset type:")
            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2))
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
            ->take(10)
            ->get();

        if ($transactions->isEmpty()) {
            $this->chat->message('No transactions found.')->keyboard($this->mainKeyboard())->send();

            return;
        }

        $lines = ["*Last 10 transactions:*\n"];

        foreach ($transactions as $transaction) {
            $sign    = $transaction->type === TransactionType::Cost ? '−' : '+';
            $date    = $transaction->occurred_at->format('M j');
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
            $this->chat->message('Investment asset not found. Choose Add investment to start again.')->send();

            return;
        }

        $wizard = $this->chat->storage()->get('inv_wizard', []);
        $wizard['asset_id'] = $investmentAsset->id;
        $wizard['asset_slug'] = $investmentAsset->slug;
        $wizard['asset_label'] = $investmentAsset->label();
        $wizard['step'] = 'quantity';
        $this->chat->storage()->set('inv_wizard', $wizard);

        $this->chat->message("Enter quantity for {$investmentAsset->label()}:")->send();
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

        $this->chat->message('Choose an action:')->keyboard($this->mainKeyboard())->send();
    }

    private function handleTransactionWizard(array $wizard, string $text, User $user): void
    {
        switch ($wizard['step']) {
            case 'amount':
                if (! is_numeric($text)) {
                    $this->chat->message('Please enter a valid number.')->send();

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
                $this->chat->message('Please choose a currency using the buttons above.')->send();
                break;

            case 'category':
                $this->chat->message('Please choose a category using the buttons above.')->send();
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
                $this->chat->message('Please enter a valid positive number.')->send();

                return;
            }

            $wizard['quantity'] = (float) $text;
            $wizard['step'] = 'cost_basis';
            $this->chat->storage()->set('inv_wizard', $wizard);

            $this->chat->message('Enter cost basis per unit. Send 0 to skip.')->send();

            return;
        }

        if ($wizard['step'] === 'cost_basis') {
            $costBasis = is_numeric($text) ? (float) $text : null;

            Investment::create([
                'user_id' => $user->id,
                'investment_asset_id' => $wizard['asset_id'],
                'asset_type' => $wizard['asset_slug'],
                'quantity' => $wizard['quantity'],
                'cost_basis' => $costBasis > 0 ? $costBasis : null,
                'occurred_at' => now()->toDateString(),
            ]);

            $this->chat->storage()->forget('inv_wizard');
            $this->chat->message('Investment saved.')->keyboard($this->mainKeyboard())->send();
        }
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

        $this->chat->message('Enter a title for this transaction:')->send();
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
            ->keyboard($keyboard)
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

        $this->chat->storage()->forget('wizard');
        $this->chat->message('Cancelled.')->keyboard($this->mainKeyboard())->send();
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
            Button::make('Last transactions')->action('list')->width(0.5),
            Button::make('Add investment')->action('add_investment')->width(0.5),
            Button::make('Today report')->action('report_today')->width(1 / 3),
            Button::make('Week report')->action('report_week')->width(1 / 3),
            Button::make('Month report')->action('report_month')->width(1 / 3),
        ]);
    }

    private function currencyKeyboard(): Keyboard
    {
        return Keyboard::make()->buttons(
            collect(Currency::cases())
                ->map(fn (Currency $currency): Button => Button::make(strtoupper($currency->value))
                    ->action('pick_currency')
                    ->param('currency', $currency->value)
                    ->width(1 / 3))
        );
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
            Currency::Usd   => '$'.number_format($amount, 2, '.', ','),
            Currency::Eur   => '€'.number_format($amount, 2, '.', ','),
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
