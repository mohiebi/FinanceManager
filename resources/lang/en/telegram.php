<?php

/**
 * Everything the bot says or labels a button with.
 *
 * Rendered in the linked user's own locale, not the app default: the webhook has
 * no session to infer a language from, so the handler sets the locale from the
 * account behind the chat id before it builds a single string.
 */
return [
    'buttons' => [
        'limit_infinite' => 'Continues indefinitely',
        'limit_count' => 'Number of payments',
        'limit_date' => 'End date',
        'add_cost' => 'Add cost',
        'add_income' => 'Add income',
        'list_transactions' => 'Last 10 transactions',
        'add_investment' => 'Add investment',
        'add_bill' => 'Add bill',
        'list_bills' => 'My bills',
        'portfolio' => 'Portfolio',
        'budget' => 'Flight plan',
        'no_spend' => 'Nothing spent today',
        'report_daily' => 'Daily report',
        'report_weekly' => 'Weekly report',
        'report_monthly' => 'Monthly report',
        'cancel_to_menu' => 'Cancel and menu',
        'back_to_menu' => 'Back to menu',
        'confirm' => 'Confirm',
        'cancel' => 'Cancel',
        'monthly' => 'Monthly',
        'one_time' => 'One-time',
        'no_category' => 'No category',
        'mark_paid' => 'Mark paid: :title',
        'today' => 'Today (:date)',
        'yesterday' => 'Yesterday (:date)',
    ],

    'link' => [
        'welcome' => "Welcome! To get started, link your account:\n\nGo to Settings > Telegram in the app and click Connect Telegram.",
        'not_linked' => "Your Telegram is not linked to an account.\n\nGo to Settings > Telegram in the app to connect.",
        'link_first' => 'Please link your account first via Settings > Telegram in the app.',
        'invalid_token' => 'Invalid or expired link. Please generate a new one from the app.',
        'success' => "*Account linked successfully!*\n\nWelcome, :name. Choose an action:",
    ],

    'menu' => [
        'greeting' => "Hello, *:name*.\n\nChoose an action:",
        'choose_action' => 'Choose an action:',
        'cancelled' => 'Cancelled.',
        'cancelled_choose_action' => 'Cancelled. Choose an action:',
    ],

    /**
     * How a formatted number and its currency are joined. Separate from
     * `finance.currencies` because the bot writes amounts inline in a sentence,
     * where the symbol's side of the number is part of the language.
     */
    'amount' => [
        'toman' => ':amount T',
        'usd' => '$:amount',
        'eur' => '€:amount',
    ],

    'shared' => [
        'choose_currency' => 'Choose a currency:',
        'use_currency_buttons' => 'Please choose a currency using the buttons above.',
        'choose_category' => 'Choose a category:',
        'use_category_buttons' => 'Please choose a category using the buttons above.',
        'invalid_number' => 'Please enter a valid number.',
        'invalid_positive_number' => 'Please enter a valid positive number.',
    ],

    'transaction' => [
        'add_cost' => "*Add cost*\n\nEnter the amount, for example: 50000",
        'add_income' => "*Add income*\n\nEnter the amount, for example: 5000000",
        'enter_title' => 'Enter a title for this transaction:',
        'confirm' => "*Confirm transaction:*\nType: :type\nAmount: :amount\nTitle: :title\nDate: today",
        'types' => [
            'cost' => 'cost',
            'income' => 'income',
        ],
        'draft_expired' => 'The transaction draft expired. Choose Add cost or Add income to start again.',
        'none_active' => 'No active transaction. Choose Add cost or Add income.',
        'saved' => 'Transaction saved.',
        'not_found' => 'Transaction not found.',
        'deleted' => 'Transaction #:id deleted.',
        'empty' => 'No transactions found.',
        'list_title' => "*Last 10 transactions:*\n",
    ],

    'investment' => [
        'start' => "*Add investment*\n\nChoose asset type:",
        'asset_not_found' => 'Investment asset not found. Choose Add investment to start again.',
        'enter_quantity' => 'Enter quantity for :asset:',
        'enter_cost_basis' => 'Enter cost basis per unit. Send 0 to skip.',
        'choose_cost_basis_currency' => 'Select the currency for the cost basis:',
        'draft_expired' => 'The investment draft expired. Choose Add investment to start again.',
        'saved' => 'Investment saved.',
    ],

    'bill' => [
        'start' => "*Add bill*\n\nEnter a title, for example: Rent",
        'empty_title' => 'Please enter a non-empty title.',
        'enter_amount' => 'Enter the amount, for example: 500000',
        'choose_recurrence' => 'Choose recurrence:',
        'use_recurrence_buttons' => 'Please choose monthly or one-time using the buttons above.',
        'enter_due_day' => 'Enter the day of month it is due (1-31):',
        'invalid_due_day' => 'Please enter a valid day of month (1-31).',
        'choose_limit' => 'How long should this monthly bill continue?',
        'use_limit_buttons' => 'Please choose unlimited, number of payments, or end date using the buttons above.',
        'enter_recurrence_count' => 'Enter the total number of payments (1-600):',
        'invalid_recurrence_count' => 'Please enter a whole number from 1 to 600.',
        'enter_recurrence_end_date' => 'Enter the last payment date as YYYY-MM-DD:',
        'enter_due_date' => 'Enter the due date as YYYY-MM-DD, for example: 2026-08-01',
        'invalid_due_date' => 'Please enter a valid date as YYYY-MM-DD, for example: 2026-08-01',
        'confirm' => "*Confirm bill:*\nTitle: :title\nAmount: :amount\nRecurrence: :recurrence",
        'recurrence_monthly' => 'Monthly — day :day',
        'recurrence_one_time' => 'One-time — :date',
        'limit_infinite' => 'continues indefinitely',
        'limit_count' => ':count payments',
        'limit_date' => 'through :date',
        'payment_progress' => 'payment :current of :total',
        'draft_expired' => 'The bill draft expired. Choose Add bill to start again.',
        'none_active' => 'No active bill draft. Choose Add bill to start again.',
        'saved' => 'Bill ":title" saved.',
        'empty' => 'No bills yet. Choose Add bill to set one up.',
        'list_title' => "*Your bills:*\n",
        'line' => '• *:title* — :amount — due :date',
        'line_no_due' => '• *:title* — :amount — no upcoming due date',
        'not_found' => 'Bill not found.',
        'already_paid' => '":title" is already marked as paid.',
        'marked_paid' => '✓ *:title* marked as paid (:amount). A transaction was added.',
    ],

    'streak' => [
        'already_recorded' => 'You have already recorded something today, so the day counts anyway.',
        'marked' => 'Marked today as spend-free.',
        'run' => ':message Run: *:days* days.',
    ],

    'portfolio' => [
        'empty' => 'No investments recorded yet. Use Add investment from the menu to get started.',
        'title' => '📊 Portfolio',
        'net_worth' => '💰 Net worth: :amount',
        'profit_loss' => 'P/L: :arrow :amount (:percent%)',
        'price_unavailable' => '→ Price unavailable',
    ],

    'budget' => [
        'empty' => 'No flight plan yet. Create one in CashPilot under Flight plan, then check it here.',
        'title' => '🎯 Flight plan — :period',
        'income' => 'Income: :amount',
        'allocated' => 'Allocated: :amount',
        'spent' => 'Spent: :amount',
        'over_allocated' => '⚠️ Over-allocated by :amount',
        'everything_else' => 'Everything else',
        'line_progress' => '→ :actual of :allocated',
        'line_over' => '⚠️ :amount over',
        'line_left' => '✅ :amount left',
        'days_left' => ':days days left this period.',
    ],

    'report' => [
        'choose_day' => 'Choose a day:',
        'daily_title' => 'Daily Report - :date',
        'weekly_title' => 'Weekly Report - :date week',
        'monthly_title' => 'Monthly Report - :date',
        'income' => 'Income',
        'costs' => 'Costs',
        'net' => 'Net',
        'cost_breakdown' => '*Cost Breakdown:*',
        'investments' => '*Investments:*',
        'uncategorized' => 'Other',
        'unnamed_asset' => 'Asset',
        'no_activity' => '_No activity in this period._',
    ],
];
