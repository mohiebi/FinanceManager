<?php

$messages = require resource_path('lang/en/advisor.php');
$messages['title'] = 'مشاور کش‌پایلوت';
$messages['eyebrow'] = 'هوش مدیریت ثروت خصوصی';
$messages['tagline'] = 'پرتفوی شما باید با زندگی‌تان هماهنگ باشد، نه فقط با بازار.';
$messages['start'] = 'شروع ارزیابی';
$messages['view_profile'] = 'مشاهده پروفایل ریسک';

$messages['resume_at'] = 'ادامه — بخش {current} از {total}';
$messages['question_count'] = '۲۲ پرسش، سپس سبد شما';
$messages['duration'] = 'حدود ۱۵ دقیقه';

$messages['assessment'] = array_merge($messages['assessment'], [
    'title' => 'ارزیابی سرمایه‌گذار',
    'step' => 'بخش {current} از {total}',
    'saved' => 'پاسخ‌های شما رمزگذاری و پس از هر بخش ذخیره می‌شود.',
    'continue' => 'ذخیره و ادامه',
    'back' => 'بازگشت',
    'finish' => 'ساخت پروفایل من',
    'loading' => 'در حال بارگذاری پاسخ‌های شما…',
    'vault_notice' => 'در حال باز کردن پاسخ‌های شما — رمزگشایی اینجا انجام می‌شود، هرگز روی سرورهای ما.',
    'needs_answer' => 'هنوز پاسخ داده نشده',
    'missing_one' => '۱ پرسش هنوز پاسخ نیاز دارد.',
    'missing_many' => '{count} پرسش هنوز پاسخ نیاز دارند.',
    'missing_country' => 'کشوری که از آن سرمایه‌گذاری می‌کنید را وارد کنید.',
    'missing_markets' => 'دست‌کم یک بازار در دسترس خود را وارد کنید.',
    'missing_assets' => 'دست‌کم یک دارایی برای استفادهٔ مشاور انتخاب کنید.',
    'missing_asset_name' => 'یکی از دارایی‌های شما هنوز نام ندارد.',
    'missing_asset_ticker' => '{name} به نماد یا شناسه نیاز دارد.',
    'save_failed' => 'ذخیره نشد. لطفاً دوباره تلاش کنید.',
]);

$messages['questions'] = array_merge($messages['questions'], [
    'liquidity_amount' => 'چه مقدار',
    'liquidity_amount_placeholder' => 'یک مقدار انتخاب کنید',
    'liquidity_speed' => 'با چه سرعتی',
    'liquidity_speed_placeholder' => 'یک بازهٔ زمانی انتخاب کنید',
    'q15_max_drawdown_hint' => 'فرض کنید به این پول فوراً نیاز ندارید و افت در کل بازار رخ داده است، نه در اثر تقلب یا شکست یک دارایی خاص.',
]);

$messages['portfolio'] = array_merge($messages['portfolio'], [
    'details' => 'جزئیات',
    'details_hint' => 'این‌ها با مقادیر پیش‌فرض منطقی شروع می‌شوند. فقط چیزی را تغییر دهید که دربارهٔ دارایی می‌دانید.',
]);

$messages['profile'] = array_merge($messages['profile'], [
    'ai_scope' => 'هوش مصنوعی دقیقاً همین پروفایل را می‌بیند و هیچ چیز دیگری از شما. کش‌پایلوت پاسخ کامل آن را پیش از نمایش به شما بررسی می‌کند.',
    'options_willingness' => 'تمایل',
    'options_capability_level' => 'توانایی',
    'options_knowledge' => 'دانش',
    'options_risk_budget' => 'بودجهٔ ریسک',
]);

$messages['recommendation'] = array_merge($messages['recommendation'], [
    'generating' => 'در حال طراحی سبد شما',
    'starting' => 'در حال شروع…',
    'stage_reading' => 'در حال خواندن پروفایل شما',
    'stage_designing' => 'در حال طراحی سبد شما',
    'stage_checking' => 'در حال بررسی با محدودیت‌های شما',
    'stage_sealing' => 'در حال رمزگذاری در مرورگر شما',
    'leave_safe' => 'می‌توانید این صفحه را ببندید. سبد شما هنگام بازگشت اینجا خواهد بود.',
    'locked' => 'برای خواندن این پیشنهاد، گاوصندوق خود را باز کنید.',
    'validated_badge' => 'بررسی‌شده توسط کش‌پایلوت',
    'integrity_failed' => 'پیشنهاد رمزگشایی‌شده با آنچه ساخته شده بود یکی نبود، بنابراین نمایش داده نمی‌شود.',
    'chat_encrypted' => 'این گفت‌وگو در مرورگر شما رمزگذاری شده است.',
    'ask_empty' => 'هر پرسشی دربارهٔ این طرح دارید بپرسید.',
    'suggest_fit' => 'چرا این تخصیص برای من مناسب است؟',
    'suggest_risk' => 'بزرگ‌ترین ریسک اینجا چیست؟',
    'suggest_start' => 'از کجا شروع کنم؟',
    'send_failed' => 'ارسال نشد.',
    'retry' => 'تلاش دوباره',
    'send_hint' => 'اینتر برای ارسال، شیفت+اینتر برای خط جدید.',
    'failed' => 'مشاور نتوانست این طرح را کامل کند. پاسخ‌های شما ذخیره شده‌اند — دوباره تلاش کنید.',
]);

