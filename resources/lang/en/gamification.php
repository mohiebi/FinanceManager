<?php

return [
    'title' => 'Flight log',
    'run' => 'days logged in a row',
    'run_none' => 'No run yet — log anything to start one.',
    'best_run' => 'Best run · {days} days',
    'logged_today' => 'Today is logged.',
    'open_today' => 'Today is still open.',
    'record_within_reach' => 'Log today and that is a new personal best.',
    'grace_left' => '{count} grace left this week|{count} grace left this week',
    'grace_none' => 'No grace left this week',
    'add_transaction' => 'Add transaction',
    'no_spend_action' => 'Nothing spent today',
    'no_spend_recorded' => 'Marked today as spend-free. Your run keeps going.',
    'no_spend_conflict' => 'You have already recorded something today, so the day counts anyway.',

    'states' => [
        'logged' => 'Logged',
        'no_spend' => 'Nothing spent',
        'grace' => 'Grace used',
        'missed' => 'Missed',
        'open' => 'Today',
    ],

    'logbook' => [
        'title' => '{month} logbook',
        'elapsed' => '{elapsed} of {total} days elapsed',
        'complete' => '{percent}% complete',
        'complete_short' => 'Complete',
        'days_covered' => 'Days with a record',
        'uncategorised' => 'Uncategorised',
        'bills' => 'Bills reconciled',
        'sort_uncategorised' => 'Sort {count} uncategorised|Sort {count} uncategorised',
    ],

    'ranks' => [
        'cadet' => 'Cadet',
        'pilot' => 'Pilot',
        'captain' => 'Captain',
        'progress' => '{days} days recorded · {remaining} to the next rank',
        'top' => '{days} days recorded',
    ],

    'goals' => [
        'new' => 'New goal',
        'asset' => 'Saving in',
        'asset_placeholder' => 'Pick an asset',
        'target' => 'Target amount',
        'target_date' => 'By',
        'label' => 'Name',
        'label_placeholder' => 'Nowruz fund',
        'title' => 'Savings goals',
        'on_track' => 'On track',
        'behind_pace' => 'Behind pace',
        'of_target' => 'of {target} {unit}',
        'remaining' => '{days} days left · {amount} {unit} a day to get there',
        'window_closed' => 'The target date has passed.',
        'saved' => 'Goal saved.',
        'deleted' => 'Goal removed.',
        'empty' => 'No goals yet. Set one in an asset you actually save in — grams of gold or dollars, not toman, so inflation cannot quietly erase it.',
    ],

];
