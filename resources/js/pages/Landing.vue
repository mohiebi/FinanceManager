<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Bell,
    CalendarDays,
    ChartPie,
    Check,
    CheckCheck,
    ChevronDown,
    DollarSign,
    Globe,
    LayoutDashboard,
    Lock,
    Menu,
    ReceiptText,
    RefreshCw,
    Send,
    ShieldCheck,
    TrendingUp,
    Wallet,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { dashboard, home, login } from '@/routes';
import { update as updateLocale } from '@/routes/locale';
import logoGreen from '../../img/Logo-green.svg';

const page = usePage();
const { t, locale } = useI18n();

const isLoggedIn = !!page.props.auth?.user;

const mobileMenuOpen = ref(false);
const langMenuOpen = ref(false);
const openFaq = ref<string | null>(null);

const localeLabels: Record<string, string> = {
    en: 'English',
    fa: 'فارسی',
    de: 'Deutsch',
};

const availableLocales = computed<string[]>(
    () => (page.props.locales as string[] | undefined) ?? ['en', 'fa'],
);

function switchLocale(code: string): void {
    langMenuOpen.value = false;
    mobileMenuOpen.value = false;

    if (code === locale.value) {
        return;
    }

    router.post(updateLocale.url(), { locale: code }, { preserveScroll: true });
}

// The hero preview uses illustrative amounts; digits follow the visitor's locale.
const numberFormatter = computed(
    () => new Intl.NumberFormat(locale.value === 'fa' ? 'fa-IR' : locale.value),
);

const mockAmounts = computed(() => ({
    income: numberFormatter.value.format(150_000_000),
    costs: numberFormatter.value.format(22_500_000),
    balance: `+${numberFormatter.value.format(127_500_000)}`,
    rate: new Intl.NumberFormat(
        locale.value === 'fa' ? 'fa-IR' : locale.value,
        {
            style: 'percent',
        },
    ).format(0.87),
}));

const navLinks = [
    { key: 'features', href: '#features' },
    { key: 'telegram', href: '#telegram' },
    { key: 'how_it_works', href: '#how-it-works' },
    { key: 'faq', href: '#faq' },
] as const;

const trustItems = [
    { key: 'encrypted', icon: ShieldCheck, color: '#02CD86' },
    { key: 'calendars', icon: CalendarDays, color: '#9478FF' },
    { key: 'currencies', icon: RefreshCw, color: '#3B82F6' },
    { key: 'telegram', icon: Send, color: '#F59E0B' },
] as const;

const telegramPoints = [
    { key: 'add', icon: ReceiptText },
    { key: 'bills', icon: Bell },
    { key: 'paid', icon: CheckCheck },
] as const;

const howSteps = [
    { key: 'account', icon: Lock, color: '#02CD86', bg: 'bg-[#02CD86]/10' },
    { key: 'log', icon: ReceiptText, color: '#9478FF', bg: 'bg-[#6C4EE9]/10' },
    {
        key: 'insights',
        icon: BarChart3,
        color: '#F59E0B',
        bg: 'bg-[#F59E0B]/10',
    },
] as const;

const faqKeys = [
    'private',
    'calendar',
    'telegram',
    'free',
    'currencies',
] as const;

function toggleFaq(key: string): void {
    openFaq.value = openFaq.value === key ? null : key;
}
</script>

