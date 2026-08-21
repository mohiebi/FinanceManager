<?php

$messages = require resource_path('lang/en/advisor.php');
$messages['title'] = 'CashPilot Berater';
$messages['eyebrow'] = 'Private Vermögensintelligenz';
$messages['tagline'] = 'Dein Portfolio sollte zu deinem Leben passen — nicht nur zum Markt.';
$messages['start'] = 'Bewertung starten';
$messages['view_profile'] = 'Risikoprofil ansehen';

$messages['resume_at'] = 'Fortsetzen — Abschnitt {current} von {total}';
$messages['question_count'] = '22 Fragen, dann dein Portfolio';
$messages['duration'] = 'Etwa 15 Minuten';

$messages['assessment'] = array_merge($messages['assessment'], [
    'title' => 'Anlegerprofil',
    'step' => 'Abschnitt {current} von {total}',
    'saved' => 'Deine Antworten werden verschlüsselt und nach jedem Abschnitt gespeichert.',
    'continue' => 'Speichern und weiter',
    'back' => 'Zurück',
    'finish' => 'Mein Profil erstellen',
    'loading' => 'Deine Antworten werden geladen…',
    'vault_notice' => 'Deine Antworten werden entsperrt — entschlüsselt wird hier, nie auf unseren Servern.',
    'needs_answer' => 'Noch ohne Antwort',
    'missing_one' => '1 Frage braucht noch eine Antwort.',
    'missing_many' => '{count} Fragen brauchen noch eine Antwort.',
    'missing_country' => 'Gib das Land an, aus dem du investierst.',
    'missing_markets' => 'Gib mindestens einen Markt an, auf den du Zugriff hast.',
    'missing_assets' => 'Wähle mindestens einen Wert, den der Advisor nutzen darf.',
    'missing_asset_name' => 'Einem deiner Werte fehlt noch ein Name.',
    'missing_asset_ticker' => '{name} braucht ein Kürzel oder eine Kennung.',
    'save_failed' => 'Das wurde nicht gespeichert. Bitte versuche es erneut.',
]);

$messages['questions'] = array_merge($messages['questions'], [
    'liquidity_amount' => 'Wie viel',
    'liquidity_amount_placeholder' => 'Betrag wählen',
    'liquidity_speed' => 'Wie schnell',
    'liquidity_speed_placeholder' => 'Zeitraum wählen',
    'q15_max_drawdown_hint' => 'Nimm an, dass du das Geld nicht sofort brauchst und der Rückgang den ganzen Markt betrifft — nicht Betrug oder das Scheitern eines einzelnen Werts.',
]);

$messages['portfolio'] = array_merge($messages['portfolio'], [
    'details' => 'Details',
    'details_hint' => 'Diese starten mit sinnvollen Vorgaben. Ändere nur, was du über den Wert bereits weißt.',
]);

$messages['profile'] = array_merge($messages['profile'], [
    'ai_scope' => 'Die KI sieht genau dieses Profil und sonst nichts über dich. CashPilot prüft ihre vollständige Antwort, bevor du sie siehst.',
    'options_willingness' => 'Bereitschaft',
    'options_capability_level' => 'Fähigkeit',
    'options_knowledge' => 'Wissen',
    'options_risk_budget' => 'Risikobudget',
]);

$messages['recommendation'] = array_merge($messages['recommendation'], [
    'generating' => 'Dein Portfolio wird entworfen',
    'holdings' => 'Positionen',
    'largest' => 'Größte Position',
    'why' => 'Warum diese Gewichtung',
    'coverage' => 'Abdeckung',
    'risk_budget' => 'Risikobudget',
    'asset' => 'Anlage',
    'current' => 'Aktuell',
    'target' => 'Ziel',
    'move' => 'Veränderung',
    'difference' => 'Differenz',
    'pricing_required' => 'Preis erforderlich',
    'prices_missing' => 'Für einige Anlagen liegt kein aktueller Preis vor, daher sind exakte Beträge nicht verfügbar.',
    'starting' => 'Wird gestartet…',
    'stage_reading' => 'Dein Profil wird gelesen',
    'stage_designing' => 'Dein Portfolio wird entworfen',
    'stage_checking' => 'Abgleich mit deinen Grenzen',
    'stage_sealing' => 'Wird in deinem Browser verschlüsselt',
    'leave_safe' => 'Du kannst diese Seite verlassen. Dein Portfolio ist da, wenn du zurückkommst.',
    'locked' => 'Entsperre deinen Vault, um diese Empfehlung zu lesen.',
    'validated_badge' => 'Von CashPilot geprüft',
    'integrity_failed' => 'Die entschlüsselte Empfehlung stimmte nicht mit der erzeugten überein und wird deshalb nicht angezeigt.',
    'chat_encrypted' => 'Dieses Gespräch ist in deinem Browser verschlüsselt.',
    'ask_empty' => 'Frag alles zu diesem Plan.',
    'suggest_fit' => 'Warum passt diese Aufteilung zu mir?',
    'suggest_risk' => 'Was ist hier das größte Risiko?',
    'suggest_start' => 'Womit fange ich an?',
    'send_failed' => 'Nicht gesendet.',
    'retry' => 'Erneut versuchen',
    'send_hint' => 'Enter sendet, Shift+Enter macht eine neue Zeile.',
    'failed' => 'Der Advisor konnte diesen Plan nicht fertigstellen. Deine Antworten sind gespeichert — versuche es erneut.',
]);

