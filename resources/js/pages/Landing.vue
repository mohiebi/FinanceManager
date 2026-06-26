<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    ChartPie,
    Check,
    DollarSign,
    LayoutDashboard,
    Lock,
    Menu,
    ReceiptText,
    Shield,
    TrendingUp,
    Wallet,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { dashboard, home, login } from '@/routes';
import logoGreen from '../../img/Logo-green.svg';

const page = usePage();
const isLoggedIn = !!page.props.auth?.user;

const mobileMenuOpen = ref(false);
</script>

<template>
    <Head title="Your money, on Autopilot">
        <meta
            name="description"
            content="Track every transaction, manage your investment portfolio, and get smart financial reports — all in one place. Start free."
        />
    </Head>

    <div class="min-h-screen overflow-x-hidden bg-[#0a0a0a] text-white">

        <!-- ══════════════════════════════════════════════════════
             FLOATING NAVBAR
        ═════════════════════════════════════════════════════════ -->
        <nav
            aria-label="Main navigation"
            class="fixed top-4 right-4 left-4 z-50 mx-auto max-w-6xl"
        >
            <div
                class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/[0.06] px-5 py-3 shadow-[0_8px_32px_rgba(0,0,0,0.4)] backdrop-blur-xl"
            >
                <!-- Logo -->
                <Link :href="home()" class="flex items-center gap-2.5 cursor-pointer">
                    <img :src="logoGreen" alt="CashPilot logo" class="h-12 w-10" />
                    <span class="text-[17px] font-bold text-white">CashPilot</span>
                </Link>

                <!-- Desktop nav links -->
                <div class="hidden items-center gap-7 lg:flex">
                    <a href="#features" class="cursor-pointer text-sm text-white/60 transition-colors duration-200 hover:text-white">Features</a>
                    <a href="#how-it-works" class="cursor-pointer text-sm text-white/60 transition-colors duration-200 hover:text-white">How it works</a>
                    <a href="#pricing" class="cursor-pointer text-sm text-white/60 transition-colors duration-200 hover:text-white">Pricing</a>
                </div>

                <!-- Desktop CTAs -->
                <div class="hidden items-center gap-3 lg:flex">
                    <template v-if="isLoggedIn">
                        <Link
                            :href="dashboard()"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#02CD86] px-4 py-2 text-sm font-semibold text-[#0a0a0a] transition-all duration-200 hover:bg-[#00b876]"
                        >
                            <LayoutDashboard class="size-4" />
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="cursor-pointer rounded-xl px-4 py-2 text-sm font-medium text-white/70 transition-colors duration-200 hover:text-white"
                        >
                            Sign in
                        </Link>
                        <Link
                            :href="login()"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#02CD86] px-4 py-2 text-sm font-semibold text-[#0a0a0a] transition-all duration-200 hover:bg-[#00b876]"
                        >
                            Get started free
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </template>
                </div>

                <!-- Mobile menu toggle -->
                <button
                    type="button"
                    class="flex cursor-pointer items-center justify-center rounded-lg p-2 text-white/70 transition-colors hover:text-white lg:hidden"
                    :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
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
                class="mt-2 rounded-2xl border border-white/10 bg-white/[0.06] px-5 py-4 shadow-[0_8px_32px_rgba(0,0,0,0.4)] backdrop-blur-xl lg:hidden"
            >
                <div class="flex flex-col gap-3">
                    <a href="#features" class="cursor-pointer rounded-lg px-3 py-2 text-sm text-white/70 transition-colors hover:bg-white/5 hover:text-white" @click="mobileMenuOpen = false">Features</a>
                    <a href="#how-it-works" class="cursor-pointer rounded-lg px-3 py-2 text-sm text-white/70 transition-colors hover:bg-white/5 hover:text-white" @click="mobileMenuOpen = false">How it works</a>
                    <a href="#pricing" class="cursor-pointer rounded-lg px-3 py-2 text-sm text-white/70 transition-colors hover:bg-white/5 hover:text-white" @click="mobileMenuOpen = false">Pricing</a>
                    <div class="mt-1 flex flex-col gap-2 border-t border-white/10 pt-3">
                        <Link
                            :href="login()"
                            class="cursor-pointer rounded-xl px-4 py-2.5 text-center text-sm font-medium text-white/70 ring-1 ring-white/20 transition-colors hover:text-white"
                        >
                            Sign in
                        </Link>
                        <Link
                            :href="login()"
                            class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-[#02CD86] px-4 py-2.5 text-sm font-semibold text-[#0a0a0a] transition-all hover:bg-[#00b876]"
                        >
                            Get started free
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </nav>


        <!-- ══════════════════════════════════════════════════════
             HERO SECTION
        ═════════════════════════════════════════════════════════ -->
        <section
            class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 pb-20 pt-32"
            aria-label="Hero"
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
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#02CD86]/30 bg-[#02CD86]/10 px-4 py-1.5 text-sm font-medium text-[#02CD86]">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#02CD86] opacity-75 motion-reduce:animate-none" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-[#02CD86]" />
                    </span>
                    Personal Finance Platform — Free to start
                </div>

                <!-- Headline -->
                <h1 class="mb-6 text-5xl font-bold leading-tight tracking-tight text-white sm:text-6xl lg:text-7xl">
                    Your money,
                    <br />
                    <span class="bg-gradient-to-r from-[#02CD86] to-[#00f5a3] bg-clip-text text-transparent">
                        on Autopilot.
                    </span>
                </h1>

                <!-- Sub-headline -->
                <p class="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-white/60 sm:text-xl">
                    Track every transaction, watch your investments grow, and get
                    crystal-clear financial reports — all in one beautifully
                    simple dashboard.
                </p>

                <!-- CTA buttons -->
                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        :href="login()"
                        class="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#02CD86] px-8 py-4 text-base font-bold text-[#0a0a0a] shadow-[0_0_40px_rgba(2,205,134,0.3)] transition-all duration-200 hover:bg-[#00b876] hover:shadow-[0_0_60px_rgba(2,205,134,0.45)]"
                    >
                        Get started — it's free
                        <ArrowRight class="size-5" />
                    </Link>
                    <a
                        href="#features"
                        class="flex cursor-pointer items-center gap-2 rounded-2xl border border-white/20 px-8 py-4 text-base font-medium text-white/80 transition-all duration-200 hover:border-white/40 hover:bg-white/5 hover:text-white"
                    >
                        See all features
                    </a>
                </div>

                <!-- Social proof micro-copy -->
                <p class="mt-6 text-sm text-white/40">
                    No credit card required &nbsp;·&nbsp; Free forever plan available
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
                            <span class="text-xs font-semibold text-white/70">CashPilot</span>
                            <span class="text-white/30">|</span>
                            <span class="text-xs text-white/50">Dashboard</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="rounded-md bg-[#2d2d2d] px-3 py-1 text-[10px] text-white/50">Currency: USD</div>
                            <div class="h-6 w-6 rounded-full bg-[#353535]" />
                        </div>
                    </div>

                    <!-- Mock KPI row -->
                    <div class="mb-4 grid grid-cols-3 gap-3">
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center gap-1.5">
                                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#effffa]">
                                    <TrendingUp class="size-3.5 text-[#02CD86]" />
                                </div>
                                <span class="text-[9px] font-medium uppercase tracking-wider text-white/40">Income</span>
                            </div>
                            <div class="text-sm font-bold text-white">150,000,000</div>
                            <div class="text-[9px] text-white/30">TOMAN</div>
                        </div>
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center gap-1.5">
                                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#f0ecff]">
                                    <ReceiptText class="size-3.5 text-[#6C4EE9]" />
                                </div>
                                <span class="text-[9px] font-medium uppercase tracking-wider text-white/40">Costs</span>
                            </div>
                            <div class="text-sm font-bold text-white">22,500,000</div>
                            <div class="text-[9px] text-white/30">TOMAN</div>
                        </div>
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center gap-1.5">
                                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#effffa]">
                                    <Wallet class="size-3.5 text-[#02CD86]" />
                                </div>
                                <span class="text-[9px] font-medium uppercase tracking-wider text-white/40">Balance</span>
                            </div>
                            <div class="text-sm font-bold text-[#02CD86]">+127,500,000</div>
                            <div class="text-[9px] text-white/30">TOMAN</div>
                        </div>
                    </div>

                    <!-- Mock chart area -->
                    <div class="grid grid-cols-[1fr_2fr] gap-3">
                        <!-- Gauge mock -->
                        <div class="flex flex-col items-center justify-center rounded-[14px] bg-[#1a1a1a] px-3 py-4">
                            <div class="text-[9px] font-medium uppercase tracking-wider text-white/40">Finance Rate</div>
                            <div class="my-2 flex h-14 w-14 items-center justify-center rounded-full border-4 border-[#02CD86] bg-[#0a0a0a]">
                                <span class="text-sm font-bold text-[#02CD86]">87%</span>
                            </div>
                            <div class="text-[8px] text-white/30">Income ÷ total flow</div>
                        </div>
                        <!-- Bar chart mock -->
                        <div class="rounded-[14px] bg-[#1a1a1a] p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-[9px] text-white/40">Monthly overview</span>
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center gap-1 text-[8px] text-white/30"><span class="inline-block h-1.5 w-1.5 rounded-full bg-[#02CD86]" />Income</span>
                                    <span class="flex items-center gap-1 text-[8px] text-white/30"><span class="inline-block h-1.5 w-1.5 rounded-full bg-[#6C4EE9]" />Costs</span>
                                </div>
                            </div>
                            <div class="flex h-14 items-end gap-1.5">
                                <div v-for="(h, i) in [35, 52, 28, 68, 100, 45]" :key="i" class="flex flex-1 flex-col items-center gap-0.5">
                                    <div class="w-full rounded-t-sm bg-[#02CD86]/70" :style="{ height: h + '%' }" />
                                    <div class="w-full rounded-t-sm bg-[#6C4EE9]/50" :style="{ height: (h * 0.18 + 5) + '%' }" />
                                </div>
                            </div>
                            <div class="mt-1 flex justify-between px-0.5 text-[7px] text-white/25">
                                <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Reflection shadow -->
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-10 left-1/2 h-20 w-4/5 -translate-x-1/2 rounded-full bg-[#02CD86]/10 blur-3xl" />
            </div>
        </section>


        <!-- ══════════════════════════════════════════════════════
             STATS STRIP
        ═════════════════════════════════════════════════════════ -->
        <section class="border-y border-white/[0.06] bg-white/[0.02] px-4 py-10" aria-label="Statistics">
            <div class="mx-auto grid max-w-4xl grid-cols-2 gap-8 text-center md:grid-cols-4">
                <div>
                    <div class="text-3xl font-bold text-white">3</div>
                    <div class="mt-1 text-sm text-white/40">Currencies supported</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-white">∞</div>
                    <div class="mt-1 text-sm text-white/40">Transactions tracked</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-[#02CD86]">6</div>
                    <div class="mt-1 text-sm text-white/40">Asset types (investments)</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-white">Free</div>
                    <div class="mt-1 text-sm text-white/40">To get started</div>
                </div>
            </div>
        </section>


        <!-- ══════════════════════════════════════════════════════
             FEATURES BENTO GRID
        ═════════════════════════════════════════════════════════ -->
        <section id="features" class="px-4 py-24" aria-labelledby="features-heading">
            <div class="mx-auto max-w-6xl">
                <!-- Section header -->
                <div class="mb-16 text-center">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.3em] text-[#02CD86]">
                        Everything you need
                    </p>
                    <h2 id="features-heading" class="text-4xl font-bold text-white sm:text-5xl">
                        One dashboard.<br />Total financial clarity.
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg text-white/50">
                        No spreadsheets. No guessing. Just clear, real-time visibility
                        over every toman you earn and spend.
                    </p>
                </div>

                <!-- Bento grid -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

                    <!-- Large card: Transactions -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#02CD86]/30 hover:bg-white/[0.07] md:col-span-2"
                    >
                        <div class="pointer-events-none absolute top-0 right-0 h-40 w-40 rounded-full bg-[#02CD86]/[0.07] blur-2xl" />
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#02CD86]/10">
                            <ReceiptText class="size-6 text-[#02CD86]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">Transaction Tracking</h3>
                        <p class="mb-5 text-white/50 leading-relaxed">
                            Log costs and income in seconds. Categorise, search, filter by
                            date or type — and see your money story at a glance.
                        </p>
                        <ul class="flex flex-col gap-2">
                            <li v-for="feat in ['Costs & incomes in one view', 'Category tags & descriptions', 'Multi-currency support']" :key="feat" class="flex items-center gap-2 text-sm text-white/60">
                                <Check class="size-4 shrink-0 text-[#02CD86]" />
                                {{ feat }}
                            </li>
                        </ul>
                    </div>

                    <!-- Tall card: Investments -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#6C4EE9]/30 hover:bg-white/[0.07] md:row-span-2"
                    >
                        <div class="pointer-events-none absolute bottom-0 left-0 h-40 w-40 rounded-full bg-[#6C4EE9]/[0.08] blur-2xl" />
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#6C4EE9]/10">
                            <TrendingUp class="size-6 text-[#9478FF]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">Investment Portfolio</h3>
                        <p class="mb-5 text-white/50 leading-relaxed">
                            Track gold, silver, USD, EUR, Bahar Azadi coins, and Bitcoin.
                            See your total portfolio value, allocation breakdown, and
                            performance over time.
                        </p>
                        <!-- Asset chips -->
                        <div class="mb-5 flex flex-wrap gap-2">
                            <span v-for="asset in ['🥇 Gold', '🥈 Silver', '💵 USD', '💶 EUR', '🪙 Coin', '₿ BTC']" :key="asset" class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/60">
                                {{ asset }}
                            </span>
                        </div>
                        <ul class="flex flex-col gap-2">
                            <li v-for="feat in ['Donut allocation chart', 'Value timeline chart', 'P&L tracking (Portfolio)']" :key="feat" class="flex items-center gap-2 text-sm text-white/60">
                                <Check class="size-4 shrink-0 text-[#9478FF]" />
                                {{ feat }}
                            </li>
                        </ul>
                    </div>

                    <!-- Card: Reports -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#02CD86]/30 hover:bg-white/[0.07]"
                    >
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#02CD86]/10">
                            <ChartPie class="size-6 text-[#02CD86]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">Smart Reports</h3>
                        <p class="text-white/50 leading-relaxed">
                            Monthly, seasonal, yearly or custom date-range reports.
                            Compare income vs costs and spot patterns instantly.
                        </p>
                    </div>

                    <!-- Card: Dashboard -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-[#F59E0B]/30 hover:bg-white/[0.07]"
                    >
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#F59E0B]/10">
                            <LayoutDashboard class="size-6 text-[#F59E0B]" />
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-white">Live Dashboard</h3>
                        <p class="text-white/50 leading-relaxed">
                            Finance Rate gauge, monthly bar chart, savings rate, and
                            period progress — everything on one screen.
                        </p>
                    </div>

                    <!-- Wide card: Multi-currency + Security -->
                    <div
                        class="group relative overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-7 transition-all duration-300 hover:border-white/20 hover:bg-white/[0.07] md:col-span-2"
                    >
                        <div class="grid gap-8 sm:grid-cols-2">
                            <div>
                                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#3B82F6]/10">
                                    <DollarSign class="size-6 text-[#3B82F6]" />
                                </div>
                                <h3 class="mb-2 text-xl font-bold text-white">Multi-Currency</h3>
                                <p class="text-white/50 leading-relaxed">
                                    Switch between Toman, USD, and EUR on the fly.
                                    Every amount converts automatically for easy comparison.
                                </p>
                            </div>
                            <div>
                                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E94E50]/10">
                                    <Shield class="size-6 text-[#E94E50]" />
                                </div>
                                <h3 class="mb-2 text-xl font-bold text-white">Secure by Default</h3>
                                <p class="text-white/50 leading-relaxed">
                                    Passwordless email auth, two-factor verification, and
                                    Google sign-in. Your data stays yours.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>


        <!-- ══════════════════════════════════════════════════════
             HOW IT WORKS
        ═════════════════════════════════════════════════════════ -->
        <section id="how-it-works" class="px-4 py-24" aria-labelledby="hiw-heading">
            <div class="mx-auto max-w-4xl">
                <div class="mb-16 text-center">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.3em] text-[#6C4EE9]">
                        Simple setup
                    </p>
                    <h2 id="hiw-heading" class="text-4xl font-bold text-white sm:text-5xl">
                        Up and running in minutes
                    </h2>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div
                        v-for="(step, i) in [
                            { icon: Lock, color: '#02CD86', bg: 'bg-[#02CD86]/10', title: 'Create your account', desc: 'Sign up with email in seconds — no credit card, no commitment. Passwordless magic-link login.' },
                            { icon: ReceiptText, color: '#9478FF', bg: 'bg-[#6C4EE9]/10', title: 'Log your transactions', desc: 'Add costs and incomes as they happen. Set categories, dates, and currencies. Takes 5 seconds.' },
                            { icon: BarChart3, color: '#F59E0B', bg: 'bg-[#F59E0B]/10', title: 'Watch insights appear', desc: 'Your dashboard, reports, and investment charts update instantly. See your financial health clearly.' },
                        ]"
                        :key="i"
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
                        <h3 class="mb-2 text-lg font-bold text-white">{{ step.title }}</h3>
                        <p class="text-white/50 text-sm leading-relaxed">{{ step.desc }}</p>

                        <!-- Connector arrow for desktop -->
                        <div
                            v-if="i < 2"
                            aria-hidden="true"
                            class="absolute -right-3 top-1/2 hidden -translate-y-1/2 text-white/20 md:block"
                        >
                            <ArrowRight class="size-5" />
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ══════════════════════════════════════════════════════
             PRICING TEASER
        ═════════════════════════════════════════════════════════ -->
        <section id="pricing" class="px-4 py-24" aria-labelledby="pricing-heading">
            <div class="mx-auto max-w-4xl">
                <div class="mb-12 text-center">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.3em] text-[#02CD86]">
                        Simple pricing
                    </p>
                    <h2 id="pricing-heading" class="text-4xl font-bold text-white sm:text-5xl">
                        Free to start. Always.
                    </h2>
                    <p class="mt-4 text-lg text-white/50">
                        CashPilot is free for personal use. No hidden fees.
                    </p>
                </div>

                <div class="relative overflow-hidden rounded-3xl border border-[#02CD86]/30 bg-[#02CD86]/[0.05] p-8 text-center md:p-12">
                    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-br from-[#02CD86]/[0.08] via-transparent to-[#6C4EE9]/[0.08]" />
                    <div class="relative z-10">
                        <div class="mb-2 text-6xl font-bold text-white">$0</div>
                        <div class="mb-8 text-white/50">per month · forever free</div>

                        <div class="mb-10 grid grid-cols-1 gap-3 text-left sm:grid-cols-2">
                            <div
                                v-for="feat in [
                                    'Unlimited transactions',
                                    'Dashboard & reports',
                                    'Investment tracking',
                                    'Multi-currency support',
                                    'Secure passwordless auth',
                                    'Export & data access',
                                ]"
                                :key="feat"
                                class="flex items-center gap-3 text-sm text-white/70"
                            >
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#02CD86]/20">
                                    <Check class="size-3 text-[#02CD86]" />
                                </span>
                                {{ feat }}
                            </div>
                        </div>

                        <Link
                            :href="login()"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-[#02CD86] px-10 py-4 text-base font-bold text-[#0a0a0a] shadow-[0_0_40px_rgba(2,205,134,0.3)] transition-all duration-200 hover:bg-[#00b876] hover:shadow-[0_0_60px_rgba(2,205,134,0.45)]"
                        >
                            Start for free today
                            <ArrowRight class="size-5" />
                        </Link>
                        <p class="mt-3 text-sm text-white/30">No credit card required</p>
                    </div>
                </div>
            </div>
        </section>


        <!-- ══════════════════════════════════════════════════════
             FINAL CTA
        ═════════════════════════════════════════════════════════ -->
        <section class="relative overflow-hidden px-4 py-24" aria-label="Call to action">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-b from-[#6C4EE9]/[0.06] via-transparent to-[#02CD86]/[0.06]" />

            <div class="relative z-10 mx-auto max-w-3xl text-center">
                <h2 class="mb-5 text-4xl font-bold text-white sm:text-5xl">
                    Take control of your
                    <span class="bg-gradient-to-r from-[#02CD86] to-[#9478FF] bg-clip-text text-transparent">
                        financial future
                    </span>
                </h2>
                <p class="mb-10 text-lg text-white/50">
                    Join CashPilot and start making sense of your money — for free,
                    today, in minutes.
                </p>
                <Link
                    :href="login()"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-[#02CD86] px-10 py-4 text-base font-bold text-[#0a0a0a] shadow-[0_0_40px_rgba(2,205,134,0.25)] transition-all duration-200 hover:bg-[#00b876] hover:shadow-[0_0_60px_rgba(2,205,134,0.4)]"
                >
                    Get started free
                    <ArrowRight class="size-5" />
                </Link>
            </div>
        </section>


        <!-- ══════════════════════════════════════════════════════
             FOOTER
        ═════════════════════════════════════════════════════════ -->
        <footer class="border-t border-white/[0.06] px-4 py-10" role="contentinfo">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-6 sm:flex-row">
                <div class="flex items-center gap-2.5">
                    <img :src="logoGreen" alt="CashPilot" class="h-7 w-6" />
                    <span class="text-sm font-bold text-white/70">CashPilot</span>
                </div>
                <p class="text-sm text-white/30">
                    © {{ new Date().getFullYear() }} CashPilot. Built with ♥ for personal finance.
                </p>
                <div class="flex items-center gap-6">
                    <Link :href="login()" class="cursor-pointer text-sm text-white/40 transition-colors hover:text-white/70">
                        Sign in
                    </Link>
                    <Link :href="login()" class="cursor-pointer text-sm text-white/40 transition-colors hover:text-white/70">
                        Get started
                    </Link>
                </div>
            </div>
        </footer>

    </div>
</template>
