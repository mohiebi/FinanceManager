# Finance Manager

Temporary project notes for the current implementation.

## Current Focus

- Email-first authentication for the Inertia SPA and JSON API.
- Finance dashboard with costs and incomes.
- Shared transaction validation and save logic for both web and mobile/API use.

## Transaction Structure

- Web dashboard: `GET /dashboard`
- Web mutations: `/transactions`
- Mobile/API mutations: `/api/transactions`
- API categories: `/api/categories`

Shared backend pieces:

- `app/Http/Requests/Transaction/TransactionRequest.php`
- `app/Actions/Transactions/SaveTransaction.php`
- `app/Http/Resources/TransactionResource.php`
- `app/Http/Resources/CategoryResource.php`

## Useful Checks

```bash
php artisan test --compact tests\Feature\TransactionControllerTest.php tests\Feature\Api\TransactionApiTest.php tests\Feature\FinanceModelTest.php tests\Feature\DashboardTest.php
php vendor\bin\pint --dirty --format agent
npm.cmd run types:check
npm.cmd run lint:check
```

## Note

This README is temporary and should be replaced with a fuller project README once the main finance flows settle.
