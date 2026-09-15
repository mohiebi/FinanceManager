<?php

return [
    'meta' => [
        'title' => 'Dein Geld, auf Autopilot',
        'description' => 'Geld im Blick behalten, Pläne erstellen, Investments verfolgen und Miles für die passenden Werkzeuge verdienen. Privat als Standard, mit optionalem Zero-Knowledge-Tresor.',
    ],

    'a11y' => [
        'main_navigation' => 'Hauptnavigation',
        'open_menu' => 'Menü öffnen',
        'close_menu' => 'Menü schließen',
        'hero' => 'Hero',
        'trust' => 'Vertrauen',
        'call_to_action' => 'Handlungsaufforderung',
    ],

    'nav' => [
        'features' => 'Funktionen',
        'privacy' => 'Datenschutz',
        'telegram' => 'Telegram-Bot',
        'how_it_works' => 'So funktioniert’s',
        'faq' => 'FAQ',
        'sign_in' => 'Anmelden',
        'get_started' => 'Kostenlos starten',
        'dashboard' => 'Dashboard',
    ],

    'hero' => [
        'badge' => 'Die wichtigsten Finanzfunktionen sind kostenlos',
        'title_top' => 'Dein Geld,',
        'title_highlight' => 'auf Autopilot.',
        'subtitle' => 'Behalte dein Geld im Blick, plane voraus und verdiene Miles für genau die Werkzeuge, die du brauchst.',
        'cta_primary' => 'Jetzt kostenlos starten',
        'cta_secondary' => 'Alle Funktionen ansehen',
        'mock' => [
            'dashboard' => 'Dashboard',
            'currency' => 'Währung: Toman',
            'income' => 'Einnahmen',
            'costs' => 'Ausgaben',
            'balance' => 'Saldo',
            'toman' => 'TOMAN',
            'finance_rate' => 'Finanzquote',
            'rate_hint' => 'Einnahmen ÷ Gesamtfluss',
            'monthly_overview' => 'Monatsübersicht',
        ],
    ],

    'trust' => [
        'encrypted' => [
            'title' => 'Standardmäßig verschlüsselt',
            'text' => 'Eigener Schlüssel pro Konto, optional mit privatem Tresor',
        ],
        'calendars' => [
            'title' => 'Zwei Kalender',
            'text' => 'Jalali und Gregorianisch, jederzeit umschaltbar',
        ],
        'currencies' => [
            'title' => 'Live-Wechselkurse',
            'text' => 'Toman, USD & EUR mit aktuellen Marktkursen',
        ],
        'telegram' => [
            'title' => 'Telegram integriert',
            'text' => 'Ausgaben erfassen und Erinnerungen im Chat erhalten',
        ],
    ],

    'privacy' => [
        'kicker' => 'Datenschutz zuerst',
        'title' => 'Dein Geld geht niemanden sonst etwas an.',
        'subtitle' => 'Jeder Betrag, Titel und jede Notiz wird vor dem Speichern verschlüsselt. Für noch mehr Privatsphäre kann der Schlüssel vollständig in deinen Browser wechseln.',

        'levels' => [
            'standard' => [
                'badge' => 'Stufe 1, Standard',
                'title' => 'Verschlüsselt gespeichert',
                'text' => 'Für jedes Konto ohne Einrichtung aktiv. Deine Beträge, Titel und Notizen werden mit einem eigenen Kontoschlüssel verschlüsselt. Eine gestohlene Datenbank bleibt dadurch unlesbar.',
                'points' => [
                    'encrypted' => 'AES-256-GCM für jeden Betrag, Titel und jede Notiz, bevor sie die Datenbank erreichen.',
                    'per_user' => 'Ein eigener Datenschlüssel pro Konto verhindert, dass ein geleakter Datensatz andere Konten öffnet.',
                    'readable' => 'Wir können sie weiterhin entschlüsseln, und genau das lässt Telegram-Bot, Erinnerungen und KI-Assistent funktionieren.',
                ],
            ],
            'vault' => [
                'badge' => 'Stufe 2, optional',
                'title' => 'Privater Tresor (Zero-Knowledge)',
                'text' => 'Beim Einschalten wandert der Schlüssel in deinen Browser. Wir speichern deine Daten weiter, aber weder unsere Mitarbeitenden noch unsere Server können sie dann lesen.',
                'points' => [
                    'zero_knowledge' => 'Bei uns liegt nur Chiffretext. Es gibt keine Kopie deines Schlüssels, die wir herausgeben könnten.',
                    'browser_key' => 'Deine Passphrase verlässt nie dein Gerät; der Schlüssel entsteht und arbeitet im Browser.',
                    'tradeoff' => 'Der ehrliche Preis: Telegram und der KI-Assistent hören auf zu funktionieren, und wer Passphrase und Wiederherstellungsschlüssel verliert, verliert die Daten.',
                ],
            ],
        ],

        'how' => [
            'title' => 'Wie der Tresor wirklich funktioniert',
            'text' => 'Deine Daten sind mit einem einzigen Datenschlüssel verschlüsselt. Beim Einschalten wird dieser Schlüssel durch eine Passphrase, die nur du kennst, und einen Wiederherstellungsschlüssel, den nur du hast, geschützt. Danach löschen wir unsere Kopie. Der Wechsel ist sofort möglich, weil die Daten nicht neu verschlüsselt werden müssen. Dein Browser übernimmt Entschlüsselung und Berechnungen.',
        ],

        'pledges' => [
            'no_ads' => [
                'title' => 'Niemals Werbung',
                'text' => 'Nichts an deinen Ausgaben wird genutzt, um dir etwas zu verkaufen',
            ],
            'no_selling' => [
                'title' => 'Nie verkauft oder geteilt',
                'text' => 'Deine Finanzdaten sind kein Produkt und werden auch keins',
            ],
            'no_tracking' => [
                'title' => 'Keine Drittanbieter-Tracker',
                'text' => 'Keine Werbepixel oder Analyseprofile verfolgen dich hier',
            ],
            'export' => [
                'title' => 'Deine Daten, exportierbar',
                'text' => 'Nimm jederzeit alles als Tabelle mit',
            ],
        ],
    ],

    'features' => [
        'kicker' => 'Dein Finanz-Cockpit',
        'title' => 'Einfach starten. Passend erweitern.',
        'subtitle' => 'Transaktionen und Berichte bilden den kostenlosen Kern. Verdiene Miles und schalte passende Zusatzmodule dauerhaft frei.',
        'transactions' => [
            'title' => 'Transaktionen erfassen',
            'text' => 'Ausgaben und Einnahmen in Sekunden erfassen, kategorisieren, durchsuchen und nach Datum oder Typ filtern. So wird dein Geld auf einen Blick verständlich.',
            'points' => [
                'Ausgaben & Einnahmen in einer Ansicht',
                'Eigene Kategorien & Beschreibungen',
                'Excel-Import & -Export',
            ],
        ],
        'investments' => [
            'title' => 'Investment-Portfolio',
            'text' => 'Gold, Silber, USD, EUR und Bitcoin verfolgen oder eigene Assets anlegen. Aktuelle Marktpreise synchronisieren oder Werte selbst festlegen.',
            'points' => [
                'Aktuelle Marktkurse synchronisieren (tgju)',
                'Nettovermögen & Portfolio-Aufteilung',
                'Eigene Assets mit eigener Bewertung',
            ],
        ],
        'bills' => [
            'title' => 'Rechnungen & Erinnerungen',
            'text' => 'Monatliche oder einmalige Rechnungen im Jalali- oder gregorianischen Kalender planen. Am Vortag und am Fälligkeitstag erinnern lassen und mit einem Tippen als bezahlt markieren.',
            'points' => [
                'Telegram- & In-App-Erinnerungen',
                'Bezahlte Rechnung wird automatisch als Ausgabe erfasst',
            ],
        ],
        'reports' => [
            'title' => 'Intelligente Berichte',
            'text' => 'Monatliche, saisonale, jährliche oder frei wählbare Zeiträume. Einnahmen und Ausgaben vergleichen und Muster sofort erkennen.',
        ],
        'multi_currency' => [
            'title' => 'Mehrere Währungen',
            'text' => 'Wechsle zwischen Toman, USD und EUR. Beträge werden mit aktuellen Marktkursen umgerechnet.',
        ],
        'miles' => [
            'title' => 'Fortschritt, der mehr freischaltet',
            'text' => 'Verdiene Miles für regelmäßige Nutzung und Meilensteine, niemals für die Höhe deines Vermögens. Nutze sie für dauerhafte Modulfreischaltungen und den Schutz deiner Serie.',
            'points' => [
                '150 Miles zum Start',
                'Tägliche Abholung und Aktivitätsprämien',
                'Freigeschaltete Module bleiben erhalten',
            ],
        ],
        'planning' => [
            'title' => 'Flugpläne und Sparziele',
            'text' => 'Plane jede Kategorie, verfolge den verbleibenden Betrag und setze Sparziele in Geld, Gold, Währungen oder eigenen Assets.',
        ],
        'advisor' => [
            'title' => 'KI-Portfolio-Berater',
            'text' => 'Eine Risikoeinschätzung wird zu einem klar begrenzten Anlegerprofil und zu Lernempfehlungen für eine passende Zielallokation.',
        ],
        'security' => [
            'title' => 'Privat von Grund auf',
            'text' => 'Daten werden mit einem eigenen Schlüssel pro Konto verschlüsselt gespeichert. Im privaten Tresor bleibt der Schlüssel ausschließlich in deinem Browser. E-Mail-Codes, Zwei-Faktor-Authentifizierung und Google-Anmeldung werden unterstützt.',
        ],
    ],

    'telegram' => [
        'kicker' => 'Dein Finanz-Bot',
        'title' => 'Alles direkt aus Telegram',
        'subtitle' => 'Schalte das Telegram-Modul einmal frei und erfasse Ausgaben, erstelle Rechnungen und markiere sie als bezahlt, ohne die App zu öffnen.',
        'points' => [
            'add' => [
                'title' => 'Transaktionen im Chat erfassen',
                'text' => 'Ein geführter Assistent erfasst eine Ausgabe in wenigen Schritten.',
            ],
            'bills' => [
                'title' => 'Erinnerungen, die dich finden',
                'text' => 'Eine Nachricht am Vortag und am Fälligkeitstag.',
            ],
            'paid' => [
                'title' => 'Bezahlt markieren, sofort erfasst',
                'text' => 'Auf „Bezahlt“ tippen und die Ausgabe wird automatisch verbucht.',
            ],
        ],
        'chat' => [
            'bot_name' => 'CashPilot Bot',
            'status' => 'online',
            'reminder' => '🔔 Erinnerung: „Internetrechnung“ ist morgen fällig. 450.000 Toman',
            'mark_paid' => 'Bezahlt ✓',
            'confirmation' => '✅ Bezahlt! Ich habe eine Ausgabe von 450.000 Toman unter „Nebenkosten“ erfasst.',
            'user_add' => 'Ausgabe hinzufügen',
            'wizard' => '💸 Wofür hast du Geld ausgegeben?',
            'user_reply' => 'Lebensmittel, 850.000',
            'done' => '✅ Gespeichert: 850.000 Toman · Lebensmittel',
        ],
    ],

    'how' => [
        'kicker' => 'Einfache Einrichtung',
        'title' => 'In Minuten startklar',
        'steps' => [
            'account' => [
                'title' => 'Konto erstellen',
                'text' => 'In Sekunden mit E-Mail oder Google registrieren. Dein Konto startet mit 150 Miles und braucht keine Kreditkarte.',
            ],
            'log' => [
                'title' => 'Module auswählen',
                'text' => 'Starte mit Transaktionen und Berichten. Mit Miles schaltest du die gewünschten Planungs-, Investment- und Automationsmodule dauerhaft frei.',
            ],
            'insights' => [
                'title' => 'Einblicke erhalten',
                'text' => 'Dashboard und Berichte wachsen mit deinen Einträgen. Pläne, Ziele und Portfolio zeigen dir, was als Nächstes wichtig ist.',
            ],
        ],
    ],

    'faq' => [
        'kicker' => 'Fragen',
        'title' => 'Häufig gefragt',
        'items' => [
            'private' => [
                'q' => 'Sind meine Finanzdaten privat?',
                'a' => 'Ja. Beträge, Titel und Notizen werden vor dem Speichern mit einem eigenen Schlüssel pro Konto verschlüsselt. Der optionale private Tresor verschiebt diesen Schlüssel in deinen Browser, sodass der Server deine Finanzdaten nicht mehr entschlüsseln kann.',
            ],
            'miles' => [
                'q' => 'Was sind Miles?',
                'a' => 'Miles belohnen regelmäßige Nutzung und Meilensteine, niemals Kontostände oder Nettovermögen. Du kannst sie täglich abholen und durch Aktivität verdienen. Anschließend nutzt du sie für dauerhafte Modulfreischaltungen und Serienschutz.',
            ],
            'advisor' => [
                'q' => 'Was macht der KI-Portfolio-Berater?',
                'a' => 'Er überführt deine Risikoeinschätzung in ein klar begrenztes Anlegerprofil und schlägt innerhalb dieser Grenzen eine Zielallokation zu Lernzwecken vor. Er führt keine Trades aus und ersetzt keine professionelle Finanzberatung.',
            ],
            'calendar' => [
                'q' => 'Wird der Jalali-Kalender unterstützt?',
                'a' => 'Vollständig. Datumsangaben, Berichte und Fälligkeiten funktionieren in Jalali oder Gregorianisch. Wähle deinen Kalender in den Einstellungen und wechsle jederzeit.',
            ],
            'telegram' => [
                'q' => 'Was kann der Telegram-Bot?',
                'a' => 'Schalte das Modul frei und verbinde dein Konto einmal in den Einstellungen. Danach kannst du im Chat Transaktionen und Rechnungen anlegen, Fälligkeiten ansehen, Zahlungen markieren und Erinnerungen erhalten.',
            ],
            'free' => [
                'q' => 'Ist es wirklich kostenlos?',
                'a' => 'Transaktionen und Berichte sind kostenlos. Neue Konten erhalten 150 Miles und können weitere Miles in der App verdienen, um Zusatzmodule dauerhaft freizuschalten. Für bereits freigeschaltete Module ist kein Abo nötig.',
            ],
            'currencies' => [
                'q' => 'Welche Währungen werden unterstützt?',
                'a' => 'Toman, US-Dollar und Euro. Umrechnungen nutzen aktuelle Marktkurse, die du in der App aktualisieren kannst.',
            ],
        ],
    ],

    'pricing' => [
        'title' => 'Mit dem kostenlosen Kern starten',
        'text' => 'Verdiene Miles in der App und schalte Zusatzmodule einmalig frei. Freigeschaltete Module bleiben dir erhalten.',
        'cta' => 'Noch heute kostenlos starten',
    ],

    'cta' => [
        'title_top' => 'Übernimm die Kontrolle über deine',
        'title_highlight' => 'finanzielle Zukunft',
        'subtitle' => 'Starte mit dem kostenlosen Kern, verdiene deine ersten Miles und passe CashPilot an deinen Umgang mit Geld an.',
        'button' => 'Kostenlos starten',
    ],

    'footer' => [
        'tagline' => 'Mit ♥ für persönliche Finanzen gebaut.',
        'sign_in' => 'Anmelden',
        'get_started' => 'Jetzt starten',
    ],

    'auth_panel' => [
        'tagline' => 'Alles an einem einfachen, leistungsstarken Ort',
        'headline_1' => 'Dein',
        'headline_2' => 'Geld,',
        'headline_3' => 'auf Autopilot',
        'description' => 'Überblicke deine Finanzen klar, behalte die Kontrolle und triff klügere Entscheidungen ohne Rätselraten.',
    ],
];
