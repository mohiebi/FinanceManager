<template>
    <Head :title="t('finance.dashboard.title')" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#101010] text-white"
    >
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.3em] text-[#989898] uppercase"
                    >
                        {{ t('finance.dashboard.eyebrow') }}
                    </p>
                    <h1
                        class="mt-1 text-[26px] leading-tight font-bold text-white sm:text-[30px]"
                    >
                        {{ t('finance.dashboard.heading') }}
                    </h1>
                    <p class="mt-0.5 text-sm text-[#989898]">
                        {{ t('finance.dashboard.welcome') }}
                        <span class="font-semibold text-white">{{
                            user?.name ?? t('finance.dashboard.guest')
                        }}</span>
                    </p>
                </div>
            </div>
        </section>
        <div
            class="grid gap-[18px] px-[18px] py-[18px] xl:grid-cols-[1fr_284px]"
        >
            <div class="flex flex-col gap-[18px]">
                <div class="grid gap-[18px] sm:grid-cols-3">
                    <!-- Income KPI -->
                    <article
                        class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <TrendingUp
                                    class="size-[18px] text-[#02CD86]"
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.metrics.income') }}
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] leading-none font-bold text-white"
                        >
                            {{ formatAmount(props.summary.income) }}
                            <span class="text-xs font-normal text-[#989898]">{{
                                props.selectedCurrency.toUpperCase()
                            }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{ props.transactions.incomes.length }}
                            {{ t('finance.reports.transactions') }}
                        </p>
                    </article>
                    <!-- Cost KPI -->
                    <article
                        class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#24212f]"
                            >
                                <TrendingDown
                                    class="size-[18px] text-[#6C4EE9]"
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.metrics.costs') }}
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] leading-none font-bold text-white"
                        >
                            {{ formatAmount(props.summary.cost) }}
                            <span class="text-xs font-normal text-[#989898]">{{
                                props.selectedCurrency.toUpperCase()
                            }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{ props.transactions.costs.length }}
                            {{ t('finance.reports.transactions') }}
                        </p>
                    </article>
                    <!-- Balance KPI -->
                    <article
                        class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                        :class="
                            balance >= 0 ? 'kpi-card-income' : 'kpi-card-cost'
                        "
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    balance >= 0
                                        ? 'bg-[#effffa]'
                                        : 'bg-[#fff0f0]'
                                "
                            >
                                <Wallet
                                    class="size-[18px]"
                                    :class="
                                        balance >= 0
                                            ? 'text-[#02CD86]'
                                            : 'text-[#E94E50]'
                                    "
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.metrics.balance') }}
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] leading-none font-bold"
                            :class="
                                balance >= 0
                                    ? 'text-[#02CD86]'
                                    : 'text-[#E94E50]'
                            "
                        >
                            {{ balance >= 0 ? '+' : '−'
                            }}{{ formatAmount(Math.abs(balance)) }}
                            <span class="text-xs font-normal text-[#989898]">{{
                                props.selectedCurrency.toUpperCase()
                            }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{
                                balance >= 0
                                    ? t('finance.metrics.in_the_positive')
                                    : t('finance.metrics.overspent')
                            }}
                        </p>
                    </article>
                </div>
                <div
                    class="grid items-stretch gap-[18px] md:grid-cols-[1fr_2fr]"
                >
                    <div
                        class="flex min-h-[244px] flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <p
                            class="mb-1 text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('finance.dashboard.finance_rate') }}
                        </p>
                        <GaugeChart :value="financeRate" />
                        <p class="mt-1 text-center text-xs text-[#989898]">
                            {{ t('finance.dashboard.finance_rate_subtitle') }}
                        </p>
                    </div>
                    <div
                        class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-[17px] font-normal text-white">
                                {{ t('finance.dashboard.monthly_overview') }}
                            </h2>
                            <div
                                class="flex items-center gap-4 text-xs text-[#989898]"
                            >
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#02CD86]"
                                    />
                                    {{ t('finance.metrics.income') }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#6C4EE9]"
                                    />
                                    {{ t('finance.metrics.costs') }}
                                </span>
                            </div>
                        </div>
                        <BarChart
                            :income-data="
                                monthlyData.map(
                                    (monthBucket) => monthBucket.income,
                                )
                            "
                            :cost-data="
                                monthlyData.map(
                                    (monthBucket) => monthBucket.cost,
                                )
                            "
                            :categories="
                                monthlyData.map(
                                    (monthBucket) => monthBucket.label,
                                )
                            "
                            :height="220"
                        />
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-[18px]">
                <div
                    class="kpi-card-period overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                    >
                        {{ t('finance.calendar.current_period') }}
                    </p>
                    <p
                        class="mt-2 text-[26px] leading-none font-bold text-white"
                    >
                        {{ period.month }}
                    </p>
                    <p class="mt-1 text-xs text-[#989898]">
                        {{ period.year }} &middot;
                        {{
                            t('finance.calendar.day_of_month', {
                                day: period.dayOfMonth,
                                days: period.daysInMonth,
                            })
                        }}
                    </p>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/10"
                    >
                        <div
                            class="h-full rounded-full bg-[#6C4EE9] transition-all duration-700"
                            :style="{ width: period.progress + '%' }"
                        />
                    </div>
                    <p class="mt-1.5 text-right text-xs text-[#989898]">
                        {{
                            t('finance.calendar.elapsed', {
                                progress: period.progress,
                            })
                        }}
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                    >
                        {{ t('finance.dashboard.cost_optimize') }}
                    </p>
                    <p
                        class="mt-2 text-[26px] leading-none font-bold"
                        :class="
                            costOptimize >= 0
                                ? 'text-[#02CD86]'
                                : 'text-[#E94E50]'
                        "
                    >
                        {{ costOptimize >= 0 ? '+' : '' }}{{ costOptimize }}%
                    </p>
                    <p class="mt-1 text-xs text-[#989898]">
                        {{ t('finance.dashboard.savings_rate') }}
                    </p>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/10"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-700"
                            :class="
                                costOptimize >= 0
                                    ? 'bg-[#02CD86]'
                                    : 'bg-[#E94E50]'
                            "
                            :style="{
                                width:
                                    Math.min(100, Math.abs(costOptimize)) + '%',
                            }"
                        />
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <button
                        type="button"
                        class="flex h-[60px] w-full items-center justify-between rounded-[16px] bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-4 text-white shadow-[0_10px_24px_rgba(108,78,233,0.28)] transition hover:cursor-pointer hover:brightness-105 active:scale-[0.98]"
                        @click="openCreateForm('cost')"
                    >
                        <span class="text-[19px] font-bold">{{
                            t('finance.actions.add_cost')
                        }}</span>
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 shadow-inner"
                        >
                            <Plus class="size-5" />
                        </span>
                    </button>

                    <button
                        type="button"
                        class="flex h-[60px] w-full items-center justify-between rounded-[16px] bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-4 text-white shadow-[0_10px_24px_rgba(2,205,134,0.25)] transition hover:cursor-pointer hover:brightness-105 active:scale-[0.98]"
                        @click="openCreateForm('income')"
                    >
                        <span class="text-[19px] font-bold">{{
                            t('finance.actions.add_income')
                        }}</span>
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 shadow-inner"
                        >
                            <Plus class="size-5" />
                        </span>
                    </button>
                </div>
            </div>
        </div>
        <div class="grid gap-[18px] px-[18px] pb-[38px] xl:grid-cols-2">
            <!-- ── Recently Costs ── -->
            <section
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center justify-between px-5 py-[24px]">
                    <h2 class="text-[20px] leading-none font-normal text-white">
                        {{ t('finance.dashboard.recent_costs') }}
                    </h2>
                    <Link
                        :href="transactionsIndex()"
                        class="text-xs text-[#6C4EE9] hover:underline"
                    >
                        {{ t('finance.actions.see_all') }}
                    </Link>
                </div>

                <div class="overflow-x-auto px-3 pb-4">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr>
                                <th
                                    class="rounded-l-2xl bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in recentCosts"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none text-white sm:px-5"
                                >
                                    <button
                                        type="button"
                                        class="transition hover:text-[#6C4EE9]"
                                        @click="openEditForm(transaction)"
                                    >
                                        {{ transaction.title }}
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-0.5 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[90px] justify-center rounded-md bg-white/10 px-3 py-1.5 text-[15px] font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none font-semibold text-[#6C4EE9] sm:px-5"
                                >
                                    {{
                                        formatAmount(transaction.display_amount)
                                    }}
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <div
                                        class="flex items-center justify-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-white/10"
                                            @click="openEditForm(transaction)"
                                        >
                                            <Pencil
                                                class="size-3.5 text-[#6C4EE9]"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                                            @click="requestDelete(transaction)"
                                        >
                                            <Trash2
                                                class="size-3.5 text-[#E94E50]"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="recentCosts.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-10 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_costs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <!-- ── Recently Incomes ── -->
            <section
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center justify-between px-5 py-[24px]">
                    <h2 class="text-[20px] leading-none font-normal text-white">
                        {{ t('finance.dashboard.recent_incomes') }}
                    </h2>
                    <Link
                        :href="transactionsIndex()"
                        class="text-xs text-[#02CD86] hover:underline"
                    >
                        {{ t('finance.actions.see_all') }}
                    </Link>
                </div>

                <div class="overflow-x-auto px-3 pb-4">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr>
                                <th
                                    class="rounded-l-2xl bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in recentIncomes"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none text-white sm:px-5"
                                >
                                    <button
                                        type="button"
                                        class="transition hover:text-[#02CD86]"
                                        @click="openEditForm(transaction)"
                                    >
                                        {{ transaction.title }}
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-0.5 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[90px] justify-center rounded-md bg-white/10 px-3 py-1.5 text-[15px] font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none font-semibold text-[#02CD86] sm:px-5"
                                >
                                    {{
                                        formatAmount(transaction.display_amount)
                                    }}
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <div
                                        class="flex items-center justify-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-white/10"
                                            @click="openEditForm(transaction)"
                                        >
                                            <Pencil
                                                class="size-3.5 text-[#6C4EE9]"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                                            @click="requestDelete(transaction)"
                                        >
                                            <Trash2
                                                class="size-3.5 text-[#E94E50]"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="recentIncomes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-10 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_incomes') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <Dialog v-model:open="isDialogOpen">
            <DialogContent
                class="max-h-[calc(100dvh-1rem)] overflow-hidden rounded-[20px] border-0 bg-[#1a1a1a] p-0 shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-2rem)] sm:min-h-[654px] sm:max-w-[618px] sm:rounded-[25px]"
                :show-close-button="false"
            >
                <form
                    class="flex max-h-[calc(100dvh-1rem)] flex-col sm:max-h-[calc(100vh-2rem)]"
                    @submit.prevent="submitTransaction"
                >
                    <div
                        class="flex-1 overflow-y-auto px-4 pt-10 pb-5 sm:px-[100px] sm:pt-[83px] sm:pb-6"
                    >
                        <DialogHeader class="mb-7 space-y-2 text-left">
                            <DialogTitle
                                class="text-[20px] leading-normal font-medium text-white"
                            >
                                {{ dialogTitle }}
                            </DialogTitle>
                            <DialogDescription
                                class="max-w-[418px] text-[16px] leading-[18px] font-light text-[#989898]"
                            >
                                {{ t('finance.form.transaction_description') }}
                            </DialogDescription>
                        </DialogHeader>

                        <input type="hidden" name="type" :value="form.type" />

                        <div class="space-y-5">
                            <div class="grid gap-4 sm:grid-cols-[276px_134px]">
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="title"
                                    >
                                        {{ t('finance.fields.subject') }}
                                    </Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        :class="fieldControlClass"
                                        required
                                        :placeholder="
                                            t(
                                                'finance.form.subject_placeholder',
                                            )
                                        "
                                    />
                                    <InputError :message="form.errors.title" />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="category"
                                    >
                                        {{ t('finance.fields.category') }}
                                    </Label>
                                    <select
                                        id="category"
                                        v-model="form.category_id"
                                        required
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <option value="" disabled>
                                            {{ t('common.select') }}
                                        </option>
                                        <option
                                            v-for="category in selectedCategories"
                                            :key="category.id"
                                            :value="category.id.toString()"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="form.errors.category_id"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="occurred_at"
                                >
                                    {{ t('finance.fields.date') }}
                                </Label>
                                <input
                                    id="occurred_at"
                                    type="hidden"
                                    :value="form.occurred_at"
                                />
                                <BirthdatePicker
                                    v-model="form.occurred_at"
                                    name="occurred_at"
                                    :trigger-class="fieldControlClass"
                                    :years-back="16"
                                    :years-forward="1"
                                />
                                <InputError
                                    :message="form.errors.occurred_at"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-[276px_134px]">
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="amount"
                                    >
                                        {{ t('finance.fields.amount') }}
                                    </Label>
                                    <Input
                                        id="amount"
                                        v-model="form.amount"
                                        :class="fieldControlClass"
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="0.00"
                                    />
                                    <InputError :message="form.errors.amount" />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="currency"
                                    >
                                        {{ t('finance.fields.currency') }}
                                    </Label>
                                    <select
                                        id="currency"
                                        v-model="form.currency"
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <option
                                            v-for="currency in props.currencies"
                                            :key="currency.value"
                                            :value="currency.value"
                                        >
                                            {{ currency.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="form.errors.currency"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="description"
                                >
                                    {{ t('finance.fields.description') }}
                                </Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="1"
                                    class="finance-dialog-field min-h-9 resize-none"
                                    :class="fieldControlClass"
                                    :placeholder="
                                        t('finance.form.note_placeholder')
                                    "
                                />
                                <InputError
                                    :message="form.errors.description"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex shrink-0 justify-end gap-2 border-t border-white/10 bg-[#1a1a1a]/95 px-4 py-4 backdrop-blur sm:border-t-0 sm:bg-transparent sm:px-[100px] sm:pt-1 sm:pb-12"
                    >
                        <Button
                            type="button"
                            class="h-11 flex-1 cursor-pointer rounded-[8px] bg-white/5 px-[10px] py-[3px] text-base font-normal text-[#989898] shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white sm:h-9 sm:w-[99px] sm:flex-none sm:text-[20px]"
                            @click="isDialogOpen = false"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            class="h-11 flex-1 rounded-[8px] bg-[#111111] px-[10px] py-[3px] text-base font-normal text-white shadow-none hover:bg-[#1f1f1f] sm:h-9 sm:w-[135px] sm:flex-none sm:text-[20px]"
                            :disabled="
                                form.processing ||
                                selectedCategories.length === 0
                            "
                        >
                            <Spinner v-if="form.processing" />
                            {{ t('common.confirm') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                t('finance.delete.transaction_title', {
                    title: deleteTarget?.title ?? '',
                })
            "
            :description="t('finance.delete.transaction_description')"
            @update:open="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import {
    Pencil,
    Plus,
    Trash2,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import BarChart from '@/components/charts/BarChart.vue';
import GaugeChart from '@/components/charts/GaugeChart.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { monthBucketKeyFromIso, recentMonthBuckets } from '@/lib/date';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';

type TransactionType = 'cost' | 'income';
type Currency = 'toman' | 'usd' | 'eur';

type Category = {
    id: number;
    name: string;
    slug: string;
    type: TransactionType;
    is_default: boolean;
};

type Transaction = {
    id: number;
    type: TransactionType;
    amount: string;
    currency: Currency;
    display_amount: string;
    display_currency: Currency;
    title: string;
    description: string | null;
    occurred_at: string;
    category: Category | null;
    category_id: number;
};

type CurrencyOption = { label: string; value: Currency };
type Period = {
    month: string;
    year: number;
    dayOfMonth: number;
    daysInMonth: number;
    progress: number;
};

const props = defineProps<{
    transactions: { costs: Transaction[]; incomes: Transaction[] };
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    summary: { cost: string; income: string };
    period: Period;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const { locale, t } = useI18n();
const user = computed(
    () => (page.props.auth as { user?: { name: string } } | undefined)?.user,
);
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);
const period = computed(() => props.period);

function parseNum(value: string | number): number {
    return parseFloat(String(value).replace(/,/g, '')) || 0;
}

const incomeNum = computed(() => parseNum(props.summary.income));
const costNum = computed(() => parseNum(props.summary.cost));
const balance = computed(() => incomeNum.value - costNum.value);

const financeRate = computed(() => {
    const total = incomeNum.value + costNum.value;

    if (total <= 0) {
        return 0;
    }

    return Math.round((incomeNum.value / total) * 100);
});

const costOptimize = computed(() => {
    if (incomeNum.value <= 0) {
        return 0;
    }

    return Math.round(
        ((incomeNum.value - costNum.value) / incomeNum.value) * 100,
    );
});

const monthlyData = computed(() => {
    const monthBuckets = recentMonthBuckets(
        6,
        displayCalendar.value,
        locale.value === 'fa' ? 'fa-IR' : 'en-US',
    ).map((bucket) => ({ ...bucket, income: 0, cost: 0 }));

    for (const transaction of props.transactions.costs) {
        const monthBucket = monthBuckets.find(
            (bucket) =>
                bucket.key ===
                monthBucketKeyFromIso(
                    transaction.occurred_at,
                    displayCalendar.value,
                ),
        );

        if (monthBucket) {
            monthBucket.cost += parseNum(transaction.display_amount);
        }
    }

    for (const transaction of props.transactions.incomes) {
        const monthBucket = monthBuckets.find(
            (bucket) =>
                bucket.key ===
                monthBucketKeyFromIso(
                    transaction.occurred_at,
                    displayCalendar.value,
                ),
        );

        if (monthBucket) {
            monthBucket.income += parseNum(transaction.display_amount);
        }
    }

    return monthBuckets;
});

const recentCosts = computed(() => props.transactions.costs.slice(0, 5));
const recentIncomes = computed(() => props.transactions.incomes.slice(0, 5));

function formatAmount(amount: string | number): string {
    const numericAmount = Number(String(amount).replace(/,/g, ''));

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: numericAmount % 1 === 0 ? 0 : 2,
    }).format(numericAmount);
}

const isDialogOpen = ref(false);
const editingTransactionId = ref<number | null>(null);

const today = () => new Date().toISOString().slice(0, 10);

const form = useForm({
    type: 'cost' as TransactionType,
    category_id: '',
    amount: '',
    currency: 'toman' as Currency,
    title: '',
    description: '',
    occurred_at: today(),
});

const selectedCategories = computed(() => props.categories[form.type] ?? []);
const isEditing = computed(() => editingTransactionId.value !== null);
const dialogTitle = computed(() =>
    isEditing.value
        ? t(
              form.type === 'cost'
                  ? 'finance.form.edit_cost'
                  : 'finance.form.edit_income',
          )
        : t(
              form.type === 'cost'
                  ? 'finance.form.add_cost'
                  : 'finance.form.add_income',
          ),
);
const fieldControlClass = computed(() =>
    form.type === 'cost'
        ? 'finance-dialog-field finance-dialog-field-cost focus-visible:ring-[#947BFF]/30'
        : 'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25',
);

function categoryName(transaction: Transaction): string {
    if (transaction.category?.name) {
        return transaction.category.name;
    }

    return (
        (props.categories[transaction.type] ?? []).find(
            (category) => category.id === transaction.category_id,
        )?.name ?? t('finance.categories.uncategorized')
    );
}

const resetForm = (type: TransactionType) => {
    const cats = props.categories[type] ?? [];
    form.clearErrors();
    form.reset();
    form.type = type;
    form.category_id = cats[0]?.id.toString() ?? '';
    form.amount = '';
    form.currency = 'toman';
    form.title = '';
    form.description = '';
    form.occurred_at = today();
};

const openCreateForm = (type: TransactionType) => {
    editingTransactionId.value = null;
    resetForm(type);
    isDialogOpen.value = true;
};

const openEditForm = (transaction: Transaction) => {
    editingTransactionId.value = transaction.id;
    form.clearErrors();
    form.type = transaction.type;
    form.category_id = transaction.category_id.toString();
    form.amount = transaction.amount;
    form.currency = transaction.currency;
    form.title = transaction.title;
    form.description = transaction.description ?? '';
    form.occurred_at = transaction.occurred_at;
    isDialogOpen.value = true;
};

const submitTransaction = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false;
            editingTransactionId.value = null;
            resetForm(form.type);
        },
    };

    if (editingTransactionId.value) {
        form.patch(`/transactions/${editingTransactionId.value}`, opts);

        return;
    }

    form.post('/transactions', opts);
};

const deleteTarget = ref<Transaction | null>(null);

const requestDelete = (transaction: Transaction) => {
    deleteTarget.value = transaction;
};

const confirmDelete = () => {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(`/transactions/${deleteTarget.value.id}`, {
        preserveScroll: true,
    });
    deleteTarget.value = null;
};
</script>
