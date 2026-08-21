<?php

return [
    'title' => 'Modules',
    'description' => 'Switch parts of CashPilot on and off. Turning a module off never deletes anything — your data comes back when you turn it on again.',
    'locked' => 'Turn on the :module module to use that page.',
    'telegram_locked' => 'The :module module is switched off. Turn it on in Settings > Modules to use this here.',
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
    'core_badge' => 'Always on',
    'core_heading' => 'Always on',
    'core_description' => 'The parts CashPilot cannot work without. Listed so you know what is running, not so you can switch it off.',
    'optional_heading' => 'Optional modules',
    'hide_from_menu' => 'Hide from menu',
    'hide_from_menu_hint' => 'Stop showing this in the sidebar until you turn it on.',
    'saved' => 'Modules updated.',
    'cascade_enabled' => 'Also turned on: :features.',
    'cascade_disabled' => 'Also turned off: :features.',
    'confirm_disable_title' => 'Turn off {module}?',
    'confirm_disable_body' => 'This will also turn off {features}. Your data is kept and comes back if you turn it on again.',
    'confirm_disable_action' => 'Turn off',
    'cancel' => 'Cancel',
    'requires' => 'Needs {features}',

    // Shown when someone without Pro reaches for a Pro module. Rendered by
    // vue-i18n, so the placeholder is {module} rather than :module.
    'upgrade' => [
        'title' => '{module} is a Pro module',
        'body' => 'Pro covers the modules that call a paid AI provider every time you use them. Everything else in CashPilot stays free.',
        'note' => 'Pay in crypto for a month, a quarter or a year. Nothing renews by itself.',
        // Shown in place of the note when billing is switched off — there is no
        // plans page to send anyone to, so the dialog must not promise one.
        'unavailable' => 'Pro is not on sale just yet. This module unlocks as soon as it is.',
        'continue' => 'See Pro plans',
        'later' => 'Maybe later',
    ],

    'manage' => 'Manage in Privacy & security',
    'managed_elsewhere' => 'Switched on from its own page, because turning it on re-keys your data.',

    'tiers' => [
        'free' => 'Free',
        'pro' => 'Pro',
    ],

    'vault' => [
        'label' => 'Private vault',
        'description' => 'Hold the only key to your own data. CashPilot will not be able to read your amounts or titles — and neither will anyone who compels us.',
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
    'budgets' => [
        'label' => 'Flight plan',
        'description' => 'Decide where your money goes before it arrives — a share of your income, a fixed amount, or whatever is left.',
    ],
    'investments' => [
        'label' => 'Investments',
        'description' => 'Log what you hold — gold, currency, crypto — and follow live prices.',
    ],
    'portfolio' => [
        'label' => 'Portfolio',
        'description' => 'See your net worth and profit or loss across every asset you hold.',
    ],
    'goals' => [
        'label' => 'Savings goals',
        'description' => 'Set targets in the asset you actually save in — grams of gold, dollars — so inflation cannot quietly erase them.',
    ],
    'gamification' => [
        'label' => 'Flight log',
        'description' => 'Keep a logging streak, mark days you spent nothing, and see how complete each month is. Never about how much you spend — only about keeping the record straight.',
    ],
    'ai_assistant' => [
        'label' => 'AI Assistant',
        'description' => 'Connect AI tools that can read your finance data and propose changes for your approval.',
    ],
    'advisor' => [
        'label' => 'AI Portfolio Advisor',
        'description' => 'Build a CashPilot risk profile and receive a constrained AI-designed target portfolio.',
    ],
    'telegram_bot' => [
        'label' => 'Telegram Bot',
        'description' => 'Use CashPilot from Telegram to add entries, review summaries, and receive reminders.',
    ],

    'display' => [
        'heading' => 'Display',
        'compact_figures_label' => 'Compact figures',
        'compact_figures_description' => 'Shows 988.7M instead of 988,691,514 in figures meant for a glance, not bookkeeping.',
    ],
];
