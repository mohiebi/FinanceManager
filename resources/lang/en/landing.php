<?php

return [
    'meta' => [
        'title' => 'Your money, on Autopilot',
        'description' => 'Track money, build plans, follow investments, and earn Miles to unlock the tools you need. Private by default, with an optional zero-knowledge vault.',
    ],

    'a11y' => [
        'main_navigation' => 'Main navigation',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'hero' => 'Hero',
        'trust' => 'Trust',
        'call_to_action' => 'Call to action',
    ],

    'nav' => [
        'features' => 'Features',
        'privacy' => 'Privacy',
        'telegram' => 'Telegram bot',
        'how_it_works' => 'How it works',
        'faq' => 'FAQ',
        'sign_in' => 'Sign in',
        'get_started' => 'Get started free',
        'dashboard' => 'Dashboard',
    ],

    'hero' => [
        'badge' => 'Core finance tracking is free',
        'title_top' => 'Your money,',
        'title_highlight' => 'on Autopilot.',
        'subtitle' => 'Track daily money, build plans, and earn Miles to unlock the tools you need, without giving up privacy.',
        'cta_primary' => "Get started, it's free",
        'cta_secondary' => 'See all features',
        'mock' => [
            'dashboard' => 'Dashboard',
            'currency' => 'Currency: Toman',
            'income' => 'Income',
            'costs' => 'Costs',
            'balance' => 'Balance',
            'toman' => 'TOMAN',
            'finance_rate' => 'Finance Rate',
            'rate_hint' => 'Income ÷ total flow',
            'monthly_overview' => 'Monthly overview',
        ],
    ],

    'trust' => [
        'encrypted' => [
            'title' => 'Encrypted by default',
            'text' => 'Per-account encryption, with an optional private vault',
        ],
        'calendars' => [
            'title' => 'Two calendars',
            'text' => 'Jalali and Gregorian, switch anytime',
        ],
        'currencies' => [
            'title' => 'Live exchange rates',
            'text' => 'Toman, USD & EUR converted with live market rates',
        ],
        'telegram' => [
            'title' => 'Telegram built in',
            'text' => 'Add expenses and get bill reminders in chat',
        ],
    ],

    'privacy' => [
        'kicker' => 'Privacy first',
        'title' => 'Your money is nobody else’s business.',
        'subtitle' => 'Every amount, title, and note is encrypted before storage. For stronger privacy, move the key entirely into your browser.',

        'levels' => [
            'standard' => [
                'badge' => 'Level 1, default',
                'title' => 'Encrypted at rest',
                'text' => 'On by default, for every account, with nothing to configure. Your amounts, titles and notes are encrypted with a key that belongs to your account alone, so a stolen database is a pile of noise.',
                'points' => [
                    'encrypted' => 'AES-256-GCM on every amount, title and note before it reaches the database.',
                    'per_user' => 'A separate data key per account means one leaked record does not unlock anyone else’s.',
                    'readable' => 'We can still decrypt it, which is what lets the Telegram bot, reminders and the AI assistant work.',
                ],
            ],
            'vault' => [
                'badge' => 'Level 2, optional',
                'title' => 'Private vault (zero-knowledge)',
                'text' => 'Switch it on and the key moves into your browser. We keep storing your data, but our staff and servers can no longer read it.',
                'points' => [
                    'zero_knowledge' => 'We hold ciphertext and nothing else. There is no copy of your key on our side to hand over.',
                    'browser_key' => 'Your passphrase never leaves your device; the key is derived and used in your browser.',
                    'tradeoff' => 'The honest cost: Telegram and the AI assistant stop working, and losing both your passphrase and recovery key means the data is gone.',
                ],
            ],
        ],

        'how' => [
            'title' => 'How the vault actually works',
            'text' => 'Your data is encrypted with a single data key. Turning the vault on protects that key with a passphrase only you know and a recovery key only you hold, then deletes our copy. The switch is instant because your data does not need to be encrypted again. Your browser handles decryption and calculations.',
        ],

        'pledges' => [
            'no_ads' => [
                'title' => 'No ads, ever',
                'text' => 'Nothing about your spending is used to sell you anything',
            ],
            'no_selling' => [
                'title' => 'Never sold or shared',
                'text' => 'Your financial data is not a product and never becomes one',
            ],
            'no_tracking' => [
                'title' => 'No third-party trackers',
                'text' => 'No advertising pixels or analytics profiles follow you here',
            ],
            'export' => [
                'title' => 'Your data, exportable',
                'text' => 'Take everything with you as a spreadsheet whenever you like',
            ],
        ],
    ],

    'features' => [
        'kicker' => 'Your financial cockpit',
        'title' => 'Start simple. Add what matters.',
        'subtitle' => 'Transactions and reports form the free core. Earn Miles and permanently unlock the optional modules that fit your life.',
        'transactions' => [
            'title' => 'Transaction Tracking',
            'text' => 'Log costs and income in seconds. Categorise, search, and filter by date or type to see your money story clearly.',
            'points' => [
                'Costs & incomes in one view',
                'Custom categories & descriptions',
                'Excel import & export',
            ],
        ],
        'investments' => [
            'title' => 'Investment Portfolio',
            'text' => 'Track gold, silver, USD, EUR, and Bitcoin, or add custom assets. Sync current market prices or value assets yourself.',
            'points' => [
                'Current market price sync (tgju)',
                'Net worth & allocation breakdown',
                'Custom assets with your own pricing',
            ],
        ],
        'bills' => [
            'title' => 'Bills & Reminders',
            'text' => 'Plan monthly or one-time bills in Jalali or Gregorian. Get reminders before and on the due date, then mark paid with one tap.',
            'points' => [
                'Telegram & in-app reminders',
                'Paying a bill logs the expense automatically',
            ],
        ],
        'reports' => [
            'title' => 'Smart Reports',
            'text' => 'Monthly, seasonal, yearly or custom date-range reports. Compare income vs costs and spot patterns instantly.',
        ],
        'multi_currency' => [
            'title' => 'Multi-Currency',
            'text' => 'Switch between Toman, USD, and EUR. Amounts convert using current market rates.',
        ],
        'miles' => [
            'title' => 'Progress that unlocks more',
            'text' => 'Earn Miles for consistent use and milestones, never for how much money you have. Spend them on permanent module unlocks and streak protection.',
            'points' => [
                '150 welcome Miles',
                'Daily claims and activity rewards',
                'Optional modules stay unlocked',
            ],
        ],
        'planning' => [
            'title' => 'Flight plans and savings goals',
            'text' => 'Give every category a plan, track what remains, and set savings targets in cash, gold, currency, or your own asset.',
        ],
        'advisor' => [
            'title' => 'AI Portfolio Advisor',
            'text' => 'Turn a risk assessment into a constrained investor profile and educational allocation guidance that stays inside your limits.',
        ],
        'security' => [
            'title' => 'Private by Design',
            'text' => 'Data is encrypted at rest with a separate key for each account. Choose the private vault when you want the key to stay only in your browser. Email codes, two-factor authentication, and Google sign-in are supported.',
        ],
    ],

    'telegram' => [
        'kicker' => 'Your finance bot',
        'title' => 'Run it all from Telegram',
        'subtitle' => 'Unlock the Telegram module once, then add expenses, create bills, and mark them paid without opening the app.',
        'points' => [
            'add' => [
                'title' => 'Add transactions in chat',
                'text' => 'A guided wizard logs an expense in a few taps.',
            ],
            'bills' => [
                'title' => 'Bill reminders that find you',
                'text' => 'A message the day before and on the due day.',
            ],
            'paid' => [
                'title' => 'Mark paid, logged instantly',
                'text' => 'Tap "Mark paid" and the expense is recorded for you.',
            ],
        ],
        'chat' => [
            'bot_name' => 'CashPilot Bot',
            'status' => 'online',
            'reminder' => '🔔 Reminder: “Internet bill” is due tomorrow. 450,000 Toman',
            'mark_paid' => 'Mark paid ✓',
            'confirmation' => "✅ Paid! I've logged a 450,000 Toman expense under Utilities.",
            'user_add' => 'Add expense',
            'wizard' => '💸 What did you spend on?',
            'user_reply' => 'Groceries, 850,000',
            'done' => '✅ Saved: 850,000 Toman · Groceries',
        ],
    ],

    'how' => [
        'kicker' => 'Simple setup',
        'title' => 'Up and running in minutes',
        'steps' => [
            'account' => [
                'title' => 'Create your account',
                'text' => 'Sign up with email or Google in seconds. Your account starts with 150 Miles and needs no credit card.',
            ],
            'log' => [
                'title' => 'Choose your modules',
                'text' => 'Start with transactions and reports, then use Miles to permanently unlock the planning, investment, and automation tools you want.',
            ],
            'insights' => [
                'title' => 'Watch insights appear',
                'text' => 'Your dashboard and reports update as you log activity, while plans, goals, and your portfolio show what comes next.',
            ],
        ],
    ],

    'faq' => [
        'kicker' => 'Questions',
        'title' => 'Frequently asked',
        'items' => [
            'private' => [
                'q' => 'Is my financial data private?',
                'a' => 'Yes. Amounts, titles, and notes are encrypted before storage with a separate key for each account. The optional private vault moves that key into your browser so the server can no longer decrypt your financial data.',
            ],
            'miles' => [
                'q' => 'What are Miles?',
                'a' => 'Miles reward consistent use and milestones, never account balances or net worth. Claim them daily and earn more through activity, then use them for permanent module unlocks and streak protection.',
            ],
            'advisor' => [
                'q' => 'What does the AI Portfolio Advisor do?',
                'a' => 'It turns your risk assessment into a constrained investor profile, then suggests an educational target allocation within those limits. It does not place trades or replace professional financial advice.',
            ],
            'calendar' => [
                'q' => 'Does it support the Jalali calendar?',
                'a' => 'Fully. Dates, reports, and bill due dates work in Jalali or Gregorian. Pick your calendar in preferences and switch anytime.',
            ],
            'telegram' => [
                'q' => 'What can the Telegram bot do?',
                'a' => 'Unlock the module and connect your account once in settings. Then add transactions and bills, list what is due, mark bills paid, and receive reminders from chat.',
            ],
            'free' => [
                'q' => 'Is it really free?',
                'a' => 'Transactions and reports are free. New accounts receive 150 Miles, and you can earn more in the app to permanently unlock optional modules. No subscription is required to keep a module you unlock.',
            ],
            'currencies' => [
                'q' => 'Which currencies are supported?',
                'a' => 'Toman, US Dollar, and Euro. Conversions use current market rates, and you can refresh them from the app.',
            ],
        ],
    ],

    'pricing' => [
        'title' => 'Start with the core for free',
        'text' => 'Earn Miles in the app and unlock optional modules once. Modules you unlock stay yours.',
        'cta' => 'Start for free today',
    ],

    'cta' => [
        'title_top' => 'Take control of your',
        'title_highlight' => 'financial future',
        'subtitle' => 'Start with the free core, earn your first Miles, and shape CashPilot around the way you manage money.',
        'button' => 'Get started free',
    ],

    'footer' => [
        'tagline' => 'Built with ♥ for personal finance.',
        'sign_in' => 'Sign in',
        'get_started' => 'Get started',
    ],

    'auth_panel' => [
        'tagline' => 'All in one simple, powerful place',
        'headline_1' => 'Your',
        'headline_2' => 'Money,',
        'headline_3' => 'on Autopilot',
        'description' => 'See your finances clearly, stay in control, and make smarter decisions without the guesswork.',
    ],
];