$messages['validation'] = array_merge($messages['validation'], [
    'pending_payload_expired' => 'این پیشنهاد بیش از حد منتظر باز شدن ماند و ناچار کنار گذاشته شد. ساخت پیشنهاد تازه چند دقیقه طول می‌کشد.',
]);

$messages['personas'] = [
    'balanced_investor' => 'سرمایه‌گذار متعادل',
    'strategic_growth_investor' => 'سرمایه‌گذار رشد راهبردی',
    'opportunistic_investor' => 'سرمایه‌گذار فرصت‌جو',
    'aggressive_growth_investor' => 'سرمایه‌گذار رشد تهاجمی',
];
$messages['risk_bands'] = [
    'very_conservative' => 'بسیار محافظه‌کارانه', 'conservative' => 'محافظه‌کارانه', 'balanced' => 'متعادل', 'growth' => 'رشدی', 'aggressive' => 'تهاجمی',
    'defensive' => 'تدافعی', 'moderate' => 'میانه', 'speculative' => 'سفته‌بازانه', 'unknown' => 'طبقه‌بندی‌نشده',
];
$messages['categories'] = [
    'stock' => 'سهام', 'etf' => 'صندوق شاخصی', 'bond' => 'اوراق قرضه', 'currency' => 'ارز', 'metal' => 'فلز گران‌بها', 'crypto' => 'رمزارز', 'commodity' => 'کالا', 'real_estate' => 'املاک', 'private_asset' => 'دارایی خصوصی', 'other' => 'سایر',
];
$messages['liquidities'] = [
    'same_day' => 'همان روز', 'within_week' => 'ظرف یک هفته', 'within_month' => 'ظرف یک ماه', 'illiquid' => 'دشوار برای فروش',
];
$messages['perspectives'] = [
    'bearish' => 'نزولی', 'neutral' => 'خنثی', 'bullish' => 'صعودی',
];
$messages['convictions'] = [
    'low' => 'اطمینان کم', 'medium' => 'اطمینان متوسط', 'high' => 'اطمینان زیاد',
];
$messages['holding_periods'] = [
    'under_1_year' => 'کمتر از یک سال', '1_3_years' => '۱ تا ۳ سال', '3_5_years' => '۳ تا ۵ سال', '5_10_years' => '۵ تا ۱۰ سال', '10_plus' => 'بیش از ۱۰ سال',
];
$messages['inclusions'] = [
    'allowed' => 'هوش مصنوعی می‌تواند استفاده کند', 'required' => 'حتماً باید باشد',
];
$messages['underlyings'] = [
    'stocks' => 'سهام', 'etfs' => 'صندوق‌های شاخصی', 'indices' => 'شاخص‌ها', 'commodities' => 'کالاها', 'currencies' => 'ارزها', 'crypto' => 'رمزارز',
];
$messages['options_experience_years'] = [
    'none' => 'هیچ', 'under_1' => 'کمتر از یک سال', '1_3' => '۱ تا ۳ سال', '3_plus' => 'بیش از ۳ سال',
];
$messages['options_trade_counts'] = [
    'none' => 'هیچ', '1_10' => '۱ تا ۱۰', '11_50' => '۱۱ تا ۵۰', '50_plus' => 'بیش از ۵۰',
];
$messages['options_objectives'] = [
    'downside_hedging' => 'پوشش ریسک افت', 'income' => 'کسب درآمد', 'defined_risk_growth' => 'رشد با ریسک مشخص', 'combination' => 'ترکیبی',
];
$messages['options_monitoring'] = [
    'daily' => 'روزانه', 'weekly' => 'هفتگی', 'monthly' => 'ماهانه', 'rarely' => 'به‌ندرت',
];
$messages['options_experience'] = [
    'none' => 'هیچ', 'basic' => 'مقدماتی', 'intermediate' => 'متوسط', 'advanced' => 'پیشرفته',
];
$messages['option_strategies'] = [
    'protective_put' => 'اختیار فروش حمایتی', 'covered_call' => 'اختیار خرید پوششی', 'collar' => 'کولار', 'uncovered' => 'بدون پوشش',
];
$messages['profile_warnings'] = [
    'return_expectation_exceeds_risk_capacity' => 'بازده هدف شما بیش از آن است که ظرفیت ریسک ارزیابی‌شده‌تان پشتیبانی کند.',
    'willingness_exceeds_capacity' => 'شما مایل به پذیرش ریسکی بیش از آن هستید که وضعیت مالی‌تان اکنون اجازه می‌دهد.',
    'capacity_exceeds_willingness' => 'می‌توانستید ریسک بیشتری بپذیرید، اما با آن راحت نیستید.',
];
$messages['recommendation_statuses'] = [
    'generating' => 'در حال طراحی', 'needs_clarification' => 'نیازمند جزئیات', 'awaiting_vault_seal' => 'در انتظار باز شدن', 'ready' => 'آماده', 'failed' => 'ناموفق',
];
$messages['constraints_labels'] = [
    'minimum_liquid_allocation' => 'حداقل نقدشوندگی', 'maximum_single_asset_allocation' => 'حداکثر در یک دارایی', 'maximum_high_risk_allocation' => 'حداکثر ریسک بالا', 'maximum_speculative_allocation' => 'حداکثر سفته‌بازانه', 'maximum_options_risk_budget' => 'حداکثر بودجهٔ اختیار معامله',
];

return $messages;
