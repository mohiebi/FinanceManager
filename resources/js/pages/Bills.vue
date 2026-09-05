<template>
    <Head :title="t('finance.bills.title')" />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Summary bar — total due over the next 30 days, whether the
             balance covers it, and the List/Calendar toggle. The mock's own
             screen has no header of its own (the shell already owns the
             page title). ─────────────────────────────────────────────── -->
        <section
            v-if="bills.length > 0"
            class="mx-[18px] mt-5 flex flex-wrap items-center justify-between gap-5 rounded-[16px] bg-[#1a1a1a] px-6 py-5 ring-1 ring-white/10"
        >
            <div>
                <p class="mb-2 text-xs text-[#989898]">
                    {{
                        t('finance.bills.due_soon_title', {
                            days: dueSoonWindowDays,
                        })
                    }}
                    · {{ currencyLabel(props.dueSoonSummary.currency) }}
                </p>
                <p class="text-[30px] leading-none font-semibold text-white">
                    <CompactMoney
                        v-if="dueSoonTotal !== null"
                        :value="dueSoonTotal"
                        :currency="
                            props.dueSoonSummary.currency as CurrencyCode
                        "
                        mask-variant="placeholder"
                    />
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
                <p
                    v-if="spare !== null"
                    class="mt-2.5 flex items-center gap-1.5 text-[12.5px]"
                    :class="spare >= 0 ? 'text-[#02CD86]' : 'text-[#E94E50]'"
                >
                    <CheckCircle2 v-if="spare >= 0" class="size-3.5 shrink-0" />
                    <AlertTriangle v-else class="size-3.5 shrink-0" />
                    <span>{{
                        spare >= 0
                            ? t('finance.bills.balance_covers', {
                                  amount: formatCompactCurrencyNumber(spare),
                              })
                            : t('finance.bills.balance_short', {
                                  amount: formatCompactCurrencyNumber(
                                      Math.abs(spare),
                                  ),
                              })
                    }}</span>
                </p>
            </div>

            <div
                role="tablist"
                class="flex shrink-0 items-center gap-1 rounded-full bg-[#252525] p-1"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="viewMode === 'list'"
                    class="cursor-pointer rounded-full px-4 py-1.5 text-[12.5px] font-medium transition-colors"
                    :class="
                        viewMode === 'list'
                            ? 'bg-[#02CD86] text-[#101010]'
                            : 'text-[#989898] hover:text-white'
                    "
                    @click="viewMode = 'list'"
                >
                    {{ t('finance.bills.list_view') }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="viewMode === 'calendar'"
                    class="cursor-pointer rounded-full px-4 py-1.5 text-[12.5px] font-medium transition-colors"
                    :class="
                        viewMode === 'calendar'
                            ? 'bg-[#02CD86] text-[#101010]'
                            : 'text-[#989898] hover:text-white'
                    "
                    @click="viewMode = 'calendar'"
                >
                    {{ t('finance.bills.calendar_view') }}
                </button>
            </div>
        </section>

        <!-- ── Empty state ────────────────────────────────────────── -->
        <div
            v-if="bills.length === 0"
            class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[16px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
        >
            <span
                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#24212f]"
            >
                <Receipt class="size-8 text-[#6C4EE9]" />
            </span>
            <h2 class="mt-4 text-xl font-semibold text-white">
                {{ t('finance.bills.empty') }}
            </h2>
            <p class="mt-2 max-w-sm text-center text-sm text-[#989898]">
                {{ t('finance.bills.empty_description') }}
            </p>
            <Button
                class="mt-6 h-11 rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010]"
                @click="openCreateDialog()"
            >
                {{ t('finance.bills.add_bill') }}
            </Button>
        </div>

        <!-- ── Scheduled + Telegram reminders ──────────────────────── -->
        <div
            v-else-if="viewMode === 'list'"
            class="grid items-start gap-[18px] px-[18px] py-[18px] xl:grid-cols-2"
        >
            <section
                class="overflow-x-auto rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div
                    class="mb-3 flex min-w-[520px] items-center justify-between gap-3"
                >
                    <p class="text-[14.5px] font-medium text-white">
                        {{ t('finance.bills.scheduled') }}
                    </p>
                    <button
                        type="button"
                        class="cursor-pointer text-[12.5px] text-[#02CD86] hover:underline"
                        @click="openCreateDialog()"
                    >
                        + {{ t('finance.bills.add_bill') }}
                    </button>
                </div>

                <div
                    v-for="bill in bills"
                    :key="bill.id"
                    class="group grid min-w-[520px] grid-cols-[8px_minmax(130px,1fr)_112px_120px_124px] items-center gap-3.5 border-t border-white/[0.06] py-3.5 transition-colors hover:bg-white/[0.02]"
                >
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: statusDotColor(bill) }"
                    />
                    <div class="min-w-0">
                        <p class="truncate text-sm text-white">
                            <Ciphered :value="bill.title" table="bills" />
                        </p>
                        <p
                            class="mt-0.5 truncate text-[11px] text-[#686868]"
                            dir="ltr"
                        >
                            {{ cadenceLabel(bill) }}
                        </p>
                    </div>
                    <span class="text-[12.5px] text-[#989898]">
                        {{
                            bill.recent_paid_occurrence
                                ? displayDate(
                                      bill.recent_paid_occurrence.due_date,
                                  )
                                : bill.next_occurrence
                                  ? displayDate(bill.next_occurrence.due_date)
                                  : t('finance.bills.no_upcoming')
                        }}
                    </span>
                    <span class="justify-self-start">
                        <span
                            class="rounded-full px-2.5 py-1 text-[11.5px] font-medium whitespace-nowrap"
                            :style="{
                                backgroundColor: `${statusDotColor(bill)}1A`,
                                color: statusDotColor(bill),
                            }"
                        >
                            {{ statusLabel(bill) }}
                        </span>
                    </span>
                    <div class="flex items-center justify-end gap-2">
                        <span
                            class="text-[14.5px] text-white tabular-nums"
                            :class="maskClass"
                            dir="ltr"
                        >
                            <CipheredMoney
                                :amount="bill.amount"
                                :display-amount="bill.display_amount"
                                :currency="bill.currency as CurrencyCode"
                                :display-currency="
                                    bill.display_currency as CurrencyCode
                                "
                                :rates="props.rates"
                                show-currency
                                table="bills"
                            />
                        </span>
                        <div
                            class="flex shrink-0 items-center gap-1 opacity-100 transition md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                        >
                            <button
                                v-if="
                                    bill.next_occurrence &&
                                    !bill.recent_paid_occurrence
                                "
                                type="button"
                                :disabled="payingId === bill.next_occurrence.id"
                                :aria-label="t('finance.bills.mark_paid')"
                                class="cursor-pointer rounded-md p-1 text-[#02CD86] hover:bg-[#02CD86]/10 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="void markPaid(bill)"
                            >
                                <Check class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                :aria-label="t('common.edit')"
                                class="cursor-pointer rounded-md p-1 text-[#6C4EE9] hover:bg-[#6C4EE9]/10"
                                @click="void openEditDialog(bill)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                :aria-label="t('common.delete')"
                                class="cursor-pointer rounded-md p-1 text-[#E94E50] hover:bg-[#E94E50]/10"
                                @click="void requestDelete(bill)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Telegram reminders — real per-bill reminder status, not the
             mock's account-wide toggle set: this app's reminders are set per
             bill (enable + time + timezone, from the edit dialog), and there
             is no "day before / weekly digest" preference to turn a toggle
             into without inventing settings that don't exist. ───────────── -->
            <section
                class="rounded-[16px] bg-[#1a1a1a] p-[22px] ring-1 ring-white/10"
            >
                <p
                    class="mb-3.5 text-[11px] font-medium tracking-[0.13em] text-[#0EA5E9] uppercase"
                >
                    {{ t('finance.bills.telegram_reminders_title') }}
                </p>
                <p class="mb-4.5 text-[13px] leading-[1.6] text-[#989898]">
                    {{ t('finance.bills.telegram_reminders_description') }}
                </p>

                <div
                    v-if="remindedBills.length > 0"
                    class="flex flex-col gap-1.5"
                >
                    <div
                        v-for="bill in remindedBills"
                        :key="bill.id"
                        class="flex items-center justify-between gap-3 rounded-xl bg-[#252525] px-[15px] py-3"
                    >
                        <span class="min-w-0 truncate text-sm text-[#e5e5e5]">
                            <Ciphered :value="bill.title" table="bills" />
                        </span>
                        <div class="shrink-0 text-end">
                            <p
                                v-if="reminderDueLabel(bill)"
                                class="text-xs font-medium"
                                :class="reminderDueClass(bill)"
                            >
                                {{ reminderDueLabel(bill) }}
                            </p>
                            <p
                                class="mt-0.5 text-[11px] text-[#686868]"
                                dir="ltr"
                            >
                                {{ bill.reminder_time }}
                            </p>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-xl bg-[#252525] px-[15px] py-3 text-[13px] text-[#686868]"
                >
                    {{ t('finance.bills.telegram_reminders_empty') }}
                </p>
            </section>
        </div>

        <!-- ── Calendar view — every occurrence due this month, paid or not,
             placed on its day. ────────────────────────────────────────── -->
        <div v-else class="px-[18px] py-[18px]">
            <section
                class="overflow-x-auto rounded-[16px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
            >
                <p class="mb-4 text-[14.5px] font-medium text-white">
                    {{ calendarGrid.monthLabel }}
                </p>

                <div class="min-w-[640px]">
                    <div class="grid grid-cols-7 gap-2">
                        <div
                            v-for="(label, index) in calendarGrid.weekdayLabels"
                            :key="index"
                            class="pb-2 text-center text-[11px] text-[#686868]"
                        >
                            {{ label }}
                        </div>
                    </div>

                    <div
                        v-for="(week, weekIndex) in calendarGrid.weeks"
                        :key="weekIndex"
                        class="grid grid-cols-7 gap-2"
                    >
                        <div
                            v-for="(cell, cellIndex) in week"
                            :key="cellIndex"
                            class="min-h-[92px] rounded-xl p-2"
                            :class="
                                cell
                                    ? cell.isToday
                                        ? 'bg-[#02CD86]/10 ring-1 ring-[#02CD86]/40'
                                        : 'bg-white/[0.02]'
                                    : ''
                            "
                        >
                            <template v-if="cell">
                                <p
                                    class="mb-1.5 text-[11.5px]"
                                    :class="
                                        cell.isToday
                                            ? 'font-semibold text-[#02CD86]'
                                            : 'text-[#989898]'
                                    "
                                >
                                    {{ cell.day }}
                                </p>
                                <div class="flex flex-col gap-1">
                                    <span
                                        v-for="occurrence in (
                                            occurrencesByDate.get(cell.iso) ??
                                            []
                                        ).slice(0, 2)"
                                        :key="occurrence.id"
                                        class="truncate rounded-md px-1.5 py-0.5 text-[10.5px] font-medium"
                                        :style="{
                                            backgroundColor: `${colorForOccurrence(occurrence)}26`,
                                            color: colorForOccurrence(
                                                occurrence,
                                            ),
                                        }"
                                    >
                                        <Ciphered
                                            :value="occurrence.title"
                                            table="bills"
                                        />
                                    </span>
                                    <span
                                        v-if="
                                            (
                                                occurrencesByDate.get(
                                                    cell.iso,
                                                ) ?? []
                                            ).length > 2
                                        "
                                        class="text-[10.5px] text-[#686868]"
                                    >
                                        +{{
                                            (
                                                occurrencesByDate.get(
                                                    cell.iso,
                                                ) ?? []
                                            ).length - 2
                                        }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ── Add / Edit dialog ──────────────────────────────────── -->
        <Dialog :open="isDialogOpen" @update:open="handleDialogOpenChange">
            <DialogContent
                class="max-h-[calc(100dvh-1rem)] overflow-hidden rounded-[20px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-2rem)] sm:max-w-[480px] sm:rounded-[25px]"
                :show-close-button="false"
            >
                <form
                    class="flex max-h-[calc(100dvh-1rem)] flex-col sm:max-h-[calc(100vh-2rem)]"
                    @submit.prevent="submitBill"
                >
                    <div
                        class="app-scroll-thin flex-1 overflow-y-auto px-6 pt-10 pb-5 sm:px-10 sm:pt-12 sm:pb-6"
                    >
                        <DialogHeader class="mb-6 space-y-2 text-start">
                            <DialogTitle
                                class="text-[20px] leading-normal font-medium text-white"
                            >
                                {{
                                    editingId !== null
                                        ? t('finance.bills.edit_bill')
                                        : t('finance.bills.add_bill')
                                }}
                            </DialogTitle>
                        </DialogHeader>

                        <div class="space-y-5">
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-title"
                                    >{{ t('finance.fields.subject') }}</Label
                                >
                                <Input
                                    id="bill-title"
                                    v-model="form.title"
                                    :class="fieldClass"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-amount"
                                        >{{ t('finance.fields.amount') }}</Label
                                    >
                                    <div
                                        v-if="isTomanBillCurrency"
                                        :class="moneyFieldClass"
                                    >
                                        <Input
                                            id="bill-amount"
                                            v-model="displayBillAmount"
                                            class="h-full min-w-0 flex-1 border-0 bg-transparent px-[17px] py-0 text-[16px] leading-[18px] font-normal text-white shadow-none ring-0 outline-none placeholder:text-[#686868] focus-visible:border-0 focus-visible:ring-0"
                                            inputmode="numeric"
                                            placeholder="0"
                                        />
                                        <button
                                            type="button"
                                            class="finance-dialog-money-button"
                                            title="x 1,000"
                                            @click="multiplyBillTomanAmount"
                                        >
                                            000
                                        </button>
                                    </div>
                                    <Input
                                        v-else
                                        id="bill-amount"
                                        v-model="displayBillAmount"
                                        :class="fieldClass"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                    />
                                    <InputError :message="form.errors.amount" />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-currency"
                                        >{{
                                            t('finance.fields.currency')
                                        }}</Label
                                    >
                                    <Select v-model="form.currency">
                                        <SelectTrigger
                                            id="bill-currency"
                                            :class="fieldClass"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content"
                                        >
                                            <SelectItem
                                                v-for="c in props.currencies"
                                                :key="c.value"
                                                :value="c.value"
                                            >
                                                {{ c.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.currency"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-category"
                                    >{{ t('finance.fields.category') }}</Label
                                >
                                <Select v-model="categoryModel">
                                    <SelectTrigger
                                        id="bill-category"
                                        :class="fieldClass"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'finance.categories.uncategorized',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent
                                        class="finance-dialog-select-content"
                                    >
                                        <SelectItem
                                            v-for="c in props.categories"
                                            :key="c.id"
                                            :value="String(c.id)"
                                        >
                                            {{ c.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.category_id"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-recurrence"
                                    >{{ t('finance.bills.recurrence') }}</Label
                                >
                                <Select v-model="form.recurrence_type">
                                    <SelectTrigger
                                        id="bill-recurrence"
                                        :class="fieldClass"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent
                                        class="finance-dialog-select-content"
                                    >
                                        <SelectItem value="monthly">{{
                                            t(
                                                'finance.bills.recurrence_monthly',
                                            )
                                        }}</SelectItem>
                                        <SelectItem value="one_time">{{
                                            t(
                                                'finance.bills.recurrence_one_time',
                                            )
                                        }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.recurrence_type"
                                />
                            </div>

                            <div
                                v-if="form.recurrence_type === 'monthly'"
                                class="grid gap-2"
                            >
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-due-day"
                                    >{{
                                        t('finance.bills.due_day_of_month')
                                    }}</Label
                                >
                                <Input
                                    id="bill-due-day"
                                    v-model="form.due_day_of_month"
                                    type="number"
                                    min="1"
                                    max="31"
                                    :class="fieldClass"
                                />
                                <InputError
                                    :message="form.errors.due_day_of_month"
                                />
                            </div>
                            <div v-else class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-due-date"
                                    >{{ t('finance.bills.due_date') }}</Label
                                >
                                <Input
                                    id="bill-due-date"
                                    v-model="form.due_date"
                                    type="date"
                                    :class="fieldClass"
                                />
                                <InputError :message="form.errors.due_date" />
                            </div>

                            <div
                                v-if="form.recurrence_type === 'monthly'"
                                class="rounded-xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-payment-duration"
                                    >
                                        {{
                                            t('finance.bills.payment_duration')
                                        }}
                                    </Label>
                                    <Select
                                        v-model="form.recurrence_limit_type"
                                    >
                                        <SelectTrigger
                                            id="bill-payment-duration"
                                            :class="fieldClass"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content"
                                        >
                                            <SelectItem value="infinite">
                                                {{
                                                    t(
                                                        'finance.bills.continues_indefinitely',
                                                    )
                                                }}
                                            </SelectItem>
                                            <SelectItem value="count">
                                                {{
                                                    t(
                                                        'finance.bills.limit_by_count',
                                                    )
                                                }}
                                            </SelectItem>
                                            <SelectItem value="date">
                                                {{
                                                    t(
                                                        'finance.bills.limit_by_date',
                                                    )
                                                }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            form.errors.recurrence_limit_type
                                        "
                                    />
                                </div>

                                <div
                                    v-if="
                                        form.recurrence_limit_type === 'count'
                                    "
                                    class="mt-4 grid gap-2"
                                >
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-recurrence-count"
                                    >
                                        {{ t('finance.bills.total_payments') }}
                                    </Label>
                                    <Input
                                        id="bill-recurrence-count"
                                        v-model="form.recurrence_count"
                                        type="number"
                                        :min="minimumPaymentCount"
                                        max="600"
                                        inputmode="numeric"
                                        :class="fieldClass"
                                    />
                                    <p class="text-xs leading-5 text-[#989898]">
                                        {{
                                            t('finance.bills.count_hint', {
                                                count:
                                                    normalizedPreviewCount ??
                                                    minimumPaymentCount,
                                            })
                                        }}
                                    </p>
                                    <InputError
                                        :message="form.errors.recurrence_count"
                                    />
                                </div>

                                <div
                                    v-else-if="
                                        form.recurrence_limit_type === 'date'
                                    "
                                    class="mt-4 grid gap-2"
                                >
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-recurrence-end-date"
                                    >
                                        {{
                                            t('finance.bills.last_payment_date')
                                        }}
                                    </Label>
                                    <Input
                                        id="bill-recurrence-end-date"
                                        v-model="form.recurrence_end_date"
                                        type="date"
                                        :min="minimumRecurrenceEndDate"
                                        :class="fieldClass"
                                    />
                                    <p
                                        v-if="normalizedPreviewCount !== null"
                                        aria-live="polite"
                                        class="text-xs leading-5 text-[#a995ff]"
                                    >
                                        {{
                                            t(
                                                'finance.bills.calculated_payments',
                                                {
                                                    count: normalizedPreviewCount,
                                                },
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-else
                                        class="text-xs leading-5 text-[#989898]"
                                    >
                                        {{ t('finance.bills.date_hint') }}
                                    </p>
                                    <InputError
                                        :message="
                                            form.errors.recurrence_end_date
                                        "
                                    />
                                </div>
                            </div>

                            <label
                                class="flex cursor-pointer items-center gap-2.5"
                            >
                                <Checkbox
                                    :checked="form.telegram_reminder_enabled"
                                    @update:checked="
                                        (val: boolean | 'indeterminate') => {
                                            form.telegram_reminder_enabled =
                                                val === true;
                                        }
                                    "
                                />
                                <span class="text-sm text-white/85">{{
                                    t('finance.bills.telegram_reminder')
                                }}</span>
                            </label>

                            <div
                                v-if="form.telegram_reminder_enabled"
                                class="rounded-xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <label
                                    class="finance-dialog-label mb-2 block"
                                    >{{
                                        t('finance.bills.reminder_time')
                                    }}</label
                                >
                                <div class="flex items-center gap-2">
                                    <Select v-model="form.reminder_time">
                                        <SelectTrigger
                                            :class="fieldClass"
                                            class="w-[120px] shrink-0"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content max-h-48"
                                        >
                                            <SelectItem
                                                v-for="time in timeOptions"
                                                :key="time"
                                                :value="time"
                                            >
                                                {{ time }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Select v-model="form.reminder_timezone">
                                        <SelectTrigger
                                            :class="fieldClass"
                                            class="min-w-0 flex-1"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content max-h-56"
                                        >
                                            <SelectItem
                                                v-for="tz in props.timezones"
                                                :key="tz.value"
                                                :value="tz.value"
                                            >
                                                {{ tz.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.reminder_time"
                                />
                                <InputError
                                    :message="form.errors.reminder_timezone"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-3 border-t border-white/10 px-6 py-4 sm:px-10"
                    >
                        <Button
                            type="button"
                            class="h-11 flex-1 rounded-xl bg-white/5 text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                            @click="closeDialog"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing || sealing"
                            class="h-11 flex-1 rounded-xl bg-[#02CD86] text-[#101010] shadow-none hover:bg-[#08dd93]"
                        >
                            <Spinner v-if="form.processing || sealing" />
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Delete confirm ─────────────────────────────────────── -->
        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                deleteTarget
                    ? t('finance.delete.bill_title', {
                          title: deleteTargetTitle,
                      })
                    : undefined
            "
            :description="t('finance.delete.bill_description')"
            :processing="deleteForm.processing"
            @update:open="(value) => !value && (deleteTarget = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    Pencil,
    Receipt,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import CompactMoney from '@/components/CompactMoney.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
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
import { useAmountMask } from '@/composables/useAmountMask';
import {
    normalizeMoneyInput,
    useMoneyInput,
} from '@/composables/useMoneyInput';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import { useVault } from '@/composables/useVault';
import {
    countMonthlyPaymentsThrough,
    nextMonthlyDueDate,
} from '@/lib/bill-recurrence';
import { buildCalendarMonth, formatAppDate } from '@/lib/date';
import {
    convert as convertMoney,
    formatCompactCurrencyNumber,
} from '@/lib/money';
import type { CurrencyCode, Rates } from '@/lib/money';
import { dashboard } from '@/routes';
import {
    index as billsIndex,
    destroy as destroyBill,
    store as storeBill,
    update as updateBill,
} from '@/routes/bills';
import { pay as payBill } from '@/routes/bills/occurrences';
import type { Encrypted } from '@/types/vault';

type BillOccurrence = {
    id: number;
    due_date: string;
    payment_number: number | null;
};
type RecentPaidOccurrence = {
    id: number;
    due_date: string;
    paid_at: string;
};
type Bill = {
    id: number;
    title: Encrypted<string>;
    amount: Encrypted<string | number>;
    currency: string;
    display_amount: string | null;
    display_currency: string;
    /** Occurrences falling in the current calendar month, for the header total. */
    month_occurrence_count: number;
    /** Occurrences due within the next 30 days, for the "due soon" summary. */
    due_soon_occurrence_count: number;
    /** Set when this bill's latest paid occurrence landed within the last 10
     *  days — takes priority over `next_occurrence` for the row's status. */
    recent_paid_occurrence: RecentPaidOccurrence | null;
    recurrence_type: 'one_time' | 'monthly';
    due_day_of_month: number | null;
    due_date: string | null;
    recurrence_limit_type: 'count' | 'date' | null;
    recurrence_count: number | null;
    recurrence_end_date: string | null;
    payments_made: number;
    schedule_search_date: string;
    telegram_reminder_enabled: boolean;
    reminder_time: string;
    reminder_timezone: string;
    is_active: boolean;
    category_id: number | null;
    category_name: string | null;
    next_occurrence: BillOccurrence | null;
};
type MonthlyBillSummary = {
    /** Null under the vault — the server cannot total what it cannot read. */
    amount: string | null;
    currency: string;
    count: number;
    from: string;
    to: string;
};
type BalanceSummary = {
    balance: number;
    currency: string;
};
type CalendarOccurrence = {
    id: number;
    bill_id: number;
    due_date: string;
    is_paid: boolean;
    title: Encrypted<string>;
    color: string | null;
};

type UpcomingOccurrence = {
    occurrence_id: number;
    bill_id: number;
    title: Encrypted<string>;
    amount: Encrypted<string | number>;
    currency: string;
    display_amount: string | null;
    display_currency: string;
    due_date: string;
    payment_number: number | null;
    payment_count: number | null;
    is_overdue: boolean;
    is_due_today: boolean;
};

const props = defineProps<{
    bills: Bill[];
    categories: { id: number; name: string }[];
    currencies: { label: string; value: string }[];
    timezones: { value: string; label: string }[];
    selectedCurrency: string;
    rates: Rates | null;
    monthlyBillSummary: MonthlyBillSummary;
    dueSoonSummary: MonthlyBillSummary;
    balanceSummary: BalanceSummary | null;
    calendarOccurrences: CalendarOccurrence[];
    upcomingOccurrences: UpcomingOccurrence[];
    userCalendar: string;
    today: string;
}>();

const dueSoonWindowDays = 30;
const viewMode = ref<'list' | 'calendar'>('list');

const { t } = useI18n();
const { revealAsync, sealForSubmit, isArmed, trackKey } = useVault();
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);
const page = usePage();
/** The account timezone set in Settings > Preferences; new bill reminders
 *  default to it, though each bill can still override it. */
const accountTimezone = computed(
    () => (page.props.timezone as string | undefined) ?? 'UTC',
);
const bills = computed(() => props.bills);

const remindedBills = computed(() =>
    props.bills.filter((bill) => bill.telegram_reminder_enabled),
);

/**
 * Total due over the rolling 30-day "due soon" window.
 *
 * Comes straight off the server unless the vault is armed, in which case the
 * amounts are ciphertext and the sum has to be rebuilt here from the decrypted
 * values and each bill's occurrence count within the window.
 */
const clientDueSoonTotal = ref<number | null>(null);

const dueSoonTotal = computed<number | string | null>(
    () => props.dueSoonSummary.amount ?? clientDueSoonTotal.value,
);

watchEffect(async () => {
    trackKey();

    if (props.dueSoonSummary.amount !== null || props.rates === null) {
        clientDueSoonTotal.value = null;

        return;
    }

    const target = props.dueSoonSummary.currency as CurrencyCode;
    let total = 0;

    for (const bill of props.bills) {
        if (bill.due_soon_occurrence_count === 0) {
            continue;
        }

        const amount = await revealAsync<string | number>(
            bill.amount,
            'bills',
            'decimal',
        );

        if (amount === undefined) {
            clientDueSoonTotal.value = null;

            return;
        }

        total +=
            convertMoney(
                amount,
                bill.currency as CurrencyCode,
                target,
                props.rates,
            ) * bill.due_soon_occurrence_count;
    }

    clientDueSoonTotal.value = Math.round(total * 100) / 100;
});

/**
 * How much of the balance is left after covering everything due soon.
 * Null under the vault — `balanceSummary` isn't computed there, since the
 * server cannot sum ciphertext transaction amounts.
 */
const spare = computed<number | null>(() => {
    if (props.balanceSummary === null || props.dueSoonSummary.amount === null) {
        return null;
    }

    return props.balanceSummary.balance - Number(props.dueSoonSummary.amount);
});

const overdueCount = computed(
    () =>
        props.bills.filter((bill) => {
            const days = daysUntilDue(bill);

            return days !== null && days < 0;
        }).length,
);

usePageSubtitle(() => {
    if (props.bills.length === 0) {
        return null;
    }

    return overdueCount.value > 0
        ? t('finance.bills.header_subtitle_overdue', {
              count: props.bills.length,
              overdue: overdueCount.value,
          })
        : t('finance.bills.header_subtitle_none_overdue', {
              count: props.bills.length,
          });
});

const calendarGrid = computed(() =>
    buildCalendarMonth(props.today, props.userCalendar, 'en-US'),
);

const occurrencesByDate = computed(() => {
    const map = new Map<string, CalendarOccurrence[]>();

    for (const occurrence of props.calendarOccurrences) {
        const existing = map.get(occurrence.due_date);

        if (existing) {
            existing.push(occurrence);
        } else {
            map.set(occurrence.due_date, [occurrence]);
        }
    }

    return map;
});

/** Category colour when the bill has one, matching the chip used on
 *  Transactions/Report — falling back to a rotation of the same chart
 *  palette Dashboard uses, keyed by bill so a bill's pill stays the same
 *  colour across every day it appears on. */
const calendarPalette = [
    '#02CD86',
    '#6C4EE9',
    '#F59E0B',
    '#3B82F6',
    '#E94E50',
    '#52525b',
];

function colorForOccurrence(occurrence: CalendarOccurrence): string {
    if (occurrence.color) {
        return occurrence.color;
    }

    return calendarPalette[occurrence.bill_id % calendarPalette.length];
}

const displayDate = (value: string): string =>
    formatAppDate(value, props.userCalendar);
const currencyLabel = (value: string): string =>
    props.currencies.find((currency) => currency.value === value)?.label ??
    t(`finance.currencies.${value}`);

function cadenceLabel(bill: Bill): string {
    return bill.recurrence_type === 'monthly'
        ? `${t('finance.bills.recurrence_monthly')} — ${t('finance.bills.due_day_label', { day: bill.due_day_of_month })}`
        : t('finance.bills.recurrence_one_time');
}

/** Whole days between today and the next occurrence — negative once it's
 *  overdue. Null when there is no next occurrence to count toward. */
function daysUntilDue(bill: Bill): number | null {
    if (!bill.next_occurrence) {
        return null;
    }

    const due = new Date(`${bill.next_occurrence.due_date}T00:00:00`);
    const today = new Date(`${props.today}T00:00:00`);

    return Math.round((due.getTime() - today.getTime()) / 86_400_000);
}

/** Overdue once past today's date; a literal day count once it's inside the
 *  next week, so the row itself carries the urgency instead of a flat
 *  "scheduled" for everything not yet due; scheduled beyond that, or with no
 *  next occurrence at all — nothing urgent left to flag. A bill paid within
 *  the last 10 days shows as "Paid" regardless of what its next occurrence
 *  (already generated up to 3 months ahead) happens to be. */
function statusLabel(bill: Bill): string {
    if (bill.recent_paid_occurrence) {
        return t('finance.bills.paid');
    }

    const days = daysUntilDue(bill);

    if (days === null || days >= 7) {
        return t('finance.bills.scheduled');
    }

    if (days < 0) {
        return t('finance.bills.overdue');
    }

    if (days === 0) {
        return t('finance.bills.due_today');
    }

    return t('finance.bills.due_in_days', { days });
}

function statusDotColor(bill: Bill): string {
    if (bill.recent_paid_occurrence) {
        return '#02CD86';
    }

    const days = daysUntilDue(bill);

    if (days === null || days >= 3) {
        return days !== null && days < 7 ? '#F59E0B' : '#686868';
    }

    return '#E94E50';
}

function reminderDueLabel(bill: Bill): string | null {
    const days = daysUntilDue(bill);

    return days !== null && days < 7 ? `* ${statusLabel(bill)}` : null;
}

function reminderDueClass(bill: Bill): string {
    const days = daysUntilDue(bill);

    return days !== null && days < 3 ? 'text-[#E94E50]' : 'text-[#F59E0B]';
}

const fieldClass =
    'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25';

const isDialogOpen = ref(false);
const editingId = ref<number | null>(null);
const editingBill = ref<Bill | null>(null);

/** True while the browser is wrapping a payload, so the button stays disabled. */
const sealing = ref(false);

const form = useForm({
    title: '',
    amount: '',
    currency: 'toman',
    category_id: '',
    recurrence_type: 'monthly' as 'one_time' | 'monthly',
    due_day_of_month: '1',
    due_date: '',
    recurrence_limit_type: 'infinite' as 'infinite' | 'count' | 'date',
    recurrence_count: '12',
    recurrence_end_date: '',
    telegram_reminder_enabled: false,
    reminder_time: '09:00',
    reminder_timezone: accountTimezone.value,
});

const minimumPaymentCount = computed(() =>
    Math.max(1, editingBill.value?.payments_made ?? 0),
);

const scheduleSearchDate = computed(
    () => editingBill.value?.schedule_search_date ?? props.today,
);

const minimumRecurrenceEndDate = computed(() => {
    const dueDay = Number(form.due_day_of_month);

    if (!Number.isInteger(dueDay) || dueDay < 1 || dueDay > 31) {
        return scheduleSearchDate.value;
    }

    return nextMonthlyDueDate(
        dueDay,
        props.userCalendar,
        scheduleSearchDate.value,
    );
});

const normalizedPreviewCount = computed<number | null>(() => {
    if (
        form.recurrence_type !== 'monthly' ||
        form.recurrence_limit_type === 'infinite'
    ) {
        return null;
    }

    if (form.recurrence_limit_type === 'count') {
        const count = Number(form.recurrence_count);

        return Number.isInteger(count) && count > 0 ? count : null;
    }

    const dueDay = Number(form.due_day_of_month);

    if (
        !Number.isInteger(dueDay) ||
        dueDay < 1 ||
        dueDay > 31 ||
        !/^\d{4}-\d{2}-\d{2}$/.test(form.recurrence_end_date)
    ) {
        return null;
    }

    return (
        (editingBill.value?.payments_made ?? 0) +
        countMonthlyPaymentsThrough(
            dueDay,
            props.userCalendar,
            scheduleSearchDate.value,
            form.recurrence_end_date,
        )
    );
});

const timeOptions = Array.from(
    { length: 24 },
    (_, i) => `${String(i).padStart(2, '0')}:00`,
);

const categoryModel = computed({
    get: () => (form.category_id ? String(form.category_id) : undefined),
    set: (value: string | undefined) => {
        form.category_id = value ?? '';
    },
});

const moneyFieldClass =
    'finance-dialog-money-field finance-dialog-field-income focus-within:border-[#02CD86] focus-within:ring-2 focus-within:ring-[#02CD86]/25';

const {
    display: displayBillAmount,
    isToman: isTomanBillCurrency,
    multiplyByThousand: multiplyBillTomanAmount,
    renormalize: renormalizeBillAmount,
} = useMoneyInput({
    get: () => form.amount,
    set: (value) => {
        form.amount = value;
    },
    currency: () => form.currency,
});

function openCreateDialog(): void {
    editingId.value = null;
    editingBill.value = null;
    form.clearErrors();
    form.reset();
    form.currency = props.currencies[0]?.value ?? 'toman';
    form.recurrence_type = 'monthly';
    form.due_day_of_month = '1';
    form.recurrence_limit_type = 'infinite';
    form.recurrence_count = '12';
    form.recurrence_end_date = '';
    form.reminder_time = '09:00';
    form.reminder_timezone = accountTimezone.value;
    isDialogOpen.value = true;
}

async function openEditDialog(bill: Bill): Promise<void> {
    editingId.value = bill.id;
    editingBill.value = bill;
    form.clearErrors();
    form.title = (await revealAsync<string>(bill.title, 'bills')) ?? '';
    form.amount = normalizeMoneyInput(
        String(
            (await revealAsync<string | number>(
                bill.amount,
                'bills',
                'decimal',
            )) ?? '',
        ),
        bill.currency,
    );
    form.currency = bill.currency;
    form.category_id = bill.category_id ? String(bill.category_id) : '';
    form.recurrence_type = bill.recurrence_type;
    form.due_day_of_month = bill.due_day_of_month
        ? String(bill.due_day_of_month)
        : '1';
    form.due_date = bill.due_date ?? '';
    form.recurrence_limit_type = bill.recurrence_limit_type ?? 'infinite';
    form.recurrence_count = String(bill.recurrence_count ?? 12);
    form.recurrence_end_date = bill.recurrence_end_date ?? '';
    form.telegram_reminder_enabled = Boolean(bill.telegram_reminder_enabled);
    const storedHour =
        (bill.reminder_time ?? '09:00').split(':')[0]?.padStart(2, '0') ?? '09';
    form.reminder_time = `${storedHour}:00`;
    form.reminder_timezone = bill.reminder_timezone ?? accountTimezone.value;
    isDialogOpen.value = true;
}

function closeDialog(): void {
    isDialogOpen.value = false;
    editingId.value = null;
    editingBill.value = null;
    form.clearErrors();
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) {
        closeDialog();
    }
}

/**
 * Encrypts the title and amount before they leave the browser when the vault is
 * armed, and is a no-op otherwise.
 *
 * `transform` rather than mutating the form: the inputs stay bound to plaintext,
 * so the dialog still shows what the user typed if validation comes back failing.
 */
async function submitBill(): Promise<void> {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    };

    sealing.value = true;

    try {
        const payload = await sealForSubmit({ ...form.data() }, 'bills', {
            title: 'string',
            amount: 'decimal',
        });

        form.transform(() => payload);
    } catch {
        return;
    } finally {
        sealing.value = false;
    }

    if (editingId.value !== null) {
        form.put(updateBill.url(editingId.value), options);
    } else {
        form.post(storeBill.url(), options);
    }
}

const deleteTarget = ref<Bill | null>(null);
const deleteTargetTitle = ref('');
const deleteForm = useForm({});

async function requestDelete(bill: Bill): Promise<void> {
    deleteTarget.value = bill;
    // Resolved rather than interpolated straight in: under the vault `bill.title`
    // is a ciphertext object, which would render as [object Object].
    deleteTargetTitle.value =
        (await revealAsync<string>(bill.title, 'bills')) ?? '';
}

function confirmDelete(): void {
    if (!deleteTarget.value) {
        return;
    }

    deleteForm.delete(destroyBill.url(deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
}

const payingId = ref<number | null>(null);

/**
 * Marking a bill paid writes a Cost transaction.
 *
 * Under the vault the browser has to build that transaction's title and amount
 * itself: a ciphertext is bound to its table by the AAD, so the bill's blobs
 * cannot simply be copied across, and the server holds no key to re-seal them.
 */
async function markPaid(bill: Bill): Promise<void> {
    if (!bill.next_occurrence) {
        return;
    }

    payingId.value = bill.next_occurrence.id;

    const payload: { title?: string; amount?: string } = {};

    if (isArmed()) {
        const title = await revealAsync<string>(bill.title, 'bills');
        const amount = await revealAsync<string | number>(
            bill.amount,
            'bills',
            'decimal',
        );

        if (title === undefined || amount === undefined) {
            payingId.value = null;

            return;
        }

        const sealed = await sealForSubmit(
            { title, amount: String(amount) },
            'transactions',
            { title: 'string', amount: 'decimal' },
        );

        payload.title = sealed.title;
        payload.amount = sealed.amount;
    }

    const payForm = useForm(payload);
    payForm.post(
        payBill.url({ bill: bill.id, occurrence: bill.next_occurrence.id }),
        {
            preserveScroll: true,
            onFinish: () => {
                payingId.value = null;
            },
        },
    );
}

watch(() => form.currency, renormalizeBillAmount);

watch(
    () => form.recurrence_type,
    (value) => {
        if (value === 'monthly' && !form.due_day_of_month) {
            form.due_day_of_month = '1';
        }
    },
);

watch(
    () => form.recurrence_limit_type,
    (value) => {
        if (value === 'count' && !form.recurrence_count) {
            form.recurrence_count = String(
                Math.max(12, minimumPaymentCount.value),
            );
        }

        if (value === 'date' && !form.recurrence_end_date) {
            form.recurrence_end_date = minimumRecurrenceEndDate.value;
        }
    },
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Bills', href: billsIndex() },
        ],
    },
});
</script>
