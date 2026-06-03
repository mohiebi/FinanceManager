<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#2d2d2d] text-black"
    >
        <!-- ═══════════════════════════════════════════════════════
             HERO BANNER
        ════════════════════════════════════════════════════════ -->
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-white p-5 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <!-- Welcome copy -->
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.3em] text-[#989898] uppercase"
                    >
                        Finance command center
                    </p>
                    <h1
                        class="mt-1 text-[26px] font-bold leading-tight text-[#2d2d2d] sm:text-[30px]"
                    >
                        Your money, on Autopilot.
                    </h1>
                    <p class="mt-0.5 text-sm text-[#989898]">
                        Welcome back,
                        <span class="font-semibold text-[#2d2d2d]">{{
                            user?.name ?? 'there'
                        }}</span>
                    </p>
                </div>

                <!-- Currency selector -->
                <div class="flex flex-col gap-1.5 sm:min-w-[160px]">
                    <Label
                        for="display_currency"
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                    >
                        Display currency
                    </Label>
                    <Select v-model="selectedCurrency">
                        <SelectTrigger
                            id="display_currency"
                            class="h-9 w-full rounded-md !border-[#e6e6e6] !bg-[#f4f4f4] !text-[#2d2d2d] shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="c in props.currencies"
                                :key="c.value"
                                :value="c.value"
                            >
                                {{ c.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════════════
             MAIN CONTENT GRID
        ════════════════════════════════════════════════════════ -->
        <div
            class="grid gap-[18px] px-[18px] py-[18px] xl:grid-cols-[1fr_284px]"
        >
            <!-- ── LEFT COLUMN ──────────────────────────────────── -->
            <div class="flex flex-col gap-[18px]">
                <!-- KPI Cards -->
                <div class="grid gap-[18px] sm:grid-cols-3">
                    <!-- Income -->
                    <article
                        class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#effffa]"
                            >
                                <TrendingUp
                                    class="size-[18px] text-[#02CD86]"
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                Income
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] font-bold leading-none text-[#2d2d2d]"
                        >
                            {{ formatAmount(props.summary.income) }}
                            <span
                                class="text-xs font-normal text-[#989898]"
                                >{{
                                    props.selectedCurrency.toUpperCase()
                                }}</span
                            >
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{ props.transactions.incomes.length }}
                            transaction{{
                                props.transactions.incomes.length !== 1
                                    ? 's'
                                    : ''
                            }}
                        </p>
                    </article>

                    <!-- Costs -->
                    <article
                        class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#f0ecff]"
                            >
                                <TrendingDown
                                    class="size-[18px] text-[#6C4EE9]"
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                Costs
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] font-bold leading-none text-[#2d2d2d]"
                        >
                            {{ formatAmount(props.summary.cost) }}
                            <span
                                class="text-xs font-normal text-[#989898]"
                                >{{
                                    props.selectedCurrency.toUpperCase()
                                }}</span
                            >
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{ props.transactions.costs.length }}
                            transaction{{
                                props.transactions.costs.length !== 1
                                    ? 's'
                                    : ''
                            }}
                        </p>
                    </article>

                    <!-- Balance -->
                    <article
                        class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm"
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
                                Balance
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] font-bold leading-none"
                            :class="
                                balance >= 0
                                    ? 'text-[#02CD86]'
                                    : 'text-[#E94E50]'
                            "
                        >
                            {{ balance >= 0 ? '+' : '−'
                            }}{{ formatAmount(Math.abs(balance)) }}
                            <span
                                class="text-xs font-normal text-[#989898]"
                                >{{
                                    props.selectedCurrency.toUpperCase()
                                }}</span
                            >
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{ balance >= 0 ? 'In the positive' : 'Overspent' }}
                        </p>
                    </article>
                </div>

                <!-- Finance Rate Gauge + Monthly Chart -->
                <div
                    class="grid gap-[18px] md:grid-cols-[216px_1fr]"
                >
                    <!-- Gauge card -->
                    <div
                        class="flex flex-col items-center justify-center rounded-[22px] bg-white p-5 shadow-sm"
                    >
                        <p
                            class="mb-1 text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            Finance Rate
                        </p>
                        <GaugeChart :value="financeRate" />
                        <p class="mt-1 text-center text-xs text-[#989898]">
                            Income ÷ total flow
                        </p>
                    </div>

                    <!-- Monthly bar chart card -->
                    <div
                        class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm"
                    >
                        <div
                            class="mb-3 flex items-center justify-between"
                        >
                            <h2
                                class="text-[17px] font-normal text-[#2d2d2d]"
                            >
                                Monthly overview
                            </h2>
                            <div
                                class="flex items-center gap-4 text-xs text-[#989898]"
                            >
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#02CD86]"
                                    />
                                    Income
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#6C4EE9]"
                                    />
                                    Costs
                                </span>
                            </div>
                        </div>
                        <BarChart
                            :income-data="monthlyData.map((m) => m.income)"
                            :cost-data="monthlyData.map((m) => m.cost)"
                            :categories="monthlyData.map((m) => m.label)"
                            :height="220"
                        />
                    </div>
                </div>
            </div>

            <!-- ── RIGHT SIDEBAR ─────────────────────────────────── -->
            <div class="flex flex-col gap-[18px]">
                <!-- Period card -->
                <div
                    class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm"
                >
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                    >
                        Current Period
                    </p>
                    <p
                        class="mt-2 text-[26px] font-bold leading-none text-[#2d2d2d]"
                    >
                        {{ period.month }}
                    </p>
                    <p class="mt-1 text-xs text-[#989898]">
                        {{ period.year }} &middot; Day
                        {{ period.dayOfMonth }} of {{ period.daysInMonth }}
                    </p>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-[#f0ecff]"
                    >
                        <div
                            class="h-full rounded-full bg-[#6C4EE9] transition-all duration-700"
                            :style="{ width: period.progress + '%' }"
                        />
                    </div>
                    <p class="mt-1.5 text-right text-xs text-[#989898]">
                        {{ period.progress }}% of month elapsed
                    </p>
                </div>

                <!-- Cost Optimize / Savings Rate -->
                <div
                    class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm"
                >
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                    >
                        Cost Optimize
                    </p>
                    <p
                        class="mt-2 text-[26px] font-bold leading-none"
                        :class="
                            costOptimize >= 0
                                ? 'text-[#02CD86]'
                                : 'text-[#E94E50]'
                        "
                    >
                        {{ costOptimize >= 0 ? '+' : ''
                        }}{{ costOptimize }}%
                    </p>
                    <p class="mt-1 text-xs text-[#989898]">Savings rate</p>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-[#f4f4f4]"
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
                                    Math.min(
                                        100,
                                        Math.abs(costOptimize),
                                    ) + '%',
                            }"
                        />
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="flex flex-col gap-3">
                    <button
                        type="button"
                        class="flex h-[60px] w-full items-center justify-between rounded-[16px] bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-4 text-white shadow-[0_10px_24px_rgba(108,78,233,0.28)] transition hover:brightness-105 active:scale-[0.98]"
                        @click="openCreateForm('cost')"
                    >
                        <span class="text-[19px] font-bold">Add Cost</span>
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 shadow-inner"
                        >
                            <Plus class="size-5" />
                        </span>
                    </button>

                    <button
                        type="button"
                        class="flex h-[60px] w-full items-center justify-between rounded-[16px] bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-4 text-white shadow-[0_10px_24px_rgba(2,205,134,0.25)] transition hover:brightness-105 active:scale-[0.98]"
                        @click="openCreateForm('income')"
                    >
                        <span class="text-[19px] font-bold">Add Income</span>
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 shadow-inner"
                        >
                            <Plus class="size-5" />
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════
             RECENT TRANSACTIONS
        ════════════════════════════════════════════════════════ -->
        <div
            class="grid gap-[18px] px-[18px] pb-[38px] xl:grid-cols-2"
        >
            <!-- Recent Costs -->
            <section
                class="overflow-hidden rounded-[22px] bg-white shadow-sm"
            >
                <div
                    class="flex items-center justify-between px-5 py-[24px]"
                >
                    <h2
                        class="text-[20px] leading-none font-normal text-black"
                    >
                        Recently Costs
                    </h2>
                    <Link
                        :href="transactionsIndex()"
                        class="text-xs text-[#6C4EE9] hover:underline"
                    >
                        See all →
                    </Link>
                </div>

                <div class="overflow-x-auto px-3 pb-4">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr>
                                <th
                                    class="rounded-l-2xl bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                >
                                    Subject
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                >
                                    Category
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                >
                                    Amount
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in recentCosts"
                                :key="t.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none text-black sm:px-5"
                                >
                                    <button
                                        type="button"
                                        class="transition hover:text-[#6C4EE9]"
                                        @click="openEditForm(t)"
                                    >
                                        {{ t.title }}
                                    </button>
                                    <div
                                        v-if="t.description"
                                        class="mt-0.5 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ t.description }}
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center sm:px-5"
                                >
                                    <span
                                        class="inline-flex min-w-[90px] justify-center rounded-md bg-[#d9d9d9] px-3 py-1.5 text-[15px] font-normal text-black"
                                    >
                                        {{
                                            t.category?.name ??
                                            'Uncategorized'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none font-semibold text-[#6C4EE9] sm:px-5"
                                >
                                    {{ formatAmount(t.display_amount) }}
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center sm:px-5"
                                >
                                    <div
                                        class="flex items-center justify-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#f0ecff]"
                                            @click="openEditForm(t)"
                                        >
                                            <Pencil
                                                class="size-3.5 text-[#6C4EE9]"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                                            @click="deleteTransaction(t)"
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
                                    No costs yet — add one when money leaves!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Recent Incomes -->
            <section
                class="overflow-hidden rounded-[22px] bg-white shadow-sm"
            >
                <div
                    class="flex items-center justify-between px-5 py-[24px]"
                >
                    <h2
                        class="text-[20px] leading-none font-normal text-black"
                    >
                        Recently Incomes
                    </h2>
                    <Link
                        :href="transactionsIndex()"
                        class="text-xs text-[#02CD86] hover:underline"
                    >
                        See all →
                    </Link>
                </div>

                <div class="overflow-x-auto px-3 pb-4">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr>
                                <th
                                    class="rounded-l-2xl bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                >
                                    Subject
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                >
                                    Category
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                >
                                    Amount
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#f0ecff] px-3 py-3.5 text-center text-sm font-normal text-black sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in recentIncomes"
                                :key="t.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none text-black sm:px-5"
                                >
                                    <button
                                        type="button"
                                        class="transition hover:text-[#02CD86]"
                                        @click="openEditForm(t)"
                                    >
                                        {{ t.title }}
                                    </button>
                                    <div
                                        v-if="t.description"
                                        class="mt-0.5 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ t.description }}
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center sm:px-5"
                                >
                                    <span
                                        class="inline-flex min-w-[90px] justify-center rounded-md bg-[#d9d9d9] px-3 py-1.5 text-[15px] font-normal text-black"
                                    >
                                        {{
                                            t.category?.name ??
                                            'Uncategorized'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none font-semibold text-[#02CD86] sm:px-5"
                                >
                                    {{ formatAmount(t.display_amount) }}
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center sm:px-5"
                                >
                                    <div
                                        class="flex items-center justify-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#f0ecff]"
                                            @click="openEditForm(t)"
                                        >
                                            <Pencil
                                                class="size-3.5 text-[#6C4EE9]"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                                            @click="deleteTransaction(t)"
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
                                    No incomes yet — add salary or freelance
                                    wins here!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ═══════════════════════════════════════════════════════
             ADD / EDIT DIALOG  (unchanged logic)
        ════════════════════════════════════════════════════════ -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent
                class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[25px] border-0 bg-white p-0 text-[#2d2d2d] shadow-2xl sm:min-h-[654px] sm:max-w-[618px]"
                :show-close-button="false"
            >
                <form
                    class="px-6 pt-16 pb-12 sm:px-[100px] sm:pt-[83px]"
                    @submit.prevent="submitTransaction"
                >
                    <DialogHeader class="mb-7 space-y-2 text-left">
                        <DialogTitle
                            class="text-[20px] leading-normal font-medium text-[#2d2d2d]"
                        >
                            {{ dialogTitle }}
                        </DialogTitle>
                        <DialogDescription
                            class="max-w-[418px] text-[16px] leading-[18px] font-light text-[#2d2d2d]"
                        >
                            The same form handles both tables. The transaction
                            type follows the table action you selected.
                        </DialogDescription>
                    </DialogHeader>

                    <input type="hidden" name="type" :value="form.type" />

                    <div class="space-y-5">
                        <!-- Subject + Category -->
                        <div class="grid gap-2">
                            <div
                                class="grid gap-2 sm:grid-cols-[276px_134px]"
                            >
                                <Label
                                    class="finance-dialog-label"
                                    for="title"
                                >
                                    Subject
                                </Label>
                                <Label
                                    class="finance-dialog-label"
                                    for="category"
                                >
                                    Category
                                </Label>
                            </div>
                            <div
                                class="grid gap-2 sm:grid-cols-[276px_134px]"
                            >
                                <div>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        :class="fieldControlClass"
                                        required
                                        placeholder="Hamburger, Fresh Restaurant"
                                    />
                                    <InputError
                                        :message="form.errors.title"
                                    />
                                </div>
                                <div>
                                    <select
                                        id="category"
                                        v-model="form.category_id"
                                        required
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <option value="" disabled>
                                            Select
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
                        </div>

                        <!-- Date -->
                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="occurred_at"
                            >
                                Date
                            </Label>
                            <input
                                id="occurred_at"
                                type="hidden"
                                :value="form.occurred_at"
                            />
                            <div
                                class="grid gap-2 sm:grid-cols-[134px_134px_134px]"
                            >
                                <Select
                                    v-model="selectedDateMonth"
                                    required
                                >
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <SelectValue placeholder="Month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="month in months"
                                            :key="month.value"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select v-model="selectedDateDay" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <SelectValue placeholder="Day" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="day in transactionDays"
                                            :key="day"
                                            :value="day"
                                        >
                                            {{ Number(day) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select v-model="selectedDateYear" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <SelectValue placeholder="Year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="year in transactionYears"
                                            :key="year"
                                            :value="year"
                                        >
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <InputError :message="form.errors.occurred_at" />
                        </div>

                        <!-- Amount + Currency -->
                        <div class="grid gap-2">
                            <div
                                class="grid gap-2 sm:grid-cols-[276px_134px]"
                            >
                                <Label
                                    class="finance-dialog-label"
                                    for="amount"
                                >
                                    Amount
                                </Label>
                                <Label
                                    class="finance-dialog-label"
                                    for="currency"
                                >
                                    Currency
                                </Label>
                            </div>
                            <div
                                class="grid gap-2 sm:grid-cols-[276px_134px]"
                            >
                                <div>
                                    <Input
                                        id="amount"
                                        v-model="form.amount"
                                        :class="fieldControlClass"
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="000.000.000"
                                    />
                                    <InputError
                                        :message="form.errors.amount"
                                    />
                                </div>
                                <div>
                                    <select
                                        id="currency"
                                        v-model="form.currency"
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <option
                                            v-for="c in props.currencies"
                                            :key="c.value"
                                            :value="c.value"
                                        >
                                            {{ c.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="form.errors.currency"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="description"
                            >
                                Description
                            </Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="1"
                                class="finance-dialog-field min-h-9 resize-none"
                                :class="fieldControlClass"
                                placeholder="Optional note"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <div class="mt-7 flex justify-end gap-2">
                        <Button
                            type="button"
                            class="h-9 w-[99px] rounded-[8px] bg-[#effffa] px-[10px] py-[3px] text-[20px] font-normal text-[#2d2d2d] shadow-none hover:bg-[#e1fff5]"
                            @click="isDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="h-9 w-[135px] rounded-[8px] bg-[#2d2d2d] px-[10px] py-[3px] text-[20px] font-normal text-white shadow-none hover:bg-[#1f1f1f]"
                            :disabled="
                                form.processing ||
                                selectedCategories.length === 0
                            "
                        >
                            <Spinner v-if="form.processing" />
                            Confirm
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import GaugeChart from '@/components/charts/GaugeChart.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';

// ─── Types ────────────────────────────────────────────────────────────────────

type TransactionType = 'cost' | 'income';
type Currency        = 'toman' | 'usd' | 'eur';

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

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
    transactions: { costs: Transaction[]; incomes: Transaction[] };
    categories:   Record<TransactionType, Category[]>;
    currencies:   CurrencyOption[];
    selectedCurrency: Currency;
    summary:      { cost: string; income: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

// ─── Auth user ────────────────────────────────────────────────────────────────

const page = usePage();
const user = computed(
    () => (page.props.auth as { user?: { name: string } } | undefined)?.user,
);

// ─── Currency selector ────────────────────────────────────────────────────────

const selectedCurrency = ref<Currency>(props.selectedCurrency);

watch(
    () => props.selectedCurrency,
    (v) => { selectedCurrency.value = v; },
);

watch(selectedCurrency, (v) => {
    if (v === props.selectedCurrency) return;
    router.get(
        dashboard.url(),
        { currency: v },
        { preserveScroll: true, preserveState: true, replace: true },
    );
});

// ─── Numeric helpers ──────────────────────────────────────────────────────────

function parseNum(val: string | number): number {
    return parseFloat(String(val).replace(/,/g, '')) || 0;
}

const incomeNum = computed(() => parseNum(props.summary.income));
const costNum   = computed(() => parseNum(props.summary.cost));
const balance   = computed(() => incomeNum.value - costNum.value);

// ─── Finance Rate gauge (income share of total flow) ─────────────────────────

const financeRate = computed(() => {
    const total = incomeNum.value + costNum.value;
    if (total <= 0) return 0;
    return Math.round((incomeNum.value / total) * 100);
});

// ─── Savings rate (Cost Optimize) ─────────────────────────────────────────────

const costOptimize = computed(() => {
    if (incomeNum.value <= 0) return 0;
    return Math.round(
        ((incomeNum.value - costNum.value) / incomeNum.value) * 100,
    );
});

// ─── Current period ───────────────────────────────────────────────────────────

const period = computed(() => {
    const now          = new Date();
    const month        = now.toLocaleDateString('en-US', { month: 'long' });
    const year         = now.getFullYear();
    const dayOfMonth   = now.getDate();
    const daysInMonth  = new Date(year, now.getMonth() + 1, 0).getDate();
    const progress     = Math.round((dayOfMonth / daysInMonth) * 100);
    return { month, year, dayOfMonth, daysInMonth, progress };
});

// ─── Monthly bar chart data (last 6 months) ───────────────────────────────────

const monthlyData = computed(() => {
    const now    = new Date();
    const result = Array.from({ length: 6 }, (_, i) => {
        const d   = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1);
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        const label = d.toLocaleDateString('en-US', { month: 'short' });
        return { key, label, income: 0, cost: 0 };
    });

    for (const t of props.transactions.costs) {
        const m = result.find((r) => r.key === t.occurred_at.slice(0, 7));
        if (m) m.cost += parseNum(t.display_amount);
    }
    for (const t of props.transactions.incomes) {
        const m = result.find((r) => r.key === t.occurred_at.slice(0, 7));
        if (m) m.income += parseNum(t.display_amount);
    }

    return result;
});

// ─── Recent rows (5 each) ─────────────────────────────────────────────────────

const recentCosts   = computed(() => props.transactions.costs.slice(0, 5));
const recentIncomes = computed(() => props.transactions.incomes.slice(0, 5));

// ─── Format amount ────────────────────────────────────────────────────────────

function formatAmount(amount: string | number): string {
    const n = Number(String(amount).replace(/,/g, ''));
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: n % 1 === 0 ? 0 : 2,
    }).format(n);
}

// ─── Dialog / form ────────────────────────────────────────────────────────────

const isDialogOpen          = ref(false);
const editingTransactionId  = ref<number | null>(null);
const selectedDateYear      = ref('');
const selectedDateMonth     = ref('');
const selectedDateDay       = ref('');

const today = () => new Date().toISOString().slice(0, 10);

const transactionYears = computed(() => {
    const y = new Date().getFullYear();
    return Array.from({ length: 17 }, (_, i) => String(y + 1 - i));
});

const months = [
    { value: '01', label: 'January'   },
    { value: '02', label: 'February'  },
    { value: '03', label: 'March'     },
    { value: '04', label: 'April'     },
    { value: '05', label: 'May'       },
    { value: '06', label: 'June'      },
    { value: '07', label: 'July'      },
    { value: '08', label: 'August'    },
    { value: '09', label: 'September' },
    { value: '10', label: 'October'   },
    { value: '11', label: 'November'  },
    { value: '12', label: 'December'  },
];

const transactionDays = computed(() => {
    const y     = Number(selectedDateYear.value  || new Date().getFullYear());
    const m     = Number(selectedDateMonth.value || 1);
    const count = new Date(y, m, 0).getDate();
    return Array.from({ length: count }, (_, i) =>
        String(i + 1).padStart(2, '0'),
    );
});

const form = useForm({
    type:        'cost' as TransactionType,
    category_id: '',
    amount:      '',
    currency:    'toman' as Currency,
    title:       '',
    description: '',
    occurred_at: today(),
});

const selectedCategories = computed(() => props.categories[form.type] ?? []);
const isEditing          = computed(() => editingTransactionId.value !== null);
const dialogTitle        = computed(() =>
    isEditing.value
        ? `Edit ${form.type === 'cost' ? 'cost' : 'income'}`
        : `Add ${form.type === 'cost' ? 'cost' : 'income'}`,
);
const fieldControlClass = computed(() =>
    form.type === 'cost'
        ? 'finance-dialog-field finance-dialog-field-cost focus-visible:ring-[#947BFF]/30'
        : 'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25',
);

function syncDatePicker(date: string): void {
    const [y, m, d] = date.split('-');
    selectedDateYear.value  = y ?? '';
    selectedDateMonth.value = m ?? '';
    selectedDateDay.value   = d ?? '';
}

function updateOccurredAt(): void {
    if (!selectedDateYear.value || !selectedDateMonth.value || !selectedDateDay.value) return;
    form.occurred_at = `${selectedDateYear.value}-${selectedDateMonth.value}-${selectedDateDay.value}`;
}

const resetForm = (type: TransactionType) => {
    const cats = props.categories[type] ?? [];
    form.clearErrors();
    form.reset();
    form.type        = type;
    form.category_id = cats[0]?.id.toString() ?? '';
    form.amount      = '';
    form.currency    = 'toman';
    form.title       = '';
    form.description = '';
    form.occurred_at = today();
    syncDatePicker(form.occurred_at);
};

const openCreateForm = (type: TransactionType) => {
    editingTransactionId.value = null;
    resetForm(type);
    isDialogOpen.value = true;
};

const openEditForm = (t: Transaction) => {
    editingTransactionId.value = t.id;
    form.clearErrors();
    form.type        = t.type;
    form.category_id = t.category_id.toString();
    form.amount      = t.amount;
    form.currency    = t.currency;
    form.title       = t.title;
    form.description = t.description ?? '';
    form.occurred_at = t.occurred_at;
    syncDatePicker(t.occurred_at);
    isDialogOpen.value = true;
};

const submitTransaction = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value         = false;
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

const deleteTransaction = (t: Transaction) => {
    if (!window.confirm(`Delete "${t.title}"?`)) return;
    router.delete(`/transactions/${t.id}`, { preserveScroll: true });
};

watch([selectedDateYear, selectedDateMonth, selectedDateDay], updateOccurredAt);

watch(transactionDays, (days) => {
    if (selectedDateDay.value && !days.includes(selectedDateDay.value)) {
        selectedDateDay.value = days.at(-1) ?? '';
    }
});

syncDatePicker(form.occurred_at);
</script>
