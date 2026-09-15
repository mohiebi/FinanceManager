<?php

return [
    'title' => 'طرح پرواز',
    'description' => 'پیش از رسیدن پول، مقصدش را مشخص کنید: سهمی از درآمد، مبلغی ثابت، یا هرچه در پایان باقی می‌ماند.',

    'saved' => 'طرح پرواز ذخیره شد.',
    'deleted' => 'طرح پرواز حذف شد.',

    'empty_title' => 'اولین طرح پروازتان را ثبت کنید',
    'empty_body' => 'سهم هر دسته از درآمد را مشخص کنید؛ کش‌پایلوت با هر دریافتی، مبلغ‌ها را برایتان حساب می‌کند.',
    'empty_action' => 'ساخت طرح',

    'edit' => 'ویرایش طرح',
    'create' => 'ساخت طرح',
    'save' => 'ذخیره طرح',
    'cancel' => 'انصراف',

    // Rendered by vue-i18n, so placeholders are {braced} rather than :colon-prefixed.
    'delete' => [
        'action' => 'حذف طرح',
        'title' => 'این طرح پرواز حذف شود؟',
        'description' => 'طرح و ردیف‌هایش حذف می‌شوند؛ تراکنش‌هایتان دست‌نخورده می‌مانند.',
    ],

    'plan_title' => 'نام طرح',
    'plan_title_placeholder' => 'طرح ماهانه',

    'income_basis' => [
        'label' => 'مبنای درصدها',
        'actual' => 'درآمد دریافت‌شده تا حالا',
        'actual_hint' => 'هدف‌ها با رسیدن پول افزایش می‌یابند. برای درآمد متغیر واقع‌بینانه‌تر است، اما ابتدای ماه صفر نشان می‌دهد.',
        'expected' => 'درآمدی که انتظار دارم',
        'expected_hint' => 'هدف‌ها از روز اول مشخص‌اند و با رسیدن درآمد واقعی، اختلافشان نمایان می‌شود.',
    ],
    'expected_income' => 'درآمد مورد انتظار',

    'period' => 'این ماه',
    'days_remaining' => '{count} روز مانده',

    'income' => 'درآمد',
    'allocated' => 'تخصیص‌یافته',
    'unallocated' => 'تخصیص‌نیافته',
    'spent' => 'خرج‌شده',
    'spent_inline' => '{amount} خرج‌شده',
    'over_allocated' => '{amount} بیشتر از درآمد تخصیص داده‌اید',
    'over_allocated_hint' => 'جمع ردیف‌های ثابت و درصدی از درآمد این دوره بیشتر است.',
    'line_over_title_one' => 'یک ردیف از سقف گذشته',
    'line_over_title_many' => '{count} ردیف از سقف گذشته‌اند',
    'line_over_body' => 'هزینهٔ {names} از سقف تعیین‌شده بیشتر شده است.',
    'edit_limits' => 'ویرایش سقف‌ها',
    'safe_to_spend_explanation' => 'تا اینجای دوره {actual} از {income} را خرج کرده‌اید و {days} روز باقی مانده است. با این روند، حدود {amount} در روز باقی می‌ماند.',

    'lines' => 'ردیف‌های طرح',
    'lines_hint' => 'هزینهٔ هر دسته در برابر مبلغی که برایش کنار گذاشته‌اید',
    'rollover_badge' => 'انتقال از ماه قبل',
    'add_line' => 'افزودن ردیف',
    'remove_line' => 'حذف ردیف',
    'category' => 'دسته',
    'rule' => 'قاعده',

    'rules' => [
        'percent' => 'سهمی از درآمد',
        'fixed' => 'مبلغ ثابت',
        'remainder' => 'ماندهٔ درآمد',
    ],

    'target' => 'هدف',
    'currency' => 'واحد پول',
    'remaining' => '{amount} مانده',
    'overspent' => '{amount} بیشتر از سقف',
    'on_target' => 'مطابق هدف',
    'no_target' => 'هنوز مبلغی اختصاص نیافته',
    'calculating' => 'در حال محاسبهٔ طرح…',

    // Thrown server-side through __(), so these keep Laravel's :colon syntax —
    // they arrive at the browser already interpolated.
    'errors' => [
        'category_required' => 'برای این ردیف یک دسته انتخاب کنید.',
        'percent_required' => 'درصد این ردیف را وارد کنید.',
        'amount_required' => 'مبلغ این ردیف را وارد کنید.',
        'one_remainder' => 'هر طرح فقط می‌تواند یک ردیف «ماندهٔ درآمد» داشته باشد.',
        'percent_total' => 'جمع ردیف‌های درصدی :total٪ است و نمی‌تواند از ۱۰۰٪ بیشتر باشد.',
        'duplicate_category' => 'هر دسته فقط می‌تواند در یک ردیف باشد.',
    ],
];
