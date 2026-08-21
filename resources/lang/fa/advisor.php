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
    'holdings' => 'دارایی',
    'largest' => 'بزرگ‌ترین دارایی',
    'why' => 'چرا این وزن',
    'coverage' => 'پوشش',
    'risk_budget' => 'بودجهٔ ریسک',
    'asset' => 'دارایی',
    'current' => 'کنونی',
    'target' => 'هدف',
    'move' => 'تغییر',
    'difference' => 'اختلاف',
    'pricing_required' => 'نیازمند قیمت',
    'prices_missing' => 'برای بعضی دارایی‌ها قیمت روز موجود نیست، بنابراین مبالغ دقیق در دسترس نیستند.',
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

/*
 * Advisor redesign. Instrument Serif has no Persian coverage, so the editorial
 * headings fall back to a lighter Vazirmatn — see .advisor-serif in app.css.
 */
$messages['how_it_works'] = 'چطور کار می‌کند';
$messages['home'] = [
    'subtitle' => 'نمای کلی',
    'in_progress' => 'ارزیابی در جریان',
];
$messages['paywall'] = [
    'subtitle' => 'ویژگی حرفه‌ای',
    'unlock' => 'فعال‌سازی مشاور با نسخهٔ حرفه‌ای',
    'see_inside' => 'ببینید چه چیزی درون آن است',
    'included' => 'شامل نسخهٔ حرفه‌ای کش‌پایلوت · بدون هزینهٔ اضافه',
    'members_only' => 'ویژهٔ اعضای حرفه‌ای',
    'profile_saved' => 'پروفایل سرمایه‌گذاری شما و تک‌تک پاسخ‌های پشت آن هنوز اینجاست. با تمدید، دقیقاً از همان‌جا که رها کردید ادامه می‌دهید.',
    'preview_equity' => 'صندوق شاخصی سهام داخلی',
    'preview_gold' => 'طلای ۱۸ عیار',
    'preview_global' => 'صندوق شاخصی سهام جهانی',
    'preview_currency' => 'دلار آمریکا',
];
$messages['assessment'] = array_merge($messages['assessment'], [
    'saved_short' => 'رمزگذاری و ذخیره پس از هر بخش',
    'answered' => '{count} از {total} پاسخ داده شده',
]);
$messages['section_intros'] = [
    'financial_foundation' => 'این پاسخ‌ها پیش از طراحی هر چیزی مشخص می‌کنند چه اندازه توان از دست دادن دارید. هیچ‌کدام قضاوت نیست — کفی است که هر سبدی باید روی آن بایستد.',
    'investment_goals' => 'این پول برای چیست و کِی به آن نیاز دارید. افق و هدف، شکل سبد را بیش از هر سلیقه‌ای محدود می‌کنند.',
    'risk_and_loss' => 'همان‌گونه پاسخ دهید که واقعاً رفتار می‌کنید، نه آن‌گونه که دوست دارید. این پاسخ‌ها سقف زیانی را تعیین می‌کنند که هر سبدی باید به آن پایبند بماند.',
    'investment_experience' => 'چه چیزهایی را پیش‌تر نگه داشته‌اید و سازوکارشان را چقدر می‌شناسید. تجربه، دامنهٔ آنچه هوش مصنوعی مجاز به پیشنهادش است را گسترده‌تر می‌کند.',
    'behavior' => 'وقتی یک موقعیت تند حرکت کرده، چه کرده‌اید. رفتار زیر فشار، تفاوت میان برنامه‌ای روی کاغذ و برنامه‌ای است که می‌توانید نگهش دارید.',
    'return_expectations' => 'چه چیزی را موفقیت می‌نامید. اگر آن عدد بالاتر از ظرفیت شما باشد، کش‌پایلوت همین را می‌گوید و به دنبالش نمی‌رود.',
    'portfolio_preferences' => 'دارایی‌هایی که هوش مصنوعی می‌تواند به کار ببرد، بازارهایی که به آن‌ها دسترسی دارید، و سقف‌هایی که می‌خواهید رعایت شود. هیچ چیزی بیرون از این فهرست وارد یک برنامه نمی‌شود.',
    'options_and_hedging' => 'اینکه آیا اختیار معامله اصلاً جای خود را در برنامهٔ شما دارد و کدام راهبردها را می‌توانید اجرا کنید. «خیر» را انتخاب کنید و این بخش هیچ هزینه‌ای برای شما ندارد.',
];
$messages['portfolio'] = array_merge($messages['portfolio'], [
    'inclusion' => 'نحوهٔ استفاده',
]);
$messages['profile'] = array_merge($messages['profile'], [
    'subtitle' => 'پروفایل سرمایه‌گذار',
    'persona' => 'شخصیت سرمایه‌گذاری',
    'sealed' => 'مهرشده',
    'scoring' => 'امتیازدهی',
    'assets_approved' => 'دارایی‌های تأییدشده',
    'derived_scores' => 'امتیازهای استخراج‌شده',
    'deterministic' => 'قطعی · نه هوش مصنوعی',
    'hard_caps' => 'سقف‌های قطعی',
    'allowed' => 'مجاز',
    'category' => 'دسته',
    'risk' => 'ریسک',
    'outlook' => 'چشم‌انداز',
]);
$messages['recommendation'] = array_merge($messages['recommendation'], [
    'primary_tab' => 'اصلی',
    'safer_tab' => 'محتاطانه‌تر',
    'higher_tab' => 'پرریسک‌تر',
    'allocated' => 'تخصیص‌یافته',
    'stage_done' => 'انجام شد',
    'stage_working' => 'در حال انجام',
    'designing' => 'در حال طراحی',
    'open_portfolio' => 'سبد خود را باز کنید',
    'open_result' => 'ببینید مشاور به چه رسیده است',
    'role' => 'نقش',
    'total' => 'مجموع',
    'liquid' => 'نقدشونده ظرف یک هفته',
    'capital' => 'سرمایهٔ مجموع',
    'hash' => 'هش',
    'base' => 'پایه',
    'consultation' => 'مشاوره',
    'back_to_plan' => 'برنامه',
    'tag_you' => 'شما',
    'tag_advisor' => 'مشاور',
    'drawdown_cap' => 'سقف افت',
    'clarification_eyebrow' => 'یک دور دیگر',
    'allow_diversifier' => 'یک دارایی متنوع‌ساز افزوده شود؟',
]);
$messages['options_willingness'] = [
    'no' => 'خیر', 'yes' => 'بله', 'not_sure' => 'مطمئن نیستم',
];

return $messages;
