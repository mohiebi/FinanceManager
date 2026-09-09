# CashPilot

CashPilot is a Laravel and Inertia Vue personal finance app for tracking daily cash flow, categories, bills, budgets, investments, savings goals, and portfolio value. It supports English, Persian, and German locales with Jalali/Gregorian calendar preferences, multi-currency display (toman/USD/EUR), a Telegram bot for reports and reminders, an MCP server so AI assistants (Claude, Codex/ChatGPT) can read and — with explicit approval — write finance data, an AI portfolio advisor, and an optional client-side encryption vault for people who want the server to never see their real numbers.

Two currencies run alongside the money being tracked. **Miles** are earned by using the app — never by how much money moves — and are spent on unlocking optional modules, protecting a streak, and paying for advisor work. **Pro** is a prepaid span of access bought with crypto; the same checkout sells Miles packs outright.

The app has an aviation streak running through it: the currency is Miles and ranks go Cadet → Pilot → Captain. The heavier flight naming — a budget as the "Flight plan", the streak as the "Flight log" — is a per-user preference that ships **off**, switched on from Settings → Preferences. Plain names are what a new account sees.

## Stack

- Laravel 13 with PHP 8.4
- Inertia Laravel v3 and Vue 3
- Tailwind CSS v4 with local Vue UI components
- Laravel Fortify and Sanctum for web/API authentication, Laravel Socialite for Google login
- Laravel Passport for OAuth (backs the MCP server's `/mcp/finance` endpoint)
- Laravel MCP for the AI assistant integration, Laravel AI for the portfolio advisor's agents
- No third-party billing SDK — crypto payments are read straight from an EVM JSON-RPC node, with Etherscan as an optional second pair of eyes
- Laravel Wayfinder for typed route helpers
- Defstudio/Telegraph for the Telegram bot
- Morilog/Jalali for Jalali calendar conversion, PhpOffice/PhpSpreadsheet for CSV import parsing
- Resend for outbound email
- Pest for backend tests
- Vite, TypeScript, ESLint, and Prettier for frontend tooling

## Feature Modules

Every area beyond the core (Transactions, Reports) is an independently toggleable module (`App\Enums\Feature`), switched per user from Settings → Modules or from the Miles hub. A disabled module can still show as a greyed "promo" card in the nav to invite discovery, unless the user hid it outright.

Switching a module on for the **first** time costs a one-time 25 Miles (`miles.unlock_price`) for everything in `miles.paid_modules`. The unlock is permanent and recorded in `user_feature_unlocks`, so switching that module off and back on later is free — turning a module off is a display choice, not a refund.

| Module | Default | Notes |
|---|---|---|
| Transactions, Reports | Always on | Core — never rendered as a toggle, never costs Miles |
| Flight log (Gamification) | On | Free. Owns `/flight-log`, which redirects into the Miles hub while the Miles UI is on |
| AI Portfolio Advisor | On | Free today, and the module Pro is designed to buy. On by default so a future purchase lands on a working page rather than a settings switch; a user without the entitlement gets the paywall instead of a locked row |
| Bills | Off | 25 Miles. Requires Transactions |
| Flight plan (Budgets) | Off | 25 Miles. Requires Transactions |
| Investments | Off | 25 Miles |
| Portfolio, Savings goals | Off | 25 Miles each. Both require Investments (progress and net worth are both built from holdings) |
| AI Assistant | Off | 25 Miles. Conflicts with the Vault — an MCP call has no browser to ask for a passphrase |
| Telegram Bot | Off | 25 Miles. Conflicts with the Vault for the same reason |
| Private vault | Off | Free, and self-managed from Settings → Security; arming/disarming wraps or unwraps the user's real encryption key client-side, so it can't be flipped by a plain settings PATCH |

Bills and Portfolio used to conflict with the vault as well. Both now seal and compute client-side, so neither needs a server that can read them.

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
- **Flight log (Gamification)** — daily logging streaks with a weekly grace allowance, no-spend-day markers, fourteen milestones ("moments" — first transaction, first bill, first full month, three/fourteen/thirty-day runs, both rank promotions, …), pilot ranks earned by days recorded (Cadet from day one, Pilot at 30, Captain at 180), and a monthly "Logbook" completeness ring (days covered, uncategorised count, bills reconciled). Every figure here is about the record, never about how much money moved — which is what makes a rank safe to show at all.
- **Miles** — the in-app currency, earned only by using the app. A 150-Mile welcome grant, a seven-day claim ladder (3/3/3/4/4/5/8, resetting on a break), +2 for a day with any activity, milestone and referral awards. Spent on module unlocks (25 each), streak freezes (40, up to two held) and streak repairs (100, within seven days, twice a month). The hub at `/miles` merges the balance, the claim ladder, the flight log's run and logbook, ranks, moments, protections, unlocks, and a paginated ledger of every credit and debit. Cosmetics (callsign, badge, palette, theme) and Miles gifting are built and priced but ship switched off — nothing renders a cosmetic yet, and there is no friend list to gift within.
- **Referrals** — every account has an invite link (`/invite/{code}`). A referral pays out in three stages as the friend actually sticks: activated (3 days logged within 14), retained (7 within 30), and habit (30 within 90), paying the referrer 25/25/50 and the friend 15 each time, under a rolling 1,000-Mile cap. Nothing pays on signup alone.
- **AI Portfolio Advisor** — an investor assessment (risk capacity, horizon, constraints) scored into a persisted profile and persona, then AI-generated portfolio recommendations in rebalance or target-only mode, with clarification rounds, a follow-up consultation thread, and validation of every proposal against the user's real holdings before it is shown. Generation runs in a queued job so the tab can be closed; with the vault armed the browser collects and seals the result within a short window. Advisor work is priced in Miles — first recommendation 175 and later ones 250, guidance 80, a consultation message 15, and an assessment 25 only when another was completed in the last 30 days, so the first one and one re-take a month are free. The charging switch (`MILES_ADVISOR_CHARGING`) is off by default: the prices are quoted and metered through `service_usage_events`, but nothing is actually deducted until it is turned on.
- **Private vault** — an opt-in, zero-knowledge mode: the browser derives a data key from the user's passphrase, encrypts transaction/investment/bill fields before they ever leave the client, and the server stores and returns only ciphertext. Portfolio and budget math run in the browser against the decrypted values so those pages keep working while armed.
- **In-app notifications** — a notification center (mark one or all as read) for bill reminders and milestones, plus per-user preference toggles for the evening streak nudge and the bills advance reminder.
- **Telegram Bot** — account linking plus daily/weekly/monthly report delivery, bill reminders, and an evening streak nudge.
- **AI Assistant (MCP)** — a Laravel MCP server (`/mcp/finance`) exposing read tools (list transactions, categories, bills, investments, savings goals; portfolio and spending summaries; budget progress) and a propose-then-approve write flow: an AI client proposes a change, the user reviews a diff in chat, and only an explicit confirmation calls `apply-finance-changes` to commit it. Settings → AI connections lists connected assistants (with per-token revoke) and a full change history of every proposal and its confirmed/rejected/expired outcome.
- **Admin analytics** — a customer-growth and product-adoption dashboard for admin accounts, with CSV export, a per-customer drawer, and Miles-economy figures (claims, spend, completed cycles) alongside the finance ones.
- **Subscriptions & Miles packs** — Pro is a prepaid span (monthly $5, quarterly $13, yearly $45) paid in crypto and verified on-chain; the same checkout sells Miles outright (500/$5, 1,200/$10, 2,600/$20, 7,000/$50). Coupons can discount or fully cover either. Settings → Billing holds the plans, the packs, payment state, and proof submission; `/admin/billing` is the operator side — approve, re-check or reject a payment, mint and disable coupons, grant or revoke Pro by hand. See [Subscriptions](#subscriptions) for the rules that make on-chain verification safe.
- **Localization & appearance** — English, Persian, and German UI copy; Gregorian and Jalali calendars; toman/USD/EUR currency display switchable from the header on any page; light/dark/system theme; an amount mask that blurs figures on screen for shoulder-surfing (independent of the vault, which decides whether a value is readable at all); compact figures for large totals; and the opt-in flight terminology.

## Application Routes

Web routes (selected):

- `/`, `/{locale}`, `/sitemap.xml` — the landing page, its per-locale entry points, and the SEO sitemap
- `/invite/{code}` — a referral link; attributed on signup, paid out only as the friend keeps logging
- `/dashboard` — finance dashboard
- `/transactions`, `/transactions/imports/preview`, `/transactions/imports`, `/transactions/export` — transaction CRUD, CSV import, and export
- `/reports` — finance reports
- `/investments`, `/investments/export`, `/investments/sell` — investment entries and disposals
- `/portfolio`, `/portfolio/export` — portfolio summary
- `/goals`, `/savings-goals` — savings goals
- `/budgets` — Flight plan
- `/bills`, `/bills/{bill}/occurrences/{occurrence}/pay` — bills and occurrence payment
- `/miles`, `/miles/claim`, `/miles/protections/{freezes,repairs}`, `/miles/cosmetics/{cosmetic}`, `/miles/gifts` — the Miles hub and everything spendable
- `/flight-log`, `/no-spend-days` — the standalone flight log (redirects into the hub while the Miles UI is on) and the no-spend marker
- `/advisor`, `/advisor/assessment/{assessment}`, `/advisor/profile`, `/advisor/recommendations/{recommendation}` — the assessment, the derived profile, and recommendations with their clarification, consultation, and sealing endpoints
- `/admin`, `/admin/customers/export`, `/admin/billing` — admin analytics and the billing console (admin accounts only)
- `/settings/*` — profile, security (password, 2FA, sessions), appearance, preferences, notifications, modules, categories, assets, billing, vault, Telegram, and AI connections
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
- `mile_wallets` hold the balance, `mile_ledger_entries` every credit and debit that produced it (idempotency-keyed, each carrying the balance after it), and `mile_days` one row per claim day. `user_feature_unlocks` record a module bought once and owned for good; `streak_protections` hold freezes and repairs; `referrals` and `referral_rewards` track an invited friend and each stage paid; `user_cosmetics` what has been bought; `service_usage_events` what a metered advisor call actually cost.
- `subscription_payments`, `subscription_grants`, `coupons`, and `coupon_redemptions` hold billing. `users.pro_until` is the entitlement itself.
- `investor_assessments` and `investor_assessment_answers` hold the questionnaire, `advisor_profiles` the scored result, `advisor_recommendations` each generated proposal and its status, and `advisor_messages` the consultation thread.
- `user_encryption_keys` hold the wrapped data key for vault users; plaintext fields are never derivable server-side while armed.
- `mcp_proposals` hold the diff for a pending AI-proposed change until the user confirms or rejects it.
- Supported transaction currencies are `toman`, `usd`, and `eur`. Supported transaction types are `cost` and `income`.

Important backend files:

- `app/Actions/Auth/EmailAuthBroker.php`, `app/Actions/Auth/GoogleAuthBroker.php`
- `app/Actions/Transactions/SaveTransaction.php`, `app/Actions/Transactions/ImportTransactions.php`, `app/Actions/Transactions/CurrencyConverter.php`
- `app/Actions/Bills/SaveBill.php`, `app/Actions/Investments/BuildPortfolioBreakdown.php`
- `app/Jobs/BillReminderJob.php`, `app/Jobs/StreakReminderJob.php`, `app/Jobs/RefreshAssetPricesJob.php`
- `app/Jobs/VerifySubscriptionPaymentJob.php`, `app/Jobs/ReconcileSubscriptionsJob.php`, `app/Jobs/ReconcileMileWalletsJob.php`, `app/Jobs/GenerateAdvisorRecommendationJob.php`
- `app/Actions/Miles/*` — the whole Miles economy: `AdjustMiles` is the only writer of a balance, `ClaimDailyMiles` the ladder, `ActivateUserFeature` the unlock, `EvaluateReferralRewards` the staged payout
- `app/Actions/Billing/*` — `StartSubscriptionPayment`, `VerifyPaymentOnChain`, `GrantProAccess` / `RevokeProAccess`, `CreditPurchasedMiles`, and the coupon flow
- `app/Services/Advisor/*` — assessment scoring, profile building, AI context, proposal validation, and rebalancing maths
- `app/Enums/Feature.php` — the module toggle system
- `config/miles.php` — every Miles price, reward, and cap in one file; `config/billing.php` and `config/advisor.php` do the same for their areas
- `app/Support/Encryption/*` — the vault's key wrapping and sealed-field handling
- `app/Mcp/Servers/FinanceServer.php` and `app/Mcp/Tools/*` — the AI assistant's tool surface
- `app/Http/Controllers/{Transaction,Bill,Budget,Investment,Portfolio,Goal,Report,AdminDashboard}Controller.php`
- `app/Services/AssetPriceService.php`, `app/Services/TelegramReportService.php`

Important frontend files:

- `resources/js/pages/Landing.vue`, `Dashboard.vue`, `Transactions.vue`, `Report.vue`
- `resources/js/pages/Investments.vue`, `Portfolio.vue`, `Bills.vue`, `Budgets.vue`, `Goals.vue`
- `resources/js/pages/Miles/Index.vue` and `FlightLog.vue` — the Miles hub and its pre-Miles fallback
- `resources/js/pages/Advisor/*.vue` (Index, Assessment, Profile, Recommendation, Paywall)
- `resources/js/components/Miles*.vue` — the header pill, the claim celebration, the shortfall prompt, and the shared Miles mark
- `resources/js/pages/admin/Dashboard.vue`
- `resources/js/pages/settings/*.vue` (Modules, Billing, AiConnections, Telegram, Notifications, Preferences, Security, …)
- `resources/js/composables/useModuleNav.ts` — the primary navigation, shared by the sidebar and mobile menu
- `resources/js/composables/useNavigationNaming.ts` — resolves one visible name per section so standard and flight terminology never mix
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

Run the frontend test suite (plain `node --test`, no browser):

```bash
npm.cmd run test:js
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
- Anything that must still work with the vault armed (Portfolio, Budgets, Savings goals, Bills) resolves its numbers client-side from decrypted values rather than assuming the server can read them; routes that genuinely require server-side plaintext sit behind `RejectWhenVaultArmed`.
- **Miles recognise actions and consistency, never amounts.** No reward may be a function of a balance, a transaction's size, or net worth — `config('miles.never_reward_financial_amounts')` states the rule and the ranks/moments copy repeats it to the user. A leaderboard of who has more money is the thing this design exists to avoid.
- Every balance change goes through `AdjustMiles` with an idempotency key, inside a transaction that locks the wallet row. Nothing else writes `mile_wallets`, and `ReconcileMileWalletsJob` re-derives each balance from the ledger to prove it.
- Keep entitlement (`mayUse`, Pro) separate from enablement (the user's own switch) and from ownership (`user_feature_unlocks`). The three answer different questions, and collapsing any two of them is how a paid feature ends up free or a bought unlock ends up re-charged.

### Subscriptions

Pro accounts are prepaid spans of access bought with crypto. Nothing renews itself — a crypto
payment cannot be taken a second time — so every plan extends the buyer's current expiry and the
expiry reminder is load-bearing rather than decorative. The same checkout also sells Miles packs,
which are not access at all: they credit the wallet through `CreditPurchasedMiles` and expire never.

- **No module is Pro yet.** `Feature::tier()` returns `Free` for every case, and `ProEntitlementTest`
  asserts it, so making a feature paid is a deliberate one-line change rather than an accident. The
  Advisor is the one it is written for.
- **A Miles pack is a purchase, not an entitlement.** It grants no access and touches no
  `pro_until`; it credits the ledger like any other award, so a refund, a re-check, or a coupon
  settling late all reconcile through the same wallet.
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
