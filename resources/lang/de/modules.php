<?php

return [
    'title' => 'Module',
    'description' => 'Schalte Teile von CashPilot ein und aus. Ein ausgeschaltetes Modul löscht nichts — deine Daten sind wieder da, sobald du es erneut einschaltest.',
    'locked' => 'Schalte das Modul :module ein, um diese Seite zu nutzen.',
    'telegram_locked' => 'Das Modul :module ist ausgeschaltet. Schalte es unter Einstellungen > Module ein, um es hier zu nutzen.',
    'enabled' => 'Aktiv',
    'disabled' => 'Inaktiv',
    'core_badge' => 'Immer aktiv',
    'core_heading' => 'Immer aktiv',
    'core_description' => 'Die Teile, ohne die CashPilot nicht läuft. Hier aufgeführt, damit du weißt, was aktiv ist – nicht damit du es abschaltest.',
    'optional_heading' => 'Optionale Module',
    'hide_from_menu' => 'Aus dem Menü ausblenden',
    'hide_from_menu_hint' => 'Nicht mehr in der Seitenleiste anzeigen, bis du es einschaltest.',
    'saved' => 'Module aktualisiert.',
    'cascade_enabled' => 'Ebenfalls eingeschaltet: :features.',
    'cascade_disabled' => 'Ebenfalls ausgeschaltet: :features.',
    'confirm_disable_title' => '{module} ausschalten?',
    'confirm_disable_body' => 'Damit wird auch {features} ausgeschaltet. Deine Daten bleiben erhalten und sind wieder da, sobald du es erneut einschaltest.',
    'confirm_disable_body_simple' => 'Deine Daten bleiben erhalten und kommen zurück, wenn du es wieder einschaltest.',
    'confirm_disable_paid' => 'Die Miles für die Freischaltung werden nicht erstattet, aber sie gehört dir dauerhaft — das spätere Wiedereinschalten kostet nichts.',
    'confirm_disable_action' => 'Ausschalten',
    'cancel' => 'Abbrechen',
    'requires' => 'Benötigt {features}',
    'activation' => [
        'title' => '{module} aktivieren?',
        'body' => 'Dadurch werden {features} dauerhaft für {miles} Meilen freigeschaltet. Späteres Deaktivieren ist kostenlos und behält den Besitz.',
        'shortfall' => 'Dir fehlen noch {miles} Meilen.',
        'confirm' => 'Für {miles} aktivieren',
    ],

    'upgrade' => [
        'title' => '{module} ist ein Pro-Modul',
        'body' => 'Pro deckt die Module ab, die bei jeder Nutzung einen kostenpflichtigen KI-Dienst aufrufen. Alles andere in CashPilot bleibt kostenlos.',
        'note' => 'Zahle mit Krypto für einen Monat, ein Quartal oder ein Jahr. Nichts verlängert sich von selbst.',
        'unavailable' => 'Pro ist noch nicht im Verkauf. Sobald es so weit ist, wird dieses Modul freigeschaltet.',
        'continue' => 'Pro-Tarife ansehen',
        'later' => 'Später vielleicht',
    ],

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
    'reports' => [
        'label' => 'Berichte',
        'description' => 'Schlüssle Einnahmen und Ausgaben nach Kategorie und Monat auf.',
    ],
    'bills' => [
        'label' => 'Rechnungen',
        'description' => 'Verfolge wiederkehrende und einmalige Rechnungen und werde vor Fälligkeit erinnert.',
    ],
    'budgets' => [
        'label' => 'Flugplan',
        'description' => 'Entscheide vorab, wohin dein Geld geht — ein Anteil deines Einkommens, ein fester Betrag, oder der Rest.',
    ],
    'investments' => [
        'label' => 'Investitionen',
        'description' => 'Erfasse deine Bestände — Gold, Devisen, Krypto — und verfolge Live-Kurse.',
    ],
    'portfolio' => [
        'label' => 'Portfolio',
        'description' => 'Sieh dein Nettovermögen sowie Gewinn und Verlust über alle Anlagen hinweg.',
    ],
    'goals' => [
        'label' => 'Sparziele',
        'description' => 'Setze Ziele in der Anlage, in der du tatsaechlich sparst — Gramm Gold, Dollar — damit Inflation sie nicht still auffrisst.',
    ],
    'gamification' => [
        'label' => 'Flugbuch',
        'description' => 'Halte eine Serie an Eintragungen, markiere Tage ohne Ausgaben und sieh, wie vollstaendig jeder Monat erfasst ist. Nie darum, wie viel du ausgibst — nur darum, die Aufzeichnungen lueckenlos zu halten.',
    ],
    'ai_assistant' => [
        'label' => 'KI-Assistent',
        'description' => 'Verbinde KI-Tools, die deine Finanzdaten lesen und Aenderungen zur Freigabe vorschlagen koennen.',
    ],
    'advisor' => [
        'label' => 'KI-Portfolio-Berater',
        'description' => 'Erstellt dein CashPilot-Risikoprofil und ein klar begrenztes KI-Zielportfolio.',
    ],
    'telegram_bot' => [
        'label' => 'Telegram-Bot',
        'description' => 'Nutze CashPilot in Telegram, um Eintraege zu erfassen, Zusammenfassungen zu pruefen und Erinnerungen zu erhalten.',
    ],

    'display' => [
        'heading' => 'Anzeige',
        'compact_figures_label' => 'Kompakte Zahlen',
        'compact_figures_description' => 'Zeigt 988,7 Mio. statt 988.691.514 bei Zahlen, die nur auf einen Blick zählen, nicht für die Buchhaltung.',
    ],
];
