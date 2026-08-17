<?php

return [
    'bill_due_tomorrow' => [
        'title' => 'Rechnung bald fällig',
        'body' => ':title ist in 3 Tagen fällig (:date). Betrag: :amount :currency.',
        'body_generic' => 'Eine Rechnung ist in 3 Tagen fällig (:date).',
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
    'bill_advance_reminder_label' => 'Frühzeitige Rechnungserinnerung',
    'bill_advance_reminder_hint' => 'Sende zusätzlich {days} Tage vor Fälligkeit eine Telegram-Erinnerung, nicht nur am Fälligkeitstag.',
    'bill_advance_reminder_unavailable' => 'Schalte das Rechnungen-Modul ein und verbinde Telegram, um dies zu nutzen.',
    'filter' => [
        'all' => 'Alle',
        'unread' => 'Ungelesen',
    ],
    'groups' => [
        'today' => 'Heute',
        'yesterday' => 'Gestern',
        'earlier' => 'Früher',
    ],
    'unread_badge' => '{count} ungelesen',
    'mark_read' => 'Als gelesen markieren',
    'empty_unread' => 'Alles gelesen',
    'empty_unread_description' => 'Es wartet nichts auf dich.',
    'mark_all_read' => 'Alle als gelesen markieren',
    'empty' => 'Noch keine Benachrichtigungen',
    'empty_description' => 'Rechnungserinnerungen und andere Hinweise werden hier angezeigt.',
    'preferences_heading' => 'Benachrichtigungseinstellungen',
    'preferences_description' => 'Wähle, welche Erinnerungen CashPilot dir sendet.',
    'page_title' => 'Benachrichtigungen',
    'page_description' => 'Rechnungserinnerungen und andere Kontohinweise.',
    'unread' => 'Ungelesen',

    'subscription_action' => 'Zur Abrechnung',
    'review_action' => 'Zu den Abonnements',

    'payment_needs_review' => [
        'title' => 'Eine Zahlung braucht deine Entscheidung',
        'body_generic' => ':count Zahlung(en) konnten nicht automatisch abgeschlossen werden.',
        'email_body' => ':count Zahlung(en) konnten nicht automatisch abgeschlossen werden und warten auf dich. Die letzte scheiterte an: :reason Bis du entscheidest, erfährt niemand, dass seine Zahlung fehlgeschlagen ist — sie warten also noch.',
    ],

    'payment_screening_delayed' => [
        'title' => 'Die Quellenprüfung ist verzögert',
        'body_generic' => ':count Zahlung(en) warten auf die Quellenprüfung.',
        'email_body' => ':count auf der Chain bestätigte Zahlung(en) warten auf ein verlässliches Prüfergebnis. Es wurde kein Zugang gewährt. Wiederhole die Prüfung nach Wiederherstellung des Anbieters in der Abonnementverwaltung.',
    ],

    'deposit_pool_low' => [
        'title' => 'Der Pool der Einzahlungsadressen ist niedrig',
        'body_generic' => ':network hat noch :count unbenutzte Einzahlungsadresse(n).',
        'email_body' => ':network hat noch :count unbenutzte Einmal-Einzahlungsadresse(n). Der Checkout stoppt bei null automatisch; importiere vorher einen weiteren offline erzeugten Stapel öffentlicher Adressen.',
    ],

    'payment_quarantined' => [
        'title' => 'Zahlungsmittel wurden quarantänisiert',
        'body_generic' => ':count Einzahlung(en) sind dauerhaft quarantänisiert.',
        'email_body' => ':count Einzahlung(en) wurden nach der Quellenprüfung dauerhaft quarantänisiert. Sie können nicht freigegeben, erstattet, gelöst oder übertragen werden.',
    ],

    'subscription_payment_quarantined' => [
        'title' => 'Deine Zahlung konnte nicht akzeptiert werden',
        'body_generic' => 'Dein :plan-Tarif wurde nach der Quellenprüfung nicht aktiviert.',
        'email_body' => ':explanation Dein :plan-Tarif wurde nicht aktiviert. Aus Sicherheitsgründen kann die Zahlung weder bewegt noch zurückgesendet werden. Erstelle bitte eine neue Zahlung und verwende eine andere Wallet.',
        'sanctioned_explanation' => 'Die Absenderadresse stimmte mit einer Sanktionsliste überein.',
        'flagged_explanation' => 'Die Zahlungsquelle wurde von der Transaktionsrisikoprüfung markiert.',
    ],

    'subscription_activated' => [
        'title' => 'Pro ist aktiv',
        'body_generic' => 'Dein Tarif :plan läuft bis :date.',
        'email_body' => 'Danke — deine Zahlung ist angekommen. Dein Tarif :plan läuft bis :date. Nichts verlängert sich von selbst, wir erinnern dich also rechtzeitig.',
    ],
    'subscription_payment_failed' => [
        'title' => 'Wir konnten deine Zahlung nicht bestätigen',
        'body_generic' => 'Deine Zahlung für :plan konnte nicht bestätigt werden.',
        'email_body' => 'Wir konnten deine Zahlung für :plan nicht bestätigen. :reason Wenn das nicht stimmt, antworte einfach auf diese E-Mail — es ist nichts verloren.',
    ],
    'subscription_expiring' => [
        'title' => 'Dein Pro-Zugang endet bald',
        'body_generic' => 'Pro endet am :date.',
        'email_body' => 'Dein Pro-Zugang endet am :date, in :days Tagen. Krypto-Zahlungen lassen sich nicht erneut einziehen, er verlängert sich also nicht von selbst.',
    ],
    'subscription_expired' => [
        'title' => 'Dein Pro-Zugang ist abgelaufen',
        'body_generic' => 'Pro endete am :date.',
        'email_body' => 'Dein Pro-Zugang endete am :date. Deine Daten sind unverändert, und alles Kostenlose funktioniert genau wie vorher.',
    ],
];
