# Finance Manager

Temporary project notes for the current implementation.

## Current Focus

- Email-first authentication for the Inertia SPA and JSON API.
- Finance dashboard with costs and incomes.
- Shared transaction validation and save logic for both web and mobile/API use.
- CSV transaction imports with Persian-friendly normalization.

## Transaction Structure

- Web dashboard: `GET /dashboard`
- Web mutations: `/transactions`
- Web imports: `/transactions/import-template`, `/transactions/imports/preview`, `/transactions/imports`
- Mobile/API mutations: `/api/transactions`
- API categories: `/api/categories`

Shared backend pieces:

- `app/Http/Requests/Transaction/TransactionRequest.php`
- `app/Actions/Transactions/SaveTransaction.php`
- `app/Actions/Transactions/ImportTransactions.php`
- `app/Http/Controllers/TransactionImportController.php`
- `app/Http/Resources/TransactionResource.php`
- `app/Http/Resources/CategoryResource.php`

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

## Useful Checks

```bash
php artisan test --compact tests\Feature\TransactionControllerTest.php tests\Feature\TransactionImportTest.php tests\Feature\Api\TransactionApiTest.php tests\Feature\FinanceModelTest.php tests\Feature\DashboardTest.php
php vendor\bin\pint --dirty --format agent
npm.cmd run types:check
npm.cmd run lint:check
```

## Note

This README is temporary and should be replaced with a fuller project README once the main finance flows settle.
