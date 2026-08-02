<?php

return [
    'title' => 'Flight plan',
    'description' => 'Decide where your money goes before it arrives. Set a share of your income, a fixed amount, or let a line mop up whatever is left.',

    'saved' => 'Flight plan saved.',
    'deleted' => 'Flight plan deleted.',

    'empty_title' => 'File your first flight plan',
    'empty_body' => 'Say what share of your income each category should get, and CashPilot works out the amounts as the money lands.',
    'empty_action' => 'Create a plan',

    'edit' => 'Edit plan',
    'create' => 'Create plan',
    'save' => 'Save plan',
    'cancel' => 'Cancel',

    // Rendered by vue-i18n, so placeholders are {braced} rather than :colon-prefixed.
    'delete' => [
        'action' => 'Delete plan',
        'title' => 'Delete this flight plan?',
        'description' => 'The plan and its lines are removed. Your transactions are untouched.',
    ],

    'plan_title' => 'Plan name',
    'plan_title_placeholder' => 'Monthly plan',

    'income_basis' => [
        'label' => 'Base percentages on',
        'actual' => 'Income received so far',
        'actual_hint' => 'Targets grow as money arrives. Honest when your income varies, but reads zero at the start of a month.',
        'expected' => 'An income I expect',
        'expected_hint' => 'Targets exist from day one, and drift shows up as the real income lands.',
    ],
    'expected_income' => 'Expected income',

    'period' => 'This month',
    'days_remaining' => '{count} days left',

    'income' => 'Income',
    'allocated' => 'Allocated',
    'unallocated' => 'Unallocated',
    'spent' => 'Spent',
    'spent_inline' => '{amount} spent',
    'over_allocated' => 'Over-allocated by {amount}',
    'over_allocated_hint' => 'The fixed and percentage lines promise more than this income covers.',

    'lines' => 'Plan lines',
    'add_line' => 'Add line',
    'remove_line' => 'Remove line',
    'category' => 'Category',
    'rule' => 'Rule',

    'rules' => [
        'percent' => 'Share of income',
        'fixed' => 'Fixed amount',
        'remainder' => 'Everything else',
    ],

    'target' => 'Target',
    'currency' => 'Currency',
    'remaining' => '{amount} to go',
    'overspent' => '{amount} over',
    'on_target' => 'On target',
    'no_target' => 'Nothing allocated yet',
    'calculating' => 'Working out your plan',

    // Thrown server-side through __(), so these keep Laravel's :colon syntax —
    // they arrive at the browser already interpolated.
    'errors' => [
        'category_required' => 'Pick a category for this line.',
        'percent_required' => 'Enter a percentage for this line.',
        'amount_required' => 'Enter an amount for this line.',
        'one_remainder' => 'A plan can only have one "everything else" line.',
        'percent_total' => 'Your percentage lines add up to :total%. They cannot exceed 100%.',
        'duplicate_category' => 'Each category can only appear on one line.',
    ],
];
