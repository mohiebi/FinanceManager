<?php

return [
    'bill_due_tomorrow' => [
        'title' => 'Rechnung morgen fällig',
        'body' => ':title ist morgen fällig (:date). Betrag: :amount :currency.',
        'body_generic' => 'Eine Rechnung ist morgen fällig (:date).',
    ],
    'bill_due_today' => [
        'title' => 'Rechnung heute fällig',
        'body' => ':title ist heute fällig (:date). Betrag: :amount :currency.',
        'body_generic' => 'Eine Rechnung ist heute fällig (:date).',
    ],
    'streak_open' => [
        'title' => 'Deine Serie ist noch offen',
        'body' => 'Tag :days ist noch offen. Erfasse vor Mitternacht irgendetwas, dann haelt die Serie.',
        'body_first' => 'Heute ist noch nichts erfasst. Erfasse vor Mitternacht etwas, um eine Serie zu starten.',
    ],
    'milestones' => [
        'first_transaction' => [
            'title' => 'Erster Eintrag',
            'body' => 'Dein Logbuch ist eroeffnet. Alles andere in CashPilot baut darauf auf.',
        ],
        'hundred_transactions' => [
            'title' => 'Hundertster Eintrag',
            'body' => 'Jetzt liegt genug Historie vor, dass die Trends lesenswert sind.',
        ],
        'first_full_month' => [
            'title' => 'Ein ganzer Monat, ohne Luecken',
            'body' => 'Der erste Monat, dessen Kategorienaufteilung du voll vertrauen kannst.',
        ],
        'thirty_day_run' => [
            'title' => 'Dreissig Tage in Folge',
            'body' => 'Ein Monat lueckenloser Eintraege. Deine Berichte haben keine Loecher.',
        ],
    ],

    'preferences_saved' => 'Benachrichtigungseinstellungen aktualisiert.',
    'streak_nudge_label' => 'Abendliche Serien-Erinnerung',
    'streak_nudge_hint' => 'Eine Telegram-Nachricht um {hour}:00 deiner Zeit, nur an Tagen ohne Eintrag.',
    'streak_nudge_unavailable' => 'Schalte das Flugbuch-Modul ein und verbinde Telegram, um dies zu nutzen.',
    'mark_all_read' => 'Alle als gelesen markieren',
    'empty' => 'Noch keine Benachrichtigungen',
    'empty_description' => 'Rechnungserinnerungen und andere Hinweise werden hier angezeigt.',
    'page_title' => 'Benachrichtigungen',
    'page_description' => 'Rechnungserinnerungen und andere Kontohinweise.',
    'unread' => 'Ungelesen',
];