$messages['validation'] = array_merge($messages['validation'], [
    'pending_payload_expired' => 'Diese Empfehlung wartete zu lange auf das Entsperren und musste verworfen werden. Eine neue zu erzeugen dauert einige Minuten.',
]);

$messages['personas'] = [
    'balanced_investor' => 'Ausgewogen',
    'strategic_growth_investor' => 'Strategisch wachstumsorientiert',
    'opportunistic_investor' => 'Opportunistisch',
    'aggressive_growth_investor' => 'Offensiv wachstumsorientiert',
];
$messages['risk_bands'] = [
    'very_conservative' => 'Sehr konservativ', 'conservative' => 'Konservativ', 'balanced' => 'Ausgewogen', 'growth' => 'Wachstum', 'aggressive' => 'Offensiv',
    'defensive' => 'Defensiv', 'moderate' => 'Moderat', 'speculative' => 'Spekulativ', 'unknown' => 'Nicht eingeordnet',
];
$messages['categories'] = [
    'stock' => 'Aktie', 'etf' => 'ETF oder Indexfonds', 'bond' => 'Anleihe', 'currency' => 'Währung', 'metal' => 'Edelmetall', 'crypto' => 'Krypto', 'commodity' => 'Rohstoff', 'real_estate' => 'Immobilien', 'private_asset' => 'Privater Wert', 'other' => 'Sonstiges',
];
$messages['liquidities'] = [
    'same_day' => 'Am selben Tag', 'within_week' => 'Binnen einer Woche', 'within_month' => 'Binnen eines Monats', 'illiquid' => 'Schwer verkäuflich',
];
$messages['perspectives'] = [
    'bearish' => 'Fallend', 'neutral' => 'Neutral', 'bullish' => 'Steigend',
];
$messages['convictions'] = [
    'low' => 'Geringe Überzeugung', 'medium' => 'Mittlere Überzeugung', 'high' => 'Hohe Überzeugung',
];
$messages['holding_periods'] = [
    'under_1_year' => 'Unter einem Jahr', '1_3_years' => '1–3 Jahre', '3_5_years' => '3–5 Jahre', '5_10_years' => '5–10 Jahre', '10_plus' => 'Über 10 Jahre',
];
$messages['inclusions'] = [
    'allowed' => 'KI darf ihn nutzen', 'required' => 'Muss enthalten sein',
];
$messages['underlyings'] = [
    'stocks' => 'Aktien', 'etfs' => 'ETFs', 'indices' => 'Indizes', 'commodities' => 'Rohstoffe', 'currencies' => 'Währungen', 'crypto' => 'Krypto',
];
$messages['options_experience_years'] = [
    'none' => 'Keine', 'under_1' => 'Unter einem Jahr', '1_3' => '1–3 Jahre', '3_plus' => 'Über 3 Jahre',
];
$messages['options_trade_counts'] = [
    'none' => 'Keine', '1_10' => '1–10', '11_50' => '11–50', '50_plus' => 'Über 50',
];
$messages['options_objectives'] = [
    'downside_hedging' => 'Gegen Rückgänge absichern', 'income' => 'Erträge erzielen', 'defined_risk_growth' => 'Wachstum mit definiertem Risiko', 'combination' => 'Eine Kombination',
];
$messages['options_monitoring'] = [
    'daily' => 'Täglich', 'weekly' => 'Wöchentlich', 'monthly' => 'Monatlich', 'rarely' => 'Selten',
];
$messages['options_experience'] = [
    'none' => 'Keine', 'basic' => 'Grundlagen', 'intermediate' => 'Fortgeschritten', 'advanced' => 'Sehr erfahren',
];
$messages['option_strategies'] = [
    'protective_put' => 'Protective Put', 'covered_call' => 'Covered Call', 'collar' => 'Collar', 'uncovered' => 'Ungedeckt',
];
$messages['profile_warnings'] = [
    'return_expectation_exceeds_risk_capacity' => 'Deine Zielrendite liegt höher, als deine ermittelte Risikotragfähigkeit hergibt.',
    'willingness_exceeds_capacity' => 'Du bist bereit, mehr Risiko zu tragen, als deine Finanzen derzeit erlauben.',
    'capacity_exceeds_willingness' => 'Du könntest mehr Risiko tragen, als dir angenehm ist.',
];
$messages['recommendation_statuses'] = [
    'generating' => 'Wird entworfen', 'needs_clarification' => 'Braucht Details', 'awaiting_vault_seal' => 'Wartet auf Entsperren', 'ready' => 'Fertig', 'failed' => 'Fehlgeschlagen',
];
$messages['constraints_labels'] = [
    'minimum_liquid_allocation' => 'Mindestens liquide', 'maximum_single_asset_allocation' => 'Höchstens in einem Wert', 'maximum_high_risk_allocation' => 'Höchstens hohes Risiko', 'maximum_speculative_allocation' => 'Höchstens spekulativ', 'maximum_options_risk_budget' => 'Höchstes Optionsbudget',
];

