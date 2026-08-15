# CashPilot

CashPilot is a Laravel and Inertia Vue personal finance app for tracking daily cash flow, categories, bills, budgets, investments, savings goals, and portfolio value. It supports English, Persian, and German locales with Jalali/Gregorian calendar preferences, multi-currency display (toman/USD/EUR), a Telegram bot for reports and reminders, an MCP server so AI assistants (Claude, Codex/ChatGPT) can read and — with explicit approval — write finance data, and an optional client-side encryption vault for people who want the server to never see their real numbers.

The app leans lightly on an aviation/pilot theme (it's literally "CashPilot") — a habit-tracking streak is the "Flight log", a budget is a "Flight plan", ranks go Cadet → Pilot → Captain, and a few nav sections carry a small flight-themed aside next to their plain name.

## Stack

- Laravel 13 with PHP 8.4
- Inertia Laravel v3 and Vue 3
- Tailwind CSS v4 with local Vue UI components
- Laravel Fortify and Sanctum for web/API authentication, Laravel Socialite for Google login
- Laravel Passport for OAuth (backs the MCP server's `/mcp/finance` endpoint)
- Laravel MCP for the AI assistant integration, Laravel AI for AI-adjacent tooling
- Laravel Wayfinder for typed route helpers
- Defstudio/Telegraph for the Telegram bot
- Morilog/Jalali for Jalali calendar conversion, PhpOffice/PhpSpreadsheet for CSV import parsing
- Resend for outbound email
- Pest for backend tests
- Vite, TypeScript, ESLint, and Prettier for frontend tooling

## Feature Modules

Every area beyond the core (Transactions, Reports) is an independently toggleable module (`App\Enums\Feature`), switched per user from Settings → Modules. A disabled module can still show as a greyed "promo" card in the nav to invite discovery, unless the user hid it outright.

| Module | Default | Notes |
|---|---|---|
| Transactions, Reports | Always on | Core — never rendered as a toggle |
| Flight log (Gamification) | On | The only optional module on by default — it has no page of its own to discover otherwise |
| Bills | Off | Requires Transactions |
| Flight plan (Budgets) | Off | Requires Transactions |
| Investments | Off | — |
| Portfolio, Savings goals | Off | Both require Investments (progress and net worth are both built from holdings) |
| AI Assistant | Off | Conflicts with the Vault — an MCP call has no browser to ask for a passphrase |
| Telegram Bot | Off | Conflicts with the Vault for the same reason |
| Private vault | Off | Self-managed from Settings → Security; arming/disarming wraps or unwraps the user's real encryption key client-side, so it can't be flipped by a plain settings PATCH |

## Main Features

- **Authentication** — email-first signup, login, password recovery, email verification, and Google login, plus a profile-completion flow (birthdate, locale, calendar, security, Telegram).
- **Security** — password management, optional two-factor authentication (QR setup, recovery codes, optional password-confirm-to-disable), and the private vault toggle.
- **Transactions** — a shared cost/income ledger with category assignment, bulk delete/recategorize, CSV export, and an AI-assisted CSV import flow for bank statements (Persian digit and Jalali date normalization, Rial→Toman conversion, duplicate detection).
- **Categories** — user-owned and default categories, with type/category consistency enforced by the model.
- **Reports** — time-sliced (month/season/year/custom) cash-flow and category breakdowns.
- **Bills** — recurring or one-time bills with occurrence tracking, mark-as-paid (which records a matching transaction), and Telegram reminders: an unconditional due-day reminder plus an opt-in "N days before" advance reminder, each configurable per bill with its own reminder time and timezone (defaulting to the user's account timezone).
- **Flight plan (Budgets)** — rule-based spending allowances (fixed amount, share of income, or "whatever's left"), resolved client-side so they still work with the vault armed.
- **Investments & Portfolio** — buy/sell entries across gold, silver, USD, EUR, coin, bitcoin, and fully custom user-defined assets (Settings → Assets), each priced from a manual value, a formula, or a live JSON/XML endpoint; a portfolio overview with net worth, cost basis, realised/unrealised P&L, an allocation donut, a value-over-time chart, and per-asset summary cards.
- **Savings goals** — targets denominated in the asset actually being saved (grams of gold, dollars — never toman), with on-track/behind-pace progress and a reached-goals archive.
- **Flight log (Gamification)** — daily logging streaks with a weekly grace allowance, no-spend-day markers, milestones (first transaction, 100th transaction, first full month, 30-day run), pilot ranks (Cadet/Pilot/Captain), and a monthly "Logbook" completeness ring (days covered, uncategorised count, bills reconciled).
- **Private vault** — an opt-in, zero-knowledge mode: the browser derives a data key from the user's passphrase, encrypts transaction/investment/bill fields before they ever leave the client, and the server stores and returns only ciphertext. Portfolio and budget math run in the browser against the decrypted values so those pages keep working while armed.
- **In-app notifications** — a notification center (mark one or all as read) for bill reminders and milestones, plus per-user preference toggles for the evening streak nudge and the bills advance reminder.
- **Telegram Bot** — account linking plus daily/weekly/monthly report delivery, bill reminders, and an evening streak nudge.
- **AI Assistant (MCP)** — a Laravel MCP server (`/mcp/finance`) exposing read tools (list transactions, categories, bills, investments, savings goals; portfolio and spending summaries; budget progress) and a propose-then-approve write flow: an AI client proposes a change, the user reviews a diff in chat, and only an explicit confirmation calls `apply-finance-changes` to commit it. Settings → AI connections lists connected assistants (with per-token revoke) and a full change history of every proposal and its confirmed/rejected/expired outcome.
- **Admin analytics** — a customer-growth and product-adoption dashboard for admin accounts, with CSV export.
- **Localization & appearance** — English, Persian, and German UI copy; Gregorian and Jalali calendars; toman/USD/EUR currency display switchable from the header on any page; light/dark/system theme.

## Application Routes

Web routes (selected):

- `/` — landing page
- `/dashboard` — finance dashboard
- `/transactions`, `/transactions/imports/preview`, `/transactions/imports`, `/transactions/export` — transaction CRUD, CSV import, and export
- `/reports` — finance reports
- `/investments`, `/investments/export`, `/investments/sell` — investment entries and disposals
- `/portfolio`, `/portfolio/export` — portfolio summary
- `/goals`, `/savings-goals` — savings goals
- `/budgets` — Flight plan
- `/bills`, `/bills/{bill}/occurrences/{occurrence}/pay` — bills and occurrence payment
- `/admin`, `/admin/customers/export` — admin analytics (admin accounts only)
- `/settings/*` — profile, security (password, 2FA), appearance, preferences, notifications, modules, categories, assets, vault, Telegram, and AI connections
- `/mcp/finance` — the MCP server endpoint, plus its OAuth routes

API routes:

- `/api/auth/*` — email, password, Google, recovery, signup, and session endpoints
- `/api/profile` — authenticated profile update
- `/api/transactions` — authenticated transaction API resource
- `/api/categories` — authenticated category index

## Core Domain

- `users` own transactions, categories, investments, bills, budgets, savings goals, social accounts, per-feature toggles (`user_features`), streak/milestone state, and Telegram/vault key material.
- `categories` may be global defaults or user-owned custom categories.
- `transactions` store type, category, amount, currency, title, description, and occurred date — encrypted at rest when the vault is armed.
- `investments` store asset type, quantity, cost basis, sale price, currency, note, and occurred date; a disposal is a second row with a negative quantity, never an edit of the purchase. `investment_assets` define the sluggable asset itself (label, icon, color, unit) and, for custom assets, how its price is fetched (manual, formula, JSON, or XML).
- `bills` and `bill_occurrences` track recurrence, due dates, paid state, and per-reminder sent timestamps.
- `budgets` and `budget_lines` hold rule-based allowances.
- `savings_goals` hold asset-denominated targets and reached state.
- `user_streaks`, `user_milestones`, and `no_spend_days` back the Flight log.
- `user_encryption_keys` hold the wrapped data key for vault users; plaintext fields are never derivable server-side while armed.
- `mcp_proposals` hold the diff for a pending AI-proposed change until the user confirms or rejects it.
- Supported transaction currencies are `toman`, `usd`, and `eur`. Supported transaction types are `cost` and `income`.

Important backend files:

- `app/Actions/Auth/EmailAuthBroker.php`, `app/Actions/Auth/GoogleAuthBroker.php`
- `app/Actions/Transactions/SaveTransaction.php`, `app/Actions/Transactions/ImportTransactions.php`, `app/Actions/Transactions/CurrencyConverter.php`
- `app/Actions/Bills/SaveBill.php`, `app/Actions/Investments/BuildPortfolioBreakdown.php`
- `app/Jobs/BillReminderJob.php`, `app/Jobs/StreakReminderJob.php`, `app/Jobs/RefreshAssetPricesJob.php`
- `app/Enums/Feature.php` — the module toggle system
- `app/Support/Encryption/*` — the vault's key wrapping and sealed-field handling
- `app/Mcp/Servers/FinanceServer.php` and `app/Mcp/Tools/*` — the AI assistant's tool surface
- `app/Http/Controllers/{Transaction,Bill,Budget,Investment,Portfolio,Goal,Report,AdminDashboard}Controller.php`
- `app/Services/AssetPriceService.php`, `app/Services/TelegramReportService.php`

Important frontend files:

- `resources/js/pages/Landing.vue`, `Dashboard.vue`, `Transactions.vue`, `Report.vue`
- `resources/js/pages/Investments.vue`, `Portfolio.vue`, `Bills.vue`, `Budgets.vue`, `Goals.vue`
- `resources/js/pages/admin/Dashboard.vue`
- `resources/js/pages/settings/*.vue` (Modules, AiConnections, Telegram, Notifications, Preferences, Security, …)
- `resources/js/composables/useModuleNav.ts` — the primary navigation, shared by the sidebar and mobile menu
- `resources/js/composables/useVaultPortfolio.ts` / `useVault.ts` — client-side decryption and vault-aware math
- `resources/js/components/ui/*`, `resources/js/components/charts/*`

## Transaction CSV Import

The Transactions page includes an import flow for bank reports. Users copy the bilingual AI prompt, attach their bank report to an AI tool, ask for a downloadable `transactions.csv` file, then upload that CSV back into the app for validation, preview, and import.

The CSV header must stay in English:

```csv
occurred_at,type,category,amount,currency,title,description
```

Example:

```csv
occurred_at,type,category,amount,currency,title,description
2026-06-17,cost,Food,450000,toman,Lunch,Restaurant payment
1403/03/27,income,Salary,120000000,toman,Payroll,Monthly salary
```

Import behavior:

- Uploaded values may be English or Persian, but headers must match the required English header.
- Persian and Arabic digits are normalized before validation.
- Jalali dates such as `1403/03/27` are converted to Gregorian dates before storage.
- Persian and English transaction types are normalized to `cost` or `income`.
- Rial amounts are divided by 10 and stored as `toman`; the preview shows a note for that conversion.
- Default category aliases map to the app's common categories.
- Unknown categories are created as custom categories for the importing user only when the user confirms the import.
- Duplicates are detected by user, date, type, amount, currency, and title. They appear in preview and are skipped by default, then re-checked before final import.

## Local Setup

Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Run migrations:

```bash
php artisan migrate
```

Build frontend assets:

```bash
npm run build
```

For active frontend development:

```bash
npm run dev
```

The Composer `setup` script runs the common first-time setup path:

```bash
composer run setup
```

## Quality Checks

Run the targeted finance feature tests:

```bash
php artisan test --compact tests\Feature\TransactionControllerTest.php tests\Feature\TransactionImportTest.php tests\Feature\Api\TransactionApiTest.php tests\Feature\FinanceModelTest.php tests\Feature\DashboardTest.php tests\Feature\ReportPageTest.php tests\Feature\PreferencesTest.php tests\Feature\DisplayCurrencySelectorTest.php
```

Run formatting, linting, and type checks:

```bash
php vendor\bin\pint --dirty --format agent
npm.cmd run types:check
npm.cmd run lint:check
```

Run the full backend suite:

```bash
php artisan test --compact
```

## Notes

- Keep transaction writes behind `SaveTransaction` so web, API, and import flows share validation and category constraints.
- Keep CSV import headers stable in English, even when uploaded values are Persian.
- Do not add `rial` as a stored currency; import rows normalize Rial values to Toman.
- Custom import categories are scoped to the importing user and must not become global defaults.
- Gate any new module-specific route with `EnsureFeatureEnabled::class` and register it in `App\Enums\Feature` (label, icon, dependencies) and `useModuleNav.ts` if it owns a nav entry — a mismatch between the two is caught by `ModulesTest`.
- Anything that must still work with the vault armed (Portfolio, Budgets, Savings goals) resolves its numbers client-side from decrypted values rather than assuming the server can read them; routes that genuinely require server-side plaintext sit behind `RejectWhenVaultArmed`.

### Subscriptions

Pro accounts are prepaid spans of access bought with crypto. Nothing renews itself — a crypto
payment cannot be taken a second time — so every plan extends the buyer's current expiry and the
expiry reminder is load-bearing rather than decorative.

- **No module is Pro yet.** `Feature::tier()` returns `Free` for every case, and `ProEntitlementTest`
  asserts it, so making a feature paid is a deliberate one-line change rather than an accident.
- `users.pro_until` is the entitlement, and it is written **only** by `GrantProAccess` /
  `RevokeProAccess`, always inside a transaction that locks the user row and always alongside a
  `subscription_grants` row. It is kept out of the model's `#[Fillable]` list on purpose: a
  mass-assignment path into it would be free Pro. Expiry needs no job — `isPro()` compares against
  the clock on every read.
- **Billing tables are plaintext by design**, against the convention everywhere else. The worker
  that settles a payment has no browser and no per-user data key, so encrypting them would make a
  real payment permanently unverifiable the moment its buyer armed their vault. Never put
  `RejectWhenVaultArmed` on a billing route.
- **Token amounts are decimal strings end to end** (`App\Support\Billing\TokenAmount`). One ether is
  10^18 wei against a `PHP_INT_MAX` of ~9.2×10^18, so a cast overflows within plausible amounts, and
  the money columns are `string` rather than `decimal` because SQLite hands a DECIMAL back as a float.
  Never route these through `CurrencyConverter` or `resources/js/lib/money.ts`, which assume 2-decimal fiat.
- Each intent's expected amount carries a **per-payment nonce** in its lowest digits so that no two
  open intents ever expect the same figure, and verification matches it **exactly**. A tolerance band
  wide enough to absorb a rounding error would be wide enough to span the next intent's amount and
  settle the wrong payment.
- For ERC-20, the credited amount is read from the receipt's `Transfer` logs, not from the
  transaction's recipient — that is what makes exchange and smart-wallet withdrawals work. Checking
  `log.address` against the payment's snapshotted contract is the **entire** defence against a
  worthless token minted to look like a payment.
- **An unreachable node must never fail a payment.** Only the terminal verdicts in
  `PaymentFailureReason` set `Failed`; everything retryable leaves the payment `Submitted` for
  `ReconcileSubscriptionsJob` to retry and, failing that, for an admin to decide.
- **Etherscan is optional but worth configuring.** Ether forwarded by a contract — which most exchange
  withdrawals are — is an internal transfer: it appears in no receipt and emits no log, so a node alone
  cannot see it and every such payment would need approving by hand. With `BILLING_ETHERSCAN_API_KEY`
  set, `EtherscanClient` reads the execution trace and those settle automatically. It also stands in as
  a second endpoint when the RPC node is unreachable. It widens what we can *see*, never what counts
  as payment: the exact-amount and confirmation rules are unchanged.
- Adding a chain is a `PaymentNetwork` case plus a `config/billing.php` block. Every EVM chain shares
  `EvmJsonRpcExplorer`; only a non-EVM chain needs a new driver behind `ChainExplorer`.
- The `billing` queue must stay in `composer.json`'s `--queue=` list **and** in `docker/supervisord.conf`,
  which is the production worker list. `QueueCoverageTest` now enforces both, along with the rule that
  `queue.retry_after` stays above the longest job timeout — a shorter window hands a running job to a
  second worker and pays for the same provider call twice.
