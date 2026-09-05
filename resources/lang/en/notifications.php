<?php

return [
    // Fires BillReminderJob::ADVANCE_REMINDER_DAYS days ahead — keep the "3" here
    // in sync with that constant.
    'bill_due_tomorrow' => [
        'title' => 'Bill due soon',
        // Sent live to Telegram, never stored by us.
        'body' => ':title is due in 3 days (:date). Amount: :amount :currency.',
        // Stored in the notifications table, which is not encrypted — so it must
        // not repeat the bill's title or amount.
        'body_generic' => 'A bill is due in 3 days (:date).',
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
        'verified_email' => [
            'title' => 'Account verified',
            'body' => 'Your address is confirmed, so resets and reports can reach you.',
        ],
        'first_bill' => [
            'title' => 'First bill scheduled',
            'body' => 'Due dates now appear on your calendar and in your reminders.',
        ],
        'first_budget' => [
            'title' => 'First flight plan set',
            'body' => 'Spending now has a limit to measure against, category by category.',
        ],
        'first_investment' => [
            'title' => 'First holding recorded',
            'body' => 'Your portfolio has something to value and track from here.',
        ],
        'first_savings_goal' => [
            'title' => 'First goal set',
            'body' => 'Progress now has a target, measured in the asset you save in.',
        ],
        'three_day_run' => [
            'title' => 'Three days in a row',
            'body' => 'Long enough to be a habit forming rather than one good day.',
        ],
        'first_flight_week' => [
            'title' => 'A full reward cycle',
            'body' => 'Seven claims collected. The cycle begins again at day one.',
        ],
        'fourteen_day_run' => [
            'title' => 'Two weeks unbroken',
            'body' => 'Half a month of records with nothing missing from the middle.',
        ],
        'pilot_rank' => [
            'title' => 'Pilot',
            'body' => 'Thirty days recorded. Enough history for month-on-month comparisons to mean something.',
        ],
        'captain_rank' => [
            'title' => 'Captain',
            'body' => 'A hundred and eighty days recorded. Your history now covers a full half-year.',
        ],
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
    'bill_advance_reminder_label' => 'Advance bill reminder',
    'bill_advance_reminder_hint' => 'Also send a Telegram reminder {days} days before a bill is due, not just on the due date.',
    'bill_advance_reminder_unavailable' => 'Turn on the Bills module and link Telegram to use this.',
    'filter' => [
        'all' => 'All',
        'unread' => 'Unread',
    ],
    'groups' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'earlier' => 'Earlier',
    ],
    'unread_badge' => '{count} unread',
    'mark_read' => 'Mark as read',
    'empty_unread' => 'You are all caught up',
    'empty_unread_description' => 'Nothing is waiting to be read.',
    'mark_all_read' => 'Mark all as read',
    'empty' => 'No notifications yet',
    'empty_description' => 'Bill reminders and other alerts will show up here.',
    'preferences_heading' => 'Notification preferences',
    'preferences_description' => 'Choose which reminders CashPilot sends you.',
    'page_title' => 'Notifications',
    'page_description' => 'Bill reminders and other account alerts.',
    'unread' => 'Unread',

    // Server-rendered with Laravel :placeholders, unlike resources/lang/*/billing.php
    // which the browser renders. Stored bodies deliberately carry no transaction
    // hash or wallet address: notifications.data is not encrypted.
    'subscription_action' => 'Open billing',
    'review_action' => 'Open subscriptions',

    'payment_needs_review' => [
        'title' => 'A payment needs your decision',
        'body_generic' => ':count payment(s) could not be settled automatically.',
        'email_body' => ':count payment(s) could not be settled automatically and are waiting on you. The most recent stopped because: :reason Nobody is told their payment failed until you decide, so they are still waiting.',
    ],

    'subscription_activated' => [
        'title' => 'Pro is active',
        'body_generic' => 'Your :plan plan runs until :date.',
        'email_body' => 'Thank you — your payment went through. Your :plan plan runs until :date. Nothing renews by itself, so we will remind you before it ends.',
    ],
    'subscription_payment_failed' => [
        'title' => 'We could not confirm your payment',
        'body_generic' => 'Your :plan payment could not be confirmed.',
        'email_body' => 'We could not confirm your :plan payment. :reason If you believe this is wrong, reply to this email and we will look into it — nothing is lost.',
    ],
    'subscription_expiring' => [
        'title' => 'Your Pro access ends soon',
        'body_generic' => 'Pro ends on :date.',
        'email_body' => 'Your Pro access ends on :date, in :days days. Crypto payments cannot be taken again automatically, so it will not renew on its own.',
    ],
    'subscription_expired' => [
        'title' => 'Your Pro access has ended',
        'body_generic' => 'Pro ended on :date.',
        'email_body' => 'Your Pro access ended on :date. Your data is untouched and everything free still works exactly as before.',
    ],
];
