<?php

return [
    'title' => 'Flugplan',
    'description' => 'Entscheide vorab, wohin dein Geld geht. Ein Anteil deines Einkommens, ein fester Betrag, oder eine Zeile, die den Rest aufnimmt.',

    'saved' => 'Flugplan gespeichert.',
    'deleted' => 'Flugplan gelöscht.',

    'empty_title' => 'Reiche deinen ersten Flugplan ein',
    'empty_body' => 'Lege fest, welchen Anteil deines Einkommens jede Kategorie bekommt — CashPilot rechnet die Beträge aus, sobald das Geld eingeht.',
    'empty_action' => 'Plan erstellen',

    'edit' => 'Plan bearbeiten',
    'create' => 'Plan erstellen',
    'save' => 'Plan speichern',
    'cancel' => 'Abbrechen',

    // Rendered by vue-i18n, so placeholders are {braced} rather than :colon-prefixed.
    'delete' => [
        'action' => 'Plan löschen',
        'title' => 'Diesen Flugplan löschen?',
        'description' => 'Der Plan und seine Zeilen werden entfernt. Deine Transaktionen bleiben unberührt.',
    ],

    'plan_title' => 'Name des Plans',
    'plan_title_placeholder' => 'Monatsplan',

    'income_basis' => [
        'label' => 'Prozente berechnen auf',
        'actual' => 'Bisher erhaltenes Einkommen',
        'actual_hint' => 'Ziele wachsen, sobald Geld eingeht. Ehrlich bei schwankendem Einkommen, steht aber am Monatsanfang auf null.',
        'expected' => 'Ein erwartetes Einkommen',
        'expected_hint' => 'Ziele stehen ab dem ersten Tag fest, und die Abweichung zeigt sich, sobald das echte Einkommen eingeht.',
    ],
    'expected_income' => 'Erwartetes Einkommen',

    'period' => 'Dieser Monat',
    'days_remaining' => 'Noch {count} Tage',

    'income' => 'Einkommen',
    'allocated' => 'Zugeteilt',
    'unallocated' => 'Nicht zugeteilt',
    'spent' => 'Ausgegeben',
    'spent_inline' => '{amount} ausgegeben',
    'over_allocated' => '{amount} über dem Einkommen zugeteilt',
    'over_allocated_hint' => 'Die festen und prozentualen Zeilen versprechen mehr, als dieses Einkommen deckt.',
    'line_over_title_one' => 'Eine Zeile über dem Limit',
    'line_over_title_many' => '{count} Zeilen über dem Limit',
    'line_over_body' => '{names} hat das Limit bereits überschritten.',
    'edit_limits' => 'Limits bearbeiten',

    'lines' => 'Planzeilen',
    'add_line' => 'Zeile hinzufügen',
    'remove_line' => 'Zeile entfernen',
    'category' => 'Kategorie',
    'rule' => 'Regel',

    'rules' => [
        'percent' => 'Anteil am Einkommen',
        'fixed' => 'Fester Betrag',
        'remainder' => 'Alles Übrige',
    ],

    'target' => 'Ziel',
    'currency' => 'Währung',
    'remaining' => 'Noch {amount}',
    'overspent' => '{amount} darüber',
    'on_target' => 'Im Ziel',
    'no_target' => 'Noch nichts zugeteilt',
    'calculating' => 'Dein Plan wird berechnet',

    // Thrown server-side through __(), so these keep Laravel's :colon syntax —
    // they arrive at the browser already interpolated.
    'errors' => [
        'category_required' => 'Wähle eine Kategorie für diese Zeile.',
        'percent_required' => 'Gib einen Prozentsatz für diese Zeile an.',
        'amount_required' => 'Gib einen Betrag für diese Zeile an.',
        'one_remainder' => 'Ein Plan kann nur eine Zeile "Alles Übrige" haben.',
        'percent_total' => 'Deine prozentualen Zeilen ergeben :total %. Mehr als 100 % ist nicht möglich.',
        'duplicate_category' => 'Jede Kategorie darf nur in einer Zeile vorkommen.',
    ],
];
