<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Bills\ListBillsTool;
use App\Mcp\Tools\Bills\ProposeBillTool;
use App\Mcp\Tools\Bills\ProposePayBillTool;
use App\Mcp\Tools\Investments\ListInvestmentsTool;
use App\Mcp\Tools\Investments\PortfolioSummaryTool;
use App\Mcp\Tools\Investments\ProposeCustomAssetTool;
use App\Mcp\Tools\Investments\ProposeInvestmentTool;
use App\Mcp\Tools\Proposals\ConfirmProposalTool;
use App\Mcp\Tools\Proposals\ListPendingProposalsTool;
use App\Mcp\Tools\Proposals\RejectProposalTool;
use App\Mcp\Tools\Reports\SpendingSummaryTool;
use App\Mcp\Tools\Transactions\ListCategoriesTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use App\Mcp\Tools\Transactions\ProposeCategoryTool;
use App\Mcp\Tools\Transactions\ProposeTransactionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('CashPilot Finance')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
CashPilot is a personal finance app. All data you can see or change belongs
exclusively to the authenticated user.

Currencies: toman (Iranian toman, the default), usd, eur. Dates are always
Gregorian YYYY-MM-DD in tool arguments and results, even for users who use
the Jalali calendar in the app.

Reading data (transactions, categories, bills, investments, portfolio,
spending summaries) is direct.

Changing data is a strict two-step protocol:
1. Call a propose-* tool. It validates the change and returns a diff plus a
   proposal_id. NOTHING IS SAVED at this point.
2. Show the user the exact change and ask for approval. Only after the user
   clearly approves, call confirm-proposal with the proposal_id. If the user
   declines, call reject-proposal.

Proposals expire after 10 minutes and can be confirmed at most once. Never
confirm a proposal the user has not explicitly approved in this conversation.
MARKDOWN)]
class FinanceServer extends Server
{
    protected array $tools = [
        // Read
        ListTransactionsTool::class,
        ListCategoriesTool::class,
        ListBillsTool::class,
        ListInvestmentsTool::class,
        PortfolioSummaryTool::class,
        SpendingSummaryTool::class,

        // Propose (write nothing)
        ProposeTransactionTool::class,
        ProposeCategoryTool::class,
        ProposeBillTool::class,
        ProposePayBillTool::class,
        ProposeInvestmentTool::class,
        ProposeCustomAssetTool::class,

        // Confirm / reject
        ConfirmProposalTool::class,
        RejectProposalTool::class,
        ListPendingProposalsTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
