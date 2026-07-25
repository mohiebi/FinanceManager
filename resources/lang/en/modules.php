<?php

return [
    'title' => 'Modules',
    'description' => 'Switch parts of CashPilot on and off. Turning a module off never deletes anything — your data comes back when you turn it on again.',
    'locked' => 'Turn on the :module module to use that page.',
    'telegram_locked' => 'The :module module is switched off. Turn it on in Settings > Modules to use this here.',
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
    'core_badge' => 'Always on',
    'hide_from_menu' => 'Hide from menu',
    'hide_from_menu_hint' => 'Stop showing this in the sidebar until you turn it on.',
    'saved' => 'Modules updated.',
    'cascade_enabled' => 'Also turned on: :features.',
    'cascade_disabled' => 'Also turned off: :features.',
    'confirm_disable_title' => 'Turn off :module?',
    'confirm_disable_body' => 'This will also turn off :features. Your data is kept and comes back if you turn it on again.',
    'confirm_disable_action' => 'Turn off',
    'cancel' => 'Cancel',
    'requires' => 'Needs :features',

    'tiers' => [
        'free' => 'Free',
        'pro' => 'Pro',
    ],

    'transactions' => [
        'label' => 'Transactions',
        'description' => 'Record what you earn and spend. The base of everything else.',
    ],
    'reports' => [
        'label' => 'Reports',
        'description' => 'Break your income and spending down by category and by month.',
    ],
    'bills' => [
        'label' => 'Bills',
        'description' => 'Track recurring and one-off bills, and get reminded before they are due.',
    ],
    'investments' => [
        'label' => 'Investments',
        'description' => 'Log what you hold — gold, currency, crypto — and follow live prices.',
    ],
    'portfolio' => [
        'label' => 'Portfolio',
        'description' => 'See your net worth and profit or loss across every asset you hold.',
    ],
    'ai_assistant' => [
        'label' => 'AI Assistant',
        'description' => 'Connect AI tools that can read your finance data and propose changes for your approval.',
    ],
    'telegram_bot' => [
        'label' => 'Telegram Bot',
        'description' => 'Use CashPilot from Telegram to add entries, review summaries, and receive reminders.',
    ],
];
