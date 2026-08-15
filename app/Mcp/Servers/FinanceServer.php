<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ApplyFinanceChangesTool;
use App\Mcp\Tools\Bills\ListBillsTool;
use App\Mcp\Tools\Budgets\BudgetProgressTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\Goals\ListSavingsGoalsTool;
use App\Mcp\Tools\Investments\ListInvestmentsTool;
use App\Mcp\Tools\Investments\PortfolioSummaryTool;
use App\Mcp\Tools\Reports\SpendingSummaryTool;
use App\Mcp\Tools\Transactions\ListCategoriesTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('CashPilot Finance')]
#[Version('2.0.0')]
#[Instructions(<<<'MARKDOWN'
CashPilot is a personal finance app. All data you can see or change belongs
exclusively to the authenticated user.

ALWAYS call get-user-context first: it returns the user's language, calendar
system (gregorian or jalali), preferred currency, and today's date in both
calendars. Users with the jalali calendar think and speak in Jalali dates.

Language: reply in the language named by the `language` field of that result,
whatever language the user writes in, unless they explicitly ask for another
one. Stored names — categories, bills, assets, goals — are reproduced exactly
as they come back from the tools, never translated.

Dates: pass dates to tools exactly as the user gives them, in YYYY-MM-DD
form. Jalali years (1100-1599) are auto-detected and converted server-side —
NEVER convert between calendars yourself; you will get it wrong. Tool
results include *_jalali fields for jalali users; present those dates to
the user, not the Gregorian ones. Monthly summaries group by the user's
calendar months.

Currencies: toman (Iranian toman, the default), usd, eur.

Reading data (transactions, categories, bills, investments, portfolio,
spending summaries, budgets, savings goals) is direct.

Budgets answer "how much can I still spend on X this month". Allowances are
rules, not fixed envelopes — a share of income, a fixed amount, or whatever
is left over — so they move with the income actually received. Savings goals
are denominated in asset units (grams of gold, dollars), never in toman, and
report `reached` separately from `on_track`.

Changing data uses one approval and one batch call:
1. Build the complete list of intended changes without calling a mutation tool.
2. Show the user a concise summary of the whole batch and ask for explicit
   approval once.
3. After approval, call apply-finance-changes exactly once with every approved
   row in its operations array. It writes immediately and atomically.

Never call apply-finance-changes before approval. Never split one approved
batch into one tool call per row, and never ask for confirmation again between
operations in that batch. If the user changes the requested scope, summarize
the revised batch and obtain approval for the revised scope before calling.
MARKDOWN)]
class FinanceServer extends Server
{
    protected array $tools = [
        // Context
        GetUserContextTool::class,

        // Read
        ListTransactionsTool::class,
        ListCategoriesTool::class,
        ListBillsTool::class,
        ListInvestmentsTool::class,
        PortfolioSummaryTool::class,
        SpendingSummaryTool::class,
        BudgetProgressTool::class,
        ListSavingsGoalsTool::class,

        // Write the user's explicitly approved batch in one atomic call.
        ApplyFinanceChangesTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
