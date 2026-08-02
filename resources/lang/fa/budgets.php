<?php

return [
    'title' => 'طرح پرواز',
    'description' => 'پیش از آن‌که پول برسد تصمیم بگیرید کجا برود. سهمی از درآمدتان، مبلغی ثابت، یا خطی که هرچه ماند را جمع کند.',

    'saved' => 'طرح پرواز ذخیره شد.',
    'deleted' => 'طرح پرواز حذف شد.',

    'empty_title' => 'اولین طرح پروازتان را ثبت کنید',
    'empty_body' => 'بگویید هر دسته چه سهمی از درآمدتان بگیرد؛ کش‌پایلوت مبلغ‌ها را همان‌طور که پول می‌رسد حساب می‌کند.',
    'empty_action' => 'ساختن طرح',

    'edit' => 'ویرایش طرح',
    'create' => 'ساختن طرح',
    'save' => 'ذخیره طرح',
    'cancel' => 'انصراف',

    // Rendered by vue-i18n, so placeholders are {braced} rather than :colon-prefixed.
    'delete' => [
        'action' => 'حذف طرح',
        'title' => 'این طرح پرواز حذف شود؟',
        'description' => 'طرح و خط‌هایش حذف می‌شوند. تراکنش‌هایتان دست‌نخورده می‌مانند.',
    ],

    'plan_title' => 'نام طرح',
    'plan_title_placeholder' => 'طرح ماهانه',

    'income_basis' => [
        'label' => 'مبنای درصدها',
        'actual' => 'درآمد دریافت‌شده تا حالا',
        'actual_hint' => 'هدف‌ها با رسیدن پول بزرگ می‌شوند. وقتی درآمدتان متغیر است صادقانه‌تر است، اما اول ماه صفر نشان می‌دهد.',
        'expected' => 'درآمدی که انتظار دارم',
        'expected_hint' => 'هدف‌ها از روز اول وجود دارند و با رسیدن درآمد واقعی، اختلاف پیدا می‌شود.',
    ],
    'expected_income' => 'درآمد مورد انتظار',

    'period' => 'این ماه',
    'days_remaining' => '{count} روز مانده',

    'income' => 'درآمد',
    'allocated' => 'تخصیص‌یافته',
    'unallocated' => 'تخصیص‌نیافته',
    'spent' => 'خرج‌شده',
    'spent_inline' => '{amount} خرج‌شده',
    'over_allocated' => '{amount} بیش از درآمد تخصیص یافته',
    'over_allocated_hint' => 'خط‌های ثابت و درصدی بیش از آنچه این درآمد پوشش می‌دهد قول داده‌اند.',

    'lines' => 'خط‌های طرح',
    'add_line' => 'افزودن خط',
    'remove_line' => 'حذف خط',
    'category' => 'دسته',
    'rule' => 'قاعده',

    'rules' => [
        'percent' => 'سهمی از درآمد',
        'fixed' => 'مبلغ ثابت',
        'remainder' => 'باقی همه چیز',
    ],

    'target' => 'هدف',
    'currency' => 'واحد پول',
    'remaining' => '{amount} مانده',
    'overspent' => '{amount} بیشتر',
    'on_target' => 'روی هدف',
    'no_target' => 'هنوز چیزی تخصیص نیافته',
    'calculating' => 'در حال حساب کردن طرح شما',

    // Thrown server-side through __(), so these keep Laravel's :colon syntax —
    // they arrive at the browser already interpolated.
    'errors' => [
        'category_required' => 'برای این خط یک دسته انتخاب کنید.',
        'percent_required' => 'برای این خط یک درصد وارد کنید.',
        'amount_required' => 'برای این خط یک مبلغ وارد کنید.',
        'one_remainder' => 'هر طرح فقط می‌تواند یک خط «باقی همه چیز» داشته باشد.',
        'percent_total' => 'جمع خط‌های درصدی شما :total٪ است و نمی‌تواند از ۱۰۰٪ بیشتر شود.',
        'duplicate_category' => 'هر دسته فقط می‌تواند در یک خط بیاید.',
    ],
];
