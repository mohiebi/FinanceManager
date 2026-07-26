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
    'mark_all_read' => 'Mark all as read',
    'empty' => 'No notifications yet',
    'empty_description' => 'Bill reminders and other alerts will show up here.',
    'page_title' => 'Notifications',
    'page_description' => 'Bill reminders and other account alerts.',
    'unread' => 'Unread',
];
