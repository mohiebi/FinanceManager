<?php

return [
    'bill_due_tomorrow' => [
        'title' => 'Bill due tomorrow',
        // Sent live to Telegram, never stored by us.
        'body' => ':title is due tomorrow (:date). Amount: :amount :currency.',
        // Stored in the notifications table, which is not encrypted — so it must
        // not repeat the bill's title or amount.
        'body_generic' => 'A bill is due tomorrow (:date).',
    ],
    'bill_due_today' => [
        'title' => 'Bill due today',
        'body' => ':title is due today (:date). Amount: :amount :currency.',
        'body_generic' => 'A bill is due today (:date).',
    ],
    'streak_open' => [
        'title' => 'Your run is still open',
        // A run length is not sensitive, so the stored body and the Telegram one
        // are the same string — there is nothing here to withhold.
        'body' => 'Day :days is still open. Log anything before midnight and the run holds.',
        'body_first' => 'Nothing logged today yet. Record anything before midnight to start a run.',
    ],
    // Each body says what became true of the user's records, not "well done" —
    // a milestone that only congratulates is decoration.
    'milestones' => [
        'first_transaction' => [
            'title' => 'First record kept',
            'body' => 'Your logbook has started. Everything else in CashPilot builds on this.',
        ],
        'hundred_transactions' => [
            'title' => 'Hundredth record kept',
            'body' => 'There is now enough history for the trends to be worth reading.',
        ],
        'first_full_month' => [
            'title' => 'A whole month, no gaps',
            'body' => 'This is the first month whose category split you can fully trust.',
        ],
        'thirty_day_run' => [
            'title' => 'Thirty days in a row',
            'body' => 'A month of unbroken records. Your reports have no holes to work around.',
        ],
    ],

    'preferences_saved' => 'Notification preferences updated.',
    'streak_nudge_label' => 'Evening streak reminder',
    'streak_nudge_hint' => 'A Telegram message at {hour}:00 your time, only on days you have not logged anything yet.',
    'streak_nudge_unavailable' => 'Turn on the Flight log module and link Telegram to use this.',
    'mark_all_read' => 'Mark all as read',
    'empty' => 'No notifications yet',
    'empty_description' => 'Bill reminders and other alerts will show up here.',
    'page_title' => 'Notifications',
    'page_description' => 'Bill reminders and other account alerts.',
    'unread' => 'Unread',
];