<template>
    <Head :title="t('landing.meta.title')">
        <meta name="description" :content="t('landing.meta.description')" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="CashPilot" />
        <meta
            property="og:title"
            :content="`CashPilot — ${t('landing.meta.title')}`"
        />
        <meta
            property="og:description"
            :content="t('landing.meta.description')"
        />
        <meta property="og:image" content="/apple-touch-icon.png" />
        <meta name="twitter:card" content="summary" />
        <meta
            name="twitter:title"
            :content="`CashPilot — ${t('landing.meta.title')}`"
        />
        <meta
            name="twitter:description"
            :content="t('landing.meta.description')"
        />
    </Head>

    <div class="min-h-screen overflow-x-hidden bg-[#0a0a0a] text-white">
        <!-- ══════════════════════════════════════════════════════
             FLOATING NAVBAR
        ═════════════════════════════════════════════════════════ -->
        <nav
            :aria-label="t('landing.a11y.main_navigation')"
            class="fixed top-4 right-4 left-4 z-50 mx-auto max-w-6xl"
        >
            <div
                class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/[0.06] px-5 py-3 shadow-[0_8px_32px_rgba(0,0,0,0.4)] backdrop-blur-xl"
            >
                <!-- Logo -->
                <Link
                    :href="home()"
                    class="flex cursor-pointer items-center gap-2.5"
                >
                    <img
                        :src="logoGreen"
                        alt="CashPilot logo"
                        class="h-12 w-10"
                    />
                    <span class="text-[17px] font-bold text-white"
                        >CashPilot</span
                    >
                </Link>

                <!-- Desktop nav links -->
                <div class="hidden items-center gap-7 lg:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.key"
                        :href="link.href"
                        class="cursor-pointer text-sm text-white/60 transition-colors duration-200 hover:text-white"
                    >
                        {{ t(`landing.nav.${link.key}`) }}
                    </a>
                </div>

                <!-- Desktop CTAs -->
                <div class="hidden items-center gap-3 lg:flex">
                    <!-- Language switcher -->
                    <div class="relative">
                        <button
                            type="button"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-sm text-white/60 transition-colors duration-200 hover:text-white"
                            :aria-expanded="langMenuOpen"
                            aria-haspopup="listbox"
                            @click="langMenuOpen = !langMenuOpen"
                        >
                            <Globe class="size-4" />
                            {{ localeLabels[locale] ?? locale }}
                            <ChevronDown
                                class="size-3.5 transition-transform"
                                :class="{ 'rotate-180': langMenuOpen }"
                            />
                        </button>
                        <div
                            v-if="langMenuOpen"
                            class="absolute end-0 top-full z-50 mt-2 min-w-36 rounded-xl border border-white/10 bg-[#161616] p-1 shadow-[0_8px_32px_rgba(0,0,0,0.5)]"
                            role="listbox"
                        >
                            <button
                                v-for="code in availableLocales"
                                :key="code"
                                type="button"
                                role="option"
                                :aria-selected="code === locale"
                                class="flex w-full cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-start text-sm text-white/70 transition-colors hover:bg-white/5 hover:text-white"
                                @click="switchLocale(code)"
                            >
                                {{ localeLabels[code] ?? code }}
                                <Check
                                    v-if="code === locale"
                                    class="size-4 text-[#02CD86]"
                                />
                            </button>
                        </div>
                    </div>

                    <template v-if="isLoggedIn">
                        <Link
                            :href="dashboard()"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#02CD86] px-4 py-2 text-sm font-semibold text-[#0a0a0a] transition-all duration-200 hover:bg-[#00b876]"
                        >
                            <LayoutDashboard class="size-4" />
                            {{ t('landing.nav.dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="cursor-pointer rounded-xl px-4 py-2 text-sm font-medium text-white/70 transition-colors duration-200 hover:text-white"
                        >
                            {{ t('landing.nav.sign_in') }}
                        </Link>
                        <Link
                            :href="login()"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#02CD86] px-4 py-2 text-sm font-semibold text-[#0a0a0a] transition-all duration-200 hover:bg-[#00b876]"
                        >
                            {{ t('landing.nav.get_started') }}
                            <ArrowRight class="size-3.5 rtl:rotate-180" />
                        </Link>
                    </template>
                </div>

                <!-- Mobile menu toggle -->
                <button
                    type="button"
                    class="flex cursor-pointer items-center justify-center rounded-lg p-2 text-white/70 transition-colors hover:text-white lg:hidden"
                    :aria-label="
                        mobileMenuOpen
                            ? t('landing.a11y.close_menu')
                            : t('landing.a11y.open_menu')
                    "
                    :aria-expanded="mobileMenuOpen"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X v-if="mobileMenuOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>

            <!-- Mobile menu dropdown -->
            <div
                v-if="mobileMenuOpen"
                class="mt-2 rounded-2xl border border-white/10 bg-[#141414]/95 px-5 py-4 shadow-[0_8px_32px_rgba(0,0,0,0.4)] backdrop-blur-xl lg:hidden"
            >
                <div class="flex flex-col gap-3">
                    <a
                        v-for="link in navLinks"
                        :key="link.key"
                        :href="link.href"
                        class="cursor-pointer rounded-lg px-3 py-2 text-sm text-white/70 transition-colors hover:bg-white/5 hover:text-white"
                        @click="mobileMenuOpen = false"
                    >
                        {{ t(`landing.nav.${link.key}`) }}
                    </a>

                    <!-- Language options -->
                    <div
                        class="flex items-center gap-2 border-t border-white/10 pt-3"
                    >
                        <Globe class="size-4 text-white/40" />
                        <button
                            v-for="code in availableLocales"
                            :key="code"
                            type="button"
                            class="cursor-pointer rounded-lg px-3 py-1.5 text-sm transition-colors"
                            :class="
                                code === locale
                                    ? 'bg-[#02CD86]/15 text-[#02CD86]'
                                    : 'text-white/60 hover:text-white'
                            "
                            @click="switchLocale(code)"
                        >
                            {{ localeLabels[code] ?? code }}
                        </button>
                    </div>

                    <div
                        class="mt-1 flex flex-col gap-2 border-t border-white/10 pt-3"
                    >
                        <template v-if="isLoggedIn">
                            <Link
                                :href="dashboard()"
                                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-[#02CD86] px-4 py-2.5 text-sm font-semibold text-[#0a0a0a] transition-all hover:bg-[#00b876]"
                            >
                                <LayoutDashboard class="size-4" />
                                {{ t('landing.nav.dashboard') }}
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="cursor-pointer rounded-xl px-4 py-2.5 text-center text-sm font-medium text-white/70 ring-1 ring-white/20 transition-colors hover:text-white"
                            >
                                {{ t('landing.nav.sign_in') }}
                            </Link>
                            <Link
                                :href="login()"
                                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-[#02CD86] px-4 py-2.5 text-sm font-semibold text-[#0a0a0a] transition-all hover:bg-[#00b876]"
                            >
                                {{ t('landing.nav.get_started') }}
                                <ArrowRight class="size-3.5 rtl:rotate-180" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- ══════════════════════════════════════════════════════
             HERO SECTION
        ═════════════════════════════════════════════════════════ -->
        <section
            class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 pt-32 pb-20"
            :aria-label="t('landing.a11y.hero')"
        >
            <!-- Ambient gradient glows -->
            <div
                aria-hidden="true"
                class="pointer-events-none absolute top-0 left-1/4 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/3 rounded-full bg-[#02CD86]/[0.12] blur-[100px]"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute right-1/4 bottom-0 h-[500px] w-[500px] translate-x-1/2 translate-y-1/3 rounded-full bg-[#6C4EE9]/[0.14] blur-[100px]"
            />

            <div class="relative z-10 mx-auto max-w-5xl text-center">
                <!-- Badge -->
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#02CD86]/30 bg-[#02CD86]/10 px-4 py-1.5 text-sm font-medium text-[#02CD86]"
                >
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#02CD86] opacity-75 motion-reduce:animate-none"
                        />
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-[#02CD86]"
                        />
                    </span>
                    {{ t('landing.hero.badge') }}
                </div>

                <!-- Headline -->
                <h1
                    class="mb-6 text-5xl leading-tight font-bold tracking-tight text-white sm:text-6xl lg:text-7xl rtl:tracking-normal"
                >
                    {{ t('landing.hero.title_top') }}
                    <br />
                    <span
                        class="bg-gradient-to-r from-[#02CD86] to-[#00f5a3] bg-clip-text text-transparent"
                    >
                        {{ t('landing.hero.title_highlight') }}
                    </span>
                </h1>

                <!-- Sub-headline -->
                <p
                    class="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-white/60 sm:text-xl"
                >
                    {{ t('landing.hero.subtitle') }}
                </p>

                <!-- CTA buttons -->
                <div
                    class="flex flex-col items-center justify-center gap-4 sm:flex-row"
                >
                    <Link
                        :href="login()"
                        class="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#02CD86] px-8 py-4 text-base font-bold text-[#0a0a0a] shadow-[0_0_40px_rgba(2,205,134,0.3)] transition-all duration-200 hover:bg-[#00b876] hover:shadow-[0_0_60px_rgba(2,205,134,0.45)]"
                    >
                        {{ t('landing.hero.cta_primary') }}
                        <ArrowRight class="size-5 rtl:rotate-180" />
                    </Link>
                    <a
                        href="#features"
                        class="flex cursor-pointer items-center gap-2 rounded-2xl border border-white/20 px-8 py-4 text-base font-medium text-white/80 transition-all duration-200 hover:border-white/40 hover:bg-white/5 hover:text-white"
                    >
                        {{ t('landing.hero.cta_secondary') }}
                    </a>
                </div>

                <!-- Social proof micro-copy -->
                <p class="mt-6 text-sm text-white/40">
                    {{ t('landing.hero.microcopy') }}
                </p>
            </div>

            <!-- Mock dashboard preview -->
            <div class="relative z-10 mt-20 w-full max-w-5xl px-4">
                <div
                    class="rounded-3xl border border-white/10 bg-white/[0.04] p-4 shadow-[0_40px_80px_rgba(0,0,0,0.6)] backdrop-blur-md sm:p-6"
                    aria-hidden="true"
                    role="presentation"
                >
                    <!-- Mock header bar -->
                    <div class="mb-5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <img :src="logoGreen" alt="" class="h-6 w-5" />
                            <span class="text-xs font-semibold text-white/70"
                                >CashPilot</span
                            >
                            <span class="text-white/30">|</span>
                            <span class="text-xs text-white/50">{{
                                t('landing.hero.mock.dashboard')
                            }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-md bg-[#2d2d2d] px-3 py-1 text-[10px] text-white/50"
                            >
                                {{ t('landing.hero.mock.currency') }}
                            </div>
                            <div class="h-6 w-6 rounded-full bg-[#353535]" />
                        </div>
                    </div>

                    <!-- Mock KPI row -->
                    <div class="mb-4 grid grid-cols-3 gap-3">
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center gap-1.5">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#effffa]"
                                >
                                    <TrendingUp
                                        class="size-3.5 text-[#02CD86]"
                                    />
                                </div>
                                <span
                                    class="text-[9px] font-medium tracking-wider text-white/40 uppercase rtl:tracking-normal"
                                    >{{ t('landing.hero.mock.income') }}</span
                                >
                            </div>
                            <div class="text-sm font-bold text-white">
                                {{ mockAmounts.income }}
                            </div>
                            <div class="text-[9px] text-white/30">
                                {{ t('landing.hero.mock.toman') }}
                            </div>
                        </div>
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center gap-1.5">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#f0ecff]"
                                >
                                    <ReceiptText
                                        class="size-3.5 text-[#6C4EE9]"
                                    />
                                </div>
                                <span
                                    class="text-[9px] font-medium tracking-wider text-white/40 uppercase rtl:tracking-normal"
                                    >{{ t('landing.hero.mock.costs') }}</span
                                >
                            </div>
                            <div class="text-sm font-bold text-white">
                                {{ mockAmounts.costs }}
                            </div>
                            <div class="text-[9px] text-white/30">
                                {{ t('landing.hero.mock.toman') }}
                            </div>
                        </div>
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center gap-1.5">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#effffa]"
                                >
                                    <Wallet class="size-3.5 text-[#02CD86]" />
                                </div>
                                <span
                                    class="text-[9px] font-medium tracking-wider text-white/40 uppercase rtl:tracking-normal"
                                    >{{ t('landing.hero.mock.balance') }}</span
                                >
                            </div>
                            <div
                                class="text-sm font-bold text-[#02CD86]"
                                dir="ltr"
                            >
                                {{ mockAmounts.balance }}
                            </div>
                            <div class="text-[9px] text-white/30">
                                {{ t('landing.hero.mock.toman') }}
                            </div>
                        </div>
                    </div>

                    <!-- Mock chart area -->
                    <div class="grid grid-cols-[1fr_2fr] gap-3">
                        <!-- Gauge mock -->
                        <div
                            class="flex flex-col items-center justify-center rounded-[14px] bg-[#1a1a1a] px-3 py-4"
                        >
                            <div
                                class="text-[9px] font-medium tracking-wider text-white/40 uppercase rtl:tracking-normal"
                            >
                                {{ t('landing.hero.mock.finance_rate') }}
                            </div>
                            <div
                                class="my-2 flex h-14 w-14 items-center justify-center rounded-full border-4 border-[#02CD86] bg-[#0a0a0a]"
                            >
                                <span
                                    class="text-sm font-bold text-[#02CD86]"
                                    >{{ mockAmounts.rate }}</span
                                >
                            </div>
                            <div class="text-[8px] text-white/30">
                                {{ t('landing.hero.mock.rate_hint') }}
                            </div>
                        </div>
                        <!-- Bar chart mock -->
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-[9px] text-white/40">{{
                                    t('landing.hero.mock.monthly_overview')
                                }}</span>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex items-center gap-1 text-[8px] text-white/30"
                                        ><span
                                            class="inline-block h-1.5 w-1.5 rounded-full bg-[#02CD86]"
                                        />{{
                                            t('landing.hero.mock.income')
                                        }}</span
                                    >
                                    <span
                                        class="flex items-center gap-1 text-[8px] text-white/30"
                                        ><span
                                            class="inline-block h-1.5 w-1.5 rounded-full bg-[#6C4EE9]"
                                        />{{
                                            t('landing.hero.mock.costs')
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div class="flex h-14 items-end gap-1.5">
                                <div
                                    v-for="(h, i) in [35, 52, 28, 68, 100, 45]"
                                    :key="i"
                                    class="flex flex-1 flex-col items-center gap-0.5"
                                >
                                    <div
                                        class="w-full rounded-t-sm bg-[#02CD86]/70"
                                        :style="{ height: h + '%' }"
                                    />
                                    <div
                                        class="w-full rounded-t-sm bg-[#6C4EE9]/50"
                                        :style="{ height: h * 0.18 + 5 + '%' }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Reflection shadow -->
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -bottom-10 left-1/2 h-20 w-4/5 -translate-x-1/2 rounded-full bg-[#02CD86]/10 blur-3xl"
                />
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════
             TRUST STRIP
        ═════════════════════════════════════════════════════════ -->
        <section
            class="border-y border-white/[0.06] bg-white/[0.02] px-4 py-10"
            :aria-label="t('landing.a11y.trust')"
        >
            <div
                class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    v-for="item in trustItems"
                    :key="item.key"
                    class="flex items-start gap-3"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                        :style="{ backgroundColor: item.color + '1a' }"
                    >
                        <component
                            :is="item.icon"
                            class="size-5"
                            :style="{ color: item.color }"
                        />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-white">
                            {{ t(`landing.trust.${item.key}.title`) }}
                        </div>
                        <div class="mt-0.5 text-sm text-white/40">
                            {{ t(`landing.trust.${item.key}.text`) }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════
             FEATURES BENTO GRID
        ═════════════════════════════════════════════════════════ -->
        <section
            id="features"
            class="scroll-mt-24 px-4 py-24"
            aria-labelledby="features-heading"
        >
            <div class="mx-auto max-w-6xl">
                <!-- Section header -->
                <div class="mb-16 text-center">
                    <p
                        class="mb-3 text-sm font-semibold tracking-[0.3em] text-[#02CD86] uppercase rtl:tracking-normal"
                    >
                        {{ t('landing.features.kicker') }}
                    </p>
                    <h2
                        id="features-heading"
                        class="text-4xl font-bold text-white sm:text-5xl"
                    >
                        {{ t('landing.features.title') }}
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg text-white/50">
                        {{ t('landing.features.subtitle') }}
                    </p>
                </div>

                <!-- Bento grid -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <!-- Large card: Transactions -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#02CD86]/30 hover:bg-white/[0.07] md:col-span-2"
                    >
                        <div
                            class="pointer-events-none absolute top-0 right-0 h-40 w-40 rounded-full bg-[#02CD86]/[0.07] blur-2xl"
                        />
                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#02CD86]/10"
                        >
                            <ReceiptText class="size-6 text-[#02CD86]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">
                            {{ t('landing.features.transactions.title') }}
                        </h3>
                        <p class="mb-5 leading-relaxed text-white/50">
                            {{ t('landing.features.transactions.text') }}
                        </p>
                        <ul class="flex flex-col gap-2">
                            <li
                                v-for="i in 3"
                                :key="i"
                                class="flex items-center gap-2 text-sm text-white/60"
                            >
                                <Check class="size-4 shrink-0 text-[#02CD86]" />
                                {{
                                    t(
                                        `landing.features.transactions.points.${i - 1}`,
                                    )
                                }}
                            </li>
                        </ul>
                    </div>

                    <!-- Tall card: Investments -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#6C4EE9]/30 hover:bg-white/[0.07] md:row-span-2"
                    >
                        <div
                            class="pointer-events-none absolute bottom-0 left-0 h-40 w-40 rounded-full bg-[#6C4EE9]/[0.08] blur-2xl"
                        />
                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#6C4EE9]/10"
                        >
                            <TrendingUp class="size-6 text-[#9478FF]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">
                            {{ t('landing.features.investments.title') }}
                        </h3>
                        <p class="mb-5 leading-relaxed text-white/50">
                            {{ t('landing.features.investments.text') }}
                        </p>
                        <!-- Asset chips -->
                        <div class="mb-5 flex flex-wrap gap-2">
                            <span
                                v-for="asset in ['🥇', '🥈', '💵', '💶', '₿']"
                                :key="asset"
                                class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/60"
                            >
                                {{ asset }}
                            </span>
                        </div>
                        <ul class="flex flex-col gap-2">
                            <li
                                v-for="i in 3"
                                :key="i"
                                class="flex items-center gap-2 text-sm text-white/60"
                            >
                                <Check class="size-4 shrink-0 text-[#9478FF]" />
                                {{
                                    t(
                                        `landing.features.investments.points.${i - 1}`,
                                    )
                                }}
                            </li>
                        </ul>
                    </div>

                    <!-- Card: Bills & Reminders -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#F59E0B]/30 hover:bg-white/[0.07]"
                    >
                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F59E0B]/10"
                        >
                            <Bell class="size-6 text-[#F59E0B]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">
                            {{ t('landing.features.bills.title') }}
                        </h3>
                        <p class="mb-5 leading-relaxed text-white/50">
                            {{ t('landing.features.bills.text') }}
                        </p>
                        <ul class="flex flex-col gap-2">
                            <li
                                v-for="i in 2"
                                :key="i"
                                class="flex items-center gap-2 text-sm text-white/60"
                            >
                                <Check class="size-4 shrink-0 text-[#F59E0B]" />
                                {{
                                    t(`landing.features.bills.points.${i - 1}`)
                                }}
                            </li>
                        </ul>
                    </div>

                    <!-- Card: Reports -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#02CD86]/30 hover:bg-white/[0.07]"
                    >
                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#02CD86]/10"
                        >
                            <ChartPie class="size-6 text-[#02CD86]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">
                            {{ t('landing.features.reports.title') }}
                        </h3>
                        <p class="leading-relaxed text-white/50">
                            {{ t('landing.features.reports.text') }}
                        </p>
                    </div>

                    <!-- Card: Multi-currency -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#3B82F6]/30 hover:bg-white/[0.07]"
                    >
                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#3B82F6]/10"
                        >
                            <DollarSign class="size-6 text-[#3B82F6]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">
                            {{ t('landing.features.multi_currency.title') }}
                        </h3>
                        <p class="leading-relaxed text-white/50">
                            {{ t('landing.features.multi_currency.text') }}
                        </p>
                    </div>

                    <!-- Wide card: Security / encryption -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-[#02CD86]/20 bg-[#02CD86]/[0.04] p-7 transition-all duration-300 hover:border-[#02CD86]/40 hover:bg-[#02CD86]/[0.07] md:col-span-2"
                    >
                        <div
                            class="pointer-events-none absolute top-0 right-0 h-40 w-40 rounded-full bg-[#02CD86]/[0.08] blur-2xl"
                        />
                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#02CD86]/10"
                        >
                            <ShieldCheck class="size-6 text-[#02CD86]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">
                            {{ t('landing.features.security.title') }}
                        </h3>
                        <p class="max-w-2xl leading-relaxed text-white/50">
                            {{ t('landing.features.security.text') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════
             TELEGRAM SPOTLIGHT
        ═════════════════════════════════════════════════════════ -->
        <section
            id="telegram"
            class="scroll-mt-24 border-y border-white/[0.06] bg-white/[0.02] px-4 py-24"
            aria-labelledby="telegram-heading"
        >
            <div
                class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2"
            >
                <!-- Copy side -->
                <div>
                    <p
                        class="mb-3 text-sm font-semibold tracking-[0.3em] text-[#3B82F6] uppercase rtl:tracking-normal"
                    >
                        {{ t('landing.telegram.kicker') }}
                    </p>
                    <h2
                        id="telegram-heading"
                        class="text-4xl font-bold text-white sm:text-5xl"
                    >
                        {{ t('landing.telegram.title') }}
                    </h2>
                    <p class="mt-4 max-w-lg text-lg text-white/50">
                        {{ t('landing.telegram.subtitle') }}
                    </p>

                    <div class="mt-10 flex flex-col gap-6">
                        <div
                            v-for="point in telegramPoints"
                            :key="point.key"
                            class="flex items-start gap-4"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#3B82F6]/10"
                            >
                                <component
                                    :is="point.icon"
                                    class="size-5 text-[#3B82F6]"
                                />
                            </div>
                            <div>
                                <h3 class="font-bold text-white">
                                    {{
                                        t(
                                            `landing.telegram.points.${point.key}.title`,
                                        )
                                    }}
                                </h3>
                                <p class="mt-1 text-sm text-white/50">
                                    {{
                                        t(
                                            `landing.telegram.points.${point.key}.text`,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phone chat mockup -->
                <div
                    class="mx-auto w-full max-w-sm"
                    aria-hidden="true"
                    role="presentation"
                >
                    <div
                        class="rounded-[2.5rem] border border-white/10 bg-[#141414] p-3 shadow-[0_40px_80px_rgba(0,0,0,0.6)]"
                    >
                        <div
                            class="overflow-hidden rounded-[2rem] bg-[#0e1621]"
                        >
                            <!-- Chat header -->
                            <div
                                class="flex items-center gap-3 border-b border-white/5 bg-[#17212b] px-4 py-3"
                            >
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-[#02CD86]"
                                >
                                    <img
                                        :src="logoGreen"
                                        alt=""
                                        class="h-5 w-4"
                                    />
                                </div>
                                <div>
                                    <div
                                        class="text-sm font-semibold text-white"
                                    >
                                        {{
                                            t('landing.telegram.chat.bot_name')
                                        }}
                                    </div>
                                    <div class="text-[11px] text-[#02CD86]">
                                        {{ t('landing.telegram.chat.status') }}
                                    </div>
                                </div>
                                <Send class="ms-auto size-4 text-white/30" />
                            </div>

                            <!-- Messages -->
                            <div class="flex flex-col gap-2.5 px-3 py-4">
                                <!-- Bot: reminder -->
                                <div
                                    class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-[#182533] px-3.5 py-2.5 text-[13px] leading-relaxed text-white/90"
                                >
                                    {{ t('landing.telegram.chat.reminder') }}
                                    <div
                                        class="mt-2 rounded-lg border border-[#02CD86]/40 bg-[#02CD86]/10 px-3 py-1.5 text-center text-[12px] font-semibold text-[#02CD86]"
                                    >
                                        {{
                                            t('landing.telegram.chat.mark_paid')
                                        }}
                                    </div>
                                </div>
                                <!-- Bot: confirmation -->
                                <div
                                    class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-[#182533] px-3.5 py-2.5 text-[13px] leading-relaxed text-white/90"
                                >
                                    {{
                                        t('landing.telegram.chat.confirmation')
                                    }}
                                </div>
                                <!-- User: add expense -->
                                <div
                                    class="max-w-[75%] self-end rounded-2xl rounded-br-md bg-[#2b5278] px-3.5 py-2.5 text-[13px] text-white"
                                >
                                    {{ t('landing.telegram.chat.user_add') }}
                                </div>
                                <!-- Bot: wizard -->
                                <div
                                    class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-[#182533] px-3.5 py-2.5 text-[13px] text-white/90"
                                >
                                    {{ t('landing.telegram.chat.wizard') }}
                                </div>
                                <!-- User: reply -->
                                <div
                                    class="max-w-[75%] self-end rounded-2xl rounded-br-md bg-[#2b5278] px-3.5 py-2.5 text-[13px] text-white"
                                >
                                    {{ t('landing.telegram.chat.user_reply') }}
                                </div>
                                <!-- Bot: done -->
                                <div
                                    class="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-[#182533] px-3.5 py-2.5 text-[13px] text-white/90"
                                >
                                    {{ t('landing.telegram.chat.done') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════
             HOW IT WORKS
        ═════════════════════════════════════════════════════════ -->
        <section
            id="how-it-works"
            class="scroll-mt-24 px-4 py-24"
            aria-labelledby="hiw-heading"
        >
            <div class="mx-auto max-w-4xl">
                <div class="mb-16 text-center">
                    <p
                        class="mb-3 text-sm font-semibold tracking-[0.3em] text-[#6C4EE9] uppercase rtl:tracking-normal"
                    >
                        {{ t('landing.how.kicker') }}
                    </p>
                    <h2
                        id="hiw-heading"
                        class="text-4xl font-bold text-white sm:text-5xl"
                    >
                        {{ t('landing.how.title') }}
                    </h2>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div
                        v-for="(step, i) in howSteps"
                        :key="step.key"
                        class="relative rounded-3xl border border-white/10 bg-white/[0.04] p-7"
                    >
                        <!-- Step number -->
                        <div class="mb-5 flex items-center gap-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl text-sm font-bold"
                                :class="step.bg"
                                :style="{ color: step.color }"
                            >
                                {{ String(i + 1).padStart(2, '0') }}
                            </div>
                            <component
                                :is="step.icon"
                                class="size-5"
                                :style="{ color: step.color }"
                            />
                        </div>
                        <h3 class="mb-2 text-lg font-bold text-white">
                            {{ t(`landing.how.steps.${step.key}.title`) }}
                        </h3>
                        <p class="text-sm leading-relaxed text-white/50">
                            {{ t(`landing.how.steps.${step.key}.text`) }}
                        </p>

                        <!-- Connector arrow for desktop -->
                        <div
                            v-if="i < howSteps.length - 1"
                            aria-hidden="true"
                            class="absolute top-1/2 -right-3 hidden -translate-y-1/2 text-white/20 md:block rtl:right-auto rtl:-left-3"
                        >
                            <ArrowRight class="size-5 rtl:rotate-180" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════
             FAQ
        ═════════════════════════════════════════════════════════ -->
        <section
            id="faq"
            class="scroll-mt-24 px-4 py-24"
            aria-labelledby="faq-heading"
        >
            <div class="mx-auto max-w-3xl">
                <div class="mb-12 text-center">
                    <p
                        class="mb-3 text-sm font-semibold tracking-[0.3em] text-[#02CD86] uppercase rtl:tracking-normal"
                    >
                        {{ t('landing.faq.kicker') }}
                    </p>
                    <h2
                        id="faq-heading"
                        class="text-4xl font-bold text-white sm:text-5xl"
                    >
                        {{ t('landing.faq.title') }}
                    </h2>
                </div>

                <div class="flex flex-col gap-3">
                    <div
                        v-for="key in faqKeys"
                        :key="key"
                        class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] transition-colors"
                        :class="{ 'border-[#02CD86]/30': openFaq === key }"
                    >
                        <button
                            type="button"
                            class="flex w-full cursor-pointer items-center justify-between gap-4 px-6 py-5 text-start"
                            :aria-expanded="openFaq === key"
                            @click="toggleFaq(key)"
                        >
                            <span class="font-semibold text-white">{{
                                t(`landing.faq.items.${key}.q`)
                            }}</span>
                            <ChevronDown
                                class="size-5 shrink-0 text-white/40 transition-transform duration-200"
                                :class="{ 'rotate-180': openFaq === key }"
                            />
                        </button>
                        <div v-if="openFaq === key" class="px-6 pb-5">
                            <p class="leading-relaxed text-white/50">
                                {{ t(`landing.faq.items.${key}.a`) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════
             FINAL CTA (with free-plan banner)
        ═════════════════════════════════════════════════════════ -->
        <section
            class="relative overflow-hidden px-4 py-24"
            :aria-label="t('landing.a11y.call_to_action')"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 bg-gradient-to-b from-[#6C4EE9]/[0.06] via-transparent to-[#02CD86]/[0.06]"
            />

            <div class="relative z-10 mx-auto max-w-3xl text-center">
                <!-- Compact free banner -->
                <div
                    class="mb-10 inline-flex flex-col items-center gap-1 rounded-2xl border border-[#02CD86]/30 bg-[#02CD86]/[0.06] px-8 py-4 sm:flex-row sm:gap-3"
                >
                    <span class="font-bold text-[#02CD86]">{{
                        t('landing.pricing.title')
                    }}</span>
                    <span class="hidden text-white/20 sm:inline">—</span>
                    <span class="text-sm text-white/50">{{
                        t('landing.pricing.text')
                    }}</span>
                </div>

                <h2 class="mb-5 text-4xl font-bold text-white sm:text-5xl">
                    {{ t('landing.cta.title_top') }}
                    <span
                        class="bg-gradient-to-r from-[#02CD86] to-[#9478FF] bg-clip-text text-transparent"
                    >
                        {{ t('landing.cta.title_highlight') }}
                    </span>
                </h2>
                <p class="mb-10 text-lg text-white/50">
                    {{ t('landing.cta.subtitle') }}
                </p>
                <Link
                    :href="login()"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-[#02CD86] px-10 py-4 text-base font-bold text-[#0a0a0a] shadow-[0_0_40px_rgba(2,205,134,0.25)] transition-all duration-200 hover:bg-[#00b876] hover:shadow-[0_0_60px_rgba(2,205,134,0.4)]"
                >
                    {{ t('landing.cta.button') }}
                    <ArrowRight class="size-5 rtl:rotate-180" />
                </Link>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════
             FOOTER
        ═════════════════════════════════════════════════════════ -->
        <footer
            class="border-t border-white/[0.06] px-4 py-10"
            role="contentinfo"
        >
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-6 sm:flex-row"
            >
                <div class="flex items-center gap-2.5">
                    <img :src="logoGreen" alt="CashPilot" class="h-7 w-6" />
                    <span class="text-sm font-bold text-white/70"
                        >CashPilot</span
                    >
                </div>
                <p class="text-sm text-white/30">
                    © {{ new Date().getFullYear() }} CashPilot.
                    {{ t('landing.footer.tagline') }}
                </p>
                <div class="flex items-center gap-6">
                    <Link
                        :href="login()"
                        class="cursor-pointer text-sm text-white/40 transition-colors hover:text-white/70"
                    >
                        {{ t('landing.footer.sign_in') }}
                    </Link>
                    <Link
                        :href="login()"
                        class="cursor-pointer text-sm text-white/40 transition-colors hover:text-white/70"
                    >
                        {{ t('landing.footer.get_started') }}
                    </Link>
                </div>
            </div>
        </footer>
    </div>
</template>
