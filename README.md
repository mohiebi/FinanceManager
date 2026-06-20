# Finance Manager

Finance Manager is a Laravel and Inertia Vue personal finance app for tracking daily cash flow, categories, investments, reports, and portfolio value. It supports English and Persian-oriented finance workflows, including calendar/currency preferences, Telegram reports, and CSV transaction imports from bank statements.

## Stack

- Laravel 13 with PHP 8.4
- Inertia Laravel v3 and Vue 3
- Tailwind CSS v4 with local Vue UI components
- Laravel Fortify and Sanctum for web/API authentication
- Laravel Wayfinder for typed route helpers
- Pest for backend tests
- Vite, TypeScript, ESLint, and Prettier for frontend tooling

## Main Features

- Email-first authentication with signup, login, recovery, password login, email verification, and Google login.
- Profile completion flow with birthdate, locale, calendar, appearance, security, and Telegram settings.
- Transaction dashboard for costs and incomes with shared validation across web and API flows.
- User-owned and default categories, with transaction category/type consistency enforced by the model.
- Reports page for finance summaries and charts.
- Investments and portfolio pages for assets such as gold, silver, USD, EUR, coin, and bitcoin.
- Cached asset price service backed by TGJU sources, with graceful fallback when prices are unavailable.
- Telegram connection settings and daily, weekly, and monthly report generation.
- CSV import flow for bank statements, including Persian digit normalization, Jalali date conversion, Rial-to-Toman conversion, duplicate detection, and user-only custom category creation.

## Application Routes

Web routes:

- `/` - landing page
- `/dashboard` - finance dashboard
- `/transactions` - transaction list and web mutations
- `/transactions/import-template` - sample CSV download
- `/transactions/imports/preview` - CSV validation and preview
- `/transactions/imports` - confirmed CSV import
- `/reports` - finance reports
- `/investments` - investment entries
- `/portfolio` - portfolio summary
- `/settings/*` - profile, security, appearance, preferences, and Telegram settings

API routes:

- `/api/auth/*` - email, password, Google, recovery, signup, and session endpoints
- `/api/profile` - authenticated profile update
- `/api/transactions` - authenticated transaction API resource
- `/api/categories` - authenticated category index

## Core Domain

- `users` own transactions, custom categories, investments, social accounts, preferences, and Telegram connection data.
- `categories` may be global defaults or user-owned custom categories.
- `transactions` store type, category, positive amount, currency, title, description, and occurred date.
- `investments` store asset type, quantity, cost basis, currency, note, and occurred date.
- Supported transaction currencies are `toman`, `usd`, and `eur`.
- Supported transaction types are `cost` and `income`.

Important backend files:

- `app/Actions/Auth/EmailAuthBroker.php`
- `app/Actions/Auth/GoogleAuthBroker.php`
- `app/Actions/Transactions/SaveTransaction.php`
- `app/Actions/Transactions/ImportTransactions.php`
- `app/Actions/Transactions/CurrencyConverter.php`
- `app/Http/Controllers/TransactionController.php`
- `app/Http/Controllers/TransactionImportController.php`
- `app/Http/Controllers/InvestmentController.php`
- `app/Http/Controllers/PortfolioController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Services/AssetPriceService.php`
- `app/Services/TelegramReportService.php`

Important frontend files:

- `resources/js/pages/Landing.vue`
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/Transactions.vue`
- `resources/js/pages/Report.vue`
- `resources/js/pages/Investments.vue`
- `resources/js/pages/Portfolio.vue`
- `resources/js/pages/settings/*.vue`
- `resources/js/components/ui/*`
- `resources/js/components/charts/*`

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
