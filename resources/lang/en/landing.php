<?php

return [
    'meta' => [
        'title' => 'Your money, on Autopilot',
        'description' => 'Track transactions, manage investments, get bill reminders on Telegram, and see clear financial reports — free, private, encrypted.',
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
        'badge' => 'Personal finance platform — free for personal use',
        'title_top' => 'Your money,',
        'title_highlight' => 'on Autopilot.',
        'subtitle' => 'Track every transaction, watch your investments grow, and never miss a bill — with reminders right in Telegram. All in one private, encrypted dashboard.',
        'cta_primary' => "Get started — it's free",
        'cta_secondary' => 'See all features',
        'microcopy' => 'No credit card required · Sign in with email or Google',
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
            'title' => 'Encrypted at rest',
            'text' => 'Amounts are encrypted before they reach the database',
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
        'subtitle' => 'Every amount, title and note is encrypted before it is written to disk — and if that is not enough for you, you can take the key away from us entirely.',

        'levels' => [
            'standard' => [
                'badge' => 'Level 1 — default',
                'title' => 'Encrypted at rest',
                'text' => 'On by default, for every account, with nothing to configure. Your amounts, titles and notes are encrypted with a key that belongs to your account alone, so a stolen database is a pile of noise.',
                'points' => [
                    'encrypted' => 'AES-256-GCM on every amount, title and note before it reaches the database.',
                    'per_user' => 'A separate data key per account — one leaked record does not unlock anyone else’s.',
                    'readable' => 'We can still decrypt it, which is what lets the Telegram bot, reminders and the AI assistant work.',
                ],
            ],
            'vault' => [
                'badge' => 'Level 2 — optional',
                'title' => 'Private vault (zero-knowledge)',
                'text' => 'Switch it on and the key moves into your browser. We keep storing your data and we stop being able to read it — not our staff, not our servers, not under a court order.',
                'points' => [
                    'zero_knowledge' => 'We hold ciphertext and nothing else. There is no copy of your key on our side to hand over.',
                    'browser_key' => 'Your passphrase never leaves your device; the key is derived and used in your browser.',
                    'tradeoff' => 'The honest cost: Telegram and the AI assistant stop working, and losing both your passphrase and recovery key means the data is gone.',
                ],
            ],
        ],

        'how' => [
            'title' => 'How the vault actually works',
            'text' => 'Your data is encrypted with a single data key. Turning the vault on wraps that key under a passphrase only you know and a recovery key only you hold, then deletes our copy. Nothing is re-encrypted, so it is instant — and irreversible without one of your two secrets. Everything else keeps working, because your browser does the decrypting and the arithmetic.',
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
        'kicker' => 'Everything you need',
        'title' => 'One dashboard. Total financial clarity.',
        'subtitle' => 'No spreadsheets. No guessing. Just clear, real-time visibility over every toman you earn and spend.',
        'transactions' => [
            'title' => 'Transaction Tracking',
            'text' => 'Log costs and income in seconds. Categorise, search, filter by date or type — and see your money story at a glance.',
            'points' => [
                'Costs & incomes in one view',
                'Custom categories & descriptions',
                'Excel import & export',
            ],
        ],
        'investments' => [
            'title' => 'Investment Portfolio',
            'text' => 'Track gold, silver, USD, EUR, and Bitcoin — or add your own custom assets. Prices sync automatically from live market data every hour.',
            'points' => [
                'Live price sync (tgju)',
                'Net worth & allocation breakdown',
                'Custom assets with your own pricing',
            ],
        ],
        'bills' => [
            'title' => 'Bills & Reminders',
            'text' => 'Monthly or one-time bills with due dates on your calendar — Jalali or Gregorian. Get reminded the day before and on the due day, then mark paid with one tap.',
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
            'text' => 'Switch between Toman, USD, and EUR on the fly. Every amount converts using live market rates — not stale numbers.',
        ],
        'security' => [
            'title' => 'Private by Design',
            'text' => 'Your amounts are encrypted before they touch the database — we could not read them if we wanted to. Email code sign-in, two-factor auth, and Google sign-in included.',
        ],
    ],

    'telegram' => [
        'kicker' => 'Your finance bot',
        'title' => 'Run it all from Telegram',
        'subtitle' => 'Add expenses, create bills, and mark them paid — without opening the app. Reminders land right in your chat.',
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
            'reminder' => '🔔 Reminder: “Internet bill” is due tomorrow — 450,000 Toman',
            'mark_paid' => 'Mark paid ✓',
            'confirmation' => "✅ Paid! I've logged a 450,000 Toman expense under Utilities.",
            'user_add' => 'Add expense',
            'wizard' => '💸 What did you spend on?',
            'user_reply' => 'Groceries — 850,000',
            'done' => '✅ Saved: 850,000 Toman · Groceries',
        ],
    ],

    'how' => [
        'kicker' => 'Simple setup',
        'title' => 'Up and running in minutes',
        'steps' => [
            'account' => [
                'title' => 'Create your account',
                'text' => 'Sign up with email or Google in seconds — a 6-digit code confirms your email. No credit card.',
            ],
            'log' => [
                'title' => 'Log your money',
                'text' => 'Add costs, income, investments, and bills as they happen. Or import your history from Excel.',
            ],
            'insights' => [
                'title' => 'Watch insights appear',
                'text' => 'Your dashboard, reports, and portfolio update instantly — and reminders reach you on Telegram.',
            ],
        ],
    ],

    'faq' => [
        'kicker' => 'Questions',
        'title' => 'Frequently asked',
        'items' => [
            'private' => [
                'q' => 'Is my financial data private?',
                'a' => 'Yes. Transaction, investment, and bill amounts are encrypted before they are stored — they are unreadable in the database. Your account is protected by email code sign-in and optional two-factor authentication.',
            ],
            'calendar' => [
                'q' => 'Does it support the Jalali calendar?',
                'a' => 'Fully. Dates, reports, and bill due dates all work in Jalali or Gregorian — pick your calendar in preferences and switch anytime.',
            ],
            'telegram' => [
                'q' => 'What can the Telegram bot do?',
                'a' => 'Connect your account once in settings, then add transactions and bills, list what is due, mark bills paid, and receive reminders — all from chat.',
            ],
            'free' => [
                'q' => 'Is it really free?',
                'a' => 'CashPilot is free for personal use — unlimited transactions, investments, bills, and reports. No hidden fees, no credit card.',
            ],
            'currencies' => [
                'q' => 'Which currencies are supported?',
                'a' => 'Toman, US Dollar, and Euro. Conversions use live market rates that refresh automatically every hour.',
            ],
        ],
    ],

    'pricing' => [
        'title' => 'Free for personal use',
        'text' => 'Unlimited transactions, investments, bills, and reports. No hidden fees.',
        'cta' => 'Start for free today',
    ],

    'cta' => [
        'title_top' => 'Take control of your',
        'title_highlight' => 'financial future',
        'subtitle' => 'Join CashPilot and start making sense of your money — for free, today, in minutes.',
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