/* Advisor redesign. */
$messages['how_it_works'] = 'So funktioniert es';
$messages['home'] = [
    'subtitle' => 'Überblick',
    'in_progress' => 'Analyse läuft',
];
$messages['paywall'] = [
    'subtitle' => 'Pro-Funktion',
    'unlock' => 'Advisor mit Pro freischalten',
    'see_inside' => 'Sieh, was drinsteckt',
    'included' => 'In CashPilot Pro enthalten · ohne Aufpreis',
    'members_only' => 'Nur für Pro-Mitglieder',
    'profile_saved' => 'Dein Anlegerprofil und jede Antwort dahinter sind noch da. Mit einer Verlängerung machst du genau dort weiter, wo du aufgehört hast.',
    'preview_equity' => 'Heimischer Aktienindexfonds',
    'preview_gold' => 'Gold, 18 Karat',
    'preview_global' => 'Globaler Aktien-ETF',
    'preview_currency' => 'US-Dollar',
];
$messages['assessment'] = array_merge($messages['assessment'], [
    'saved_short' => 'Nach jedem Abschnitt verschlüsselt gespeichert',
    'answered' => '{count} von {total} beantwortet',
]);
$messages['section_intros'] = [
    'financial_foundation' => 'Diese Antworten legen fest, wie viel du verlieren kannst, bevor irgendetwas entworfen wird. Nichts davon ist ein Urteil — es ist der Boden, auf dem jedes Portfolio darunter stehen muss.',
    'investment_goals' => 'Wofür das Geld da ist und wann du es brauchst. Horizont und Zweck grenzen die Form eines Portfolios stärker ein als jede Vorliebe.',
    'risk_and_loss' => 'Antworte so, wie du dich tatsächlich verhalten würdest, nicht so, wie du es gern hättest. Diese Antworten setzen die Verlustgrenze, die jedes Portfolio darunter einhalten muss.',
    'investment_experience' => 'Was du bereits gehalten hast und wie gut du die Mechanik kennst. Erfahrung erweitert das, was die KI vorschlagen darf.',
    'behavior' => 'Wie du gehandelt hast, wenn eine Position stark ausgeschlagen ist. Verhalten unter Druck ist der Unterschied zwischen einem Plan auf Papier und einem, den du durchhältst.',
    'return_expectations' => 'Was du Erfolg nennen würdest. Liegt diese Zahl höher, als deine Tragfähigkeit hergibt, sagt CashPilot das — statt danach zu greifen.',
    'portfolio_preferences' => 'Die Werte, die die KI verwenden darf, die Märkte, die du erreichst, und die Obergrenzen, die gelten sollen. Nichts außerhalb dieser Liste kommt in einen Plan.',
    'options_and_hedging' => 'Ob Optionen überhaupt in deinen Plan gehören und welche Strategien du fahren kannst. Antworte mit Nein, und dieser Abschnitt kostet dich nichts.',
];
$messages['portfolio'] = array_merge($messages['portfolio'], [
    'inclusion' => 'Verwendung',
]);
$messages['profile'] = array_merge($messages['profile'], [
    'subtitle' => 'Anlegerprofil',
    'persona' => 'Anlegertyp',
    'sealed' => 'Versiegelt',
    'scoring' => 'Bewertung',
    'assets_approved' => 'Freigegebene Werte',
    'derived_scores' => 'Abgeleitete Werte',
    'deterministic' => 'Deterministisch · keine KI',
    'hard_caps' => 'Harte Grenzen',
    'allowed' => 'Erlaubt',
    'category' => 'Kategorie',
    'risk' => 'Risiko',
    'outlook' => 'Einschätzung',
]);
$messages['recommendation'] = array_merge($messages['recommendation'], [
    'primary_tab' => 'Primär',
    'safer_tab' => 'Sicherer',
    'higher_tab' => 'Mehr Risiko',
    'allocated' => 'Zugeteilt',
    'stage_done' => 'Fertig',
    'stage_working' => 'Läuft',
    'designing' => 'Wird entworfen',
    'open_portfolio' => 'Portfolio öffnen',
    'open_result' => 'Sieh, was der Advisor gefunden hat',
    'role' => 'Rolle',
    'total' => 'Gesamt',
    'liquid' => 'Binnen einer Woche liquide',
    'capital' => 'Gesamtkapital',
    'hash' => 'Hash',
    'base' => 'Basis',
    'consultation' => 'Beratung',
    'back_to_plan' => 'Der Plan',
    'tag_you' => 'Du',
    'tag_advisor' => 'Advisor',
    'drawdown_cap' => 'Verlustgrenze',
    'clarification_eyebrow' => 'Noch eine Runde',
    'allow_diversifier' => 'Einen fehlenden Diversifizierer zulassen?',
]);
$messages['options_willingness'] = [
    'no' => 'Nein', 'yes' => 'Ja', 'not_sure' => 'Nicht sicher',
];

return $messages;
