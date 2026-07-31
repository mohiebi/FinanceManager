<?php

return [
    'title' => 'Module',
    'description' => 'Schalte Teile von CashPilot ein und aus. Ein ausgeschaltetes Modul löscht nichts — deine Daten sind wieder da, sobald du es erneut einschaltest.',
    'locked' => 'Schalte das Modul :module ein, um diese Seite zu nutzen.',
    'telegram_locked' => 'Das Modul :module ist ausgeschaltet. Schalte es unter Einstellungen > Module ein, um es hier zu nutzen.',
    'enabled' => 'Aktiv',
    'disabled' => 'Inaktiv',
    'core_badge' => 'Immer aktiv',
    'hide_from_menu' => 'Aus dem Menü ausblenden',
    'hide_from_menu_hint' => 'Nicht mehr in der Seitenleiste anzeigen, bis du es einschaltest.',
    'saved' => 'Module aktualisiert.',
    'cascade_enabled' => 'Ebenfalls eingeschaltet: :features.',
    'cascade_disabled' => 'Ebenfalls ausgeschaltet: :features.',
    'confirm_disable_title' => '{module} ausschalten?',
    'confirm_disable_body' => 'Damit wird auch {features} ausgeschaltet. Deine Daten bleiben erhalten und sind wieder da, sobald du es erneut einschaltest.',
    'confirm_disable_action' => 'Ausschalten',
    'cancel' => 'Abbrechen',
    'requires' => 'Benötigt {features}',

    'manage' => 'In Datenschutz & Sicherheit verwalten',
    'managed_elsewhere' => 'Wird auf einer eigenen Seite eingeschaltet, weil dabei der Schlüssel zu deinen Daten gewechselt wird.',

    'tiers' => [
        'free' => 'Kostenlos',
        'pro' => 'Pro',
    ],

    'vault' => [
        'label' => 'Privater Tresor',
        'description' => 'Halte den einzigen Schlüssel zu deinen Daten. CashPilot kann deine Beträge und Titel dann nicht mehr lesen — und niemand kann uns dazu zwingen.',
    ],

    'transactions' => [
        'label' => 'Transaktionen',
        'description' => 'Erfasse Einnahmen und Ausgaben. Die Grundlage für alles andere.',
    ],
    'categories' => [
        'label' => 'Kategorien',
        'description' => 'Gruppiere deine Transaktionen, damit Berichte aussagekräftig werden.',
    ],
    'reports' => [
        'label' => 'Berichte',
        'description' => 'Schlüssle Einnahmen und Ausgaben nach Kategorie und Monat auf.',
    ],
    'bills' => [
        'label' => 'Rechnungen',
        'description' => 'Verfolge wiederkehrende und einmalige Rechnungen und werde vor Fälligkeit erinnert.',
    ],
    'investments' => [
        'label' => 'Investitionen',
        'description' => 'Erfasse deine Bestände — Gold, Devisen, Krypto — und verfolge Live-Kurse.',
    ],
    'portfolio' => [
        'label' => 'Portfolio',
        'description' => 'Sieh dein Nettovermögen sowie Gewinn und Verlust über alle Anlagen hinweg.',
    ],
    'gamification' => [
        'label' => 'Flugbuch',
        'description' => 'Halte eine Serie an Eintragungen, markiere Tage ohne Ausgaben und sieh, wie vollstaendig jeder Monat erfasst ist. Nie darum, wie viel du ausgibst — nur darum, die Aufzeichnungen lueckenlos zu halten.',
    ],
    'ai_assistant' => [
        'label' => 'KI-Assistent',
        'description' => 'Verbinde KI-Tools, die deine Finanzdaten lesen und Aenderungen zur Freigabe vorschlagen koennen.',
    ],
    'telegram_bot' => [
        'label' => 'Telegram-Bot',
        'description' => 'Nutze CashPilot in Telegram, um Eintraege zu erfassen, Zusammenfassungen zu pruefen und Erinnerungen zu erhalten.',
    ],
];
