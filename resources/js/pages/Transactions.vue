<template>
    <Head :title="t('finance.transactions.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between"
            >
                <div class="flex flex-wrap items-end gap-3">
                    <div
                        class="grid min-w-[160px] flex-1 gap-1.5 sm:max-w-[220px]"
                    >
                        <Label for="transaction_search" class="text-xs">{{
                            t('finance.fields.search')
                        }}</Label>
                        <Input
                            id="transaction_search"
                            v-model="filterSearch"
                            :class="filterFieldClass"
                            :placeholder="t('finance.filters.title_or_note')"
                            @keyup.enter="applyFilters()"
                        />
                    </div>

                    <div
                        class="grid min-w-[130px] flex-1 gap-1.5 sm:max-w-[170px]"
                    >
                        <Label for="transaction_category" class="text-xs">{{
                            t('finance.fields.category')
                        }}</Label>
                        <Select v-model="filterCategory">
                            <SelectTrigger
                                id="transaction_category"
                                :class="filterFieldClass"
                            >
                                <SelectValue
                                    :placeholder="
                                        t('finance.filters.all_categories')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{
                                    t('finance.filters.all_categories')
                                }}</SelectItem>
                                <!-- Where the logbook's uncategorised count links
                                     to; without the option the filter would be
                                     active but invisible in the control. -->
                                <SelectItem value="none">{{
                                    t('finance.filters.uncategorised')
                                }}</SelectItem>
                                <SelectItem
                                    v-for="category in filterCategories"
                                    :key="category.id"
                                    :value="category.id.toString()"
                                >
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        class="grid min-w-[210px] flex-1 gap-1.5 sm:max-w-[260px]"
                    >
                        <Label for="transaction_from" class="text-xs">{{
                            t('finance.fields.from')
                        }}</Label>
                        <BirthdatePicker
                            v-model="filterFrom"
                            name="transaction_from"
                            :required="false"
                            :years-back="16"
                            :years-forward="1"
                            :trigger-class="filterFieldClass"
                        />
                    </div>

                    <div
                        class="grid min-w-[210px] flex-1 gap-1.5 sm:max-w-[260px]"
                    >
                        <Label for="transaction_to" class="text-xs">{{
                            t('finance.fields.to')
                        }}</Label>
                        <BirthdatePicker
                            v-model="filterTo"
                            name="transaction_to"
                            :required="false"
                            :years-back="16"
                            :years-forward="1"
                            :trigger-class="filterFieldClass"
                        />
                    </div>

                    <div class="flex shrink-0 items-end gap-2">
                        <Button
                            class="h-9 shrink-0 rounded-full bg-[#02CD86] px-4 text-xs font-semibold text-[#071812] shadow-[0_10px_24px_rgba(2,205,134,0.22)] hover:bg-[#00b978] sm:text-sm"
                            @click="applyFilters()"
                        >
                            <Search class="size-4" />
                            {{ t('finance.actions.filter') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="size-9 shrink-0 rounded-full border-white/10 bg-[#252525] p-0 text-[#989898] shadow-none hover:bg-white/10 hover:text-white"
                            @click="clearFilters"
                        >
                            <RotateCcw class="size-4" />
                            <span class="sr-only">{{
                                t('finance.actions.reset_filters')
                            }}</span>
                        </Button>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <Button
                        class="h-9 shrink-0 rounded-full bg-white/5 px-4 text-xs text-white shadow-none ring-1 ring-white/15 hover:bg-white/10 sm:text-sm"
                        @click="openImportDialog"
                    >
                        <Upload class="size-4" />
                        {{ t('finance.actions.import_transactions') }}
                    </Button>
                    <a
                        :href="`/transactions/export?currency=${selectedCurrency}`"
                        class="inline-flex h-9 shrink-0 items-center gap-2 rounded-full bg-white/5 px-4 text-xs whitespace-nowrap text-white ring-1 ring-white/15 transition-colors hover:bg-white/10 sm:text-sm"
                    >
                        <Download class="size-4" />
                        {{ t('finance.actions.export_transactions') }}
                    </a>
                </div>
            </div>
        </section>

        <div
            class="grid items-start gap-[18px] px-[18px] py-6 sm:py-[38px] xl:grid-cols-2"
        >
            <section
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-5 py-5"
                >
                    <div>
                        <h2
                            class="text-lg leading-none font-normal text-white sm:text-xl"
                        >
                            {{ t('finance.tables.money_going_out') }}
                        </h2>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            class="h-10 w-max justify-between rounded-md bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-3.5 text-sm leading-none font-bold text-white shadow-[0_10px_20px_rgba(108,78,233,0.22)] transition hover:brightness-105 sm:h-11 sm:text-base"
                            @click="openCreateForm('cost')"
                        >
                            <span class="grid text-left">
                                <span class="col-start-1 row-start-1">
                                    {{ t('finance.actions.add_cost') }}
                                </span>
                                <span
                                    class="invisible col-start-1 row-start-1"
                                    aria-hidden="true"
                                >
                                    {{ t('finance.actions.add_income') }}
                                </span>
                            </span>
                            <span
                                class="grid h-[1.65em] w-[1.65em] min-w-[1.65em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                            >
                                <Plus class="size-5" />
                            </span>
                        </Button>
                    </div>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left">
                                <th
                                    class="finance-cell-tight rounded-l-2xl bg-[#24212f] px-2 py-3 text-center"
                                >
                                    <Checkbox
                                        :checked="costsSelectionState"
                                        @update:checked="toggleAllCosts"
                                    />
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-3 text-center font-normal text-[#c4b2ff] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-3 text-center font-normal text-[#c4b2ff] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-3 text-center font-normal text-[#c4b2ff] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden bg-[#24212f] px-3 py-3 text-center font-normal whitespace-nowrap text-[#c4b2ff] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#24212f] px-3 py-3 sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions.costs"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="finance-cell-tight px-2 py-3 text-center"
                                >
                                    <Checkbox
                                        :checked="
                                            selectedCostIds.has(transaction.id)
                                        "
                                        @update:checked="
                                            (v: boolean | 'indeterminate') =>
                                                toggleCost(
                                                    transaction.id,
                                                    v === true,
                                                )
                                        "
                                    />
                                </td>
                                <td
                                    class="px-3 py-3 text-center leading-tight font-normal text-white sm:px-5"
                                >
                                    <button
                                        class="cursor-pointer text-white hover:text-[#947BFF]"
                                        type="button"
                                        @click="openEditForm(transaction)"
                                    >
                                        <Ciphered
                                            :value="transaction.title"
                                            table="transactions"
                                        />
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[88px] justify-center rounded-md bg-white/10 px-3 py-1.5 leading-tight font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:px-5"
                                >
                                    <CipheredMoney
                                        :amount="transaction.amount"
                                        :display-amount="
                                            transaction.display_amount
                                        "
                                        :currency="transaction.currency"
                                        :display-currency="
                                            transaction.display_currency
                                        "
                                        :rates="props.rates"
                                    />
                                </td>
                                <td
                                    class="hidden px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                                <td class="px-3 py-3 text-center sm:px-5">
                                    <div
                                        class="flex items-center justify-center gap-1"
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
                            <tr v-if="props.transactions.costs.length === 0">
                                <td
                                    colspan="6"
                                    class="px-5 py-12 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_costs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Cost table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.costs &&
                        props.transactions.meta.costs.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 border-t border-white/[0.07] px-5 py-3 text-xs"
                >
                    <button
                        :disabled="
                            props.transactions.meta.costs.current_page <= 1
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page - 1,
                            )
                        "
                    >
                        ←
                    </button>
                    <span class="text-[#989898]">
                        {{ props.transactions.meta.costs.current_page }}
                        /
                        {{ props.transactions.meta.costs.last_page }}
                        <span class="ml-1 text-[#6b6b6b]"
                            >({{ props.transactions.meta.costs.total }})</span
                        >
                    </span>
                    <button
                        :disabled="
                            props.transactions.meta.costs.current_page >=
                            props.transactions.meta.costs.last_page
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page + 1,
                            )
                        "
                    >
                        →
                    </button>
                </div>
            </section>

            <section
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-5 py-5"
                >
                    <div>
                        <h2
                            class="text-lg leading-none font-normal text-white sm:text-xl"
                        >
                            {{ t('finance.tables.money_coming_in') }}
                        </h2>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            class="h-10 w-max justify-between rounded-md bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-3.5 text-sm leading-none font-bold text-white shadow-[0_10px_20px_rgba(2,205,134,0.22)] transition hover:brightness-105 sm:h-11 sm:text-base"
                            @click="openCreateForm('income')"
                        >
                            <span class="grid text-left">
                                <span class="col-start-1 row-start-1">
                                    {{ t('finance.actions.add_income') }}
                                </span>
                                <span
                                    class="invisible col-start-1 row-start-1"
                                    aria-hidden="true"
                                >
                                    {{ t('finance.actions.add_income') }}
                                </span>
                            </span>
                            <span
                                class="grid h-[1.65em] w-[1.65em] min-w-[1.65em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                            >
                                <Plus class="size-5" />
                            </span>
                        </Button>
                    </div>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left">
                                <th
                                    class="finance-cell-tight rounded-l-2xl bg-[#0d2620] px-2 py-3 text-center"
                                >
                                    <Checkbox
                                        :checked="incomesSelectionState"
                                        @update:checked="toggleAllIncomes"
                                    />
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-3 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-3 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-3 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden bg-[#0d2620] px-3 py-3 text-center font-normal whitespace-nowrap text-[#7ee8c4] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#0d2620] px-3 py-3 sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions
                                    .incomes"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="finance-cell-tight px-2 py-3 text-center"
                                >
                                    <Checkbox
                                        :checked="
                                            selectedIncomeIds.has(
                                                transaction.id,
                                            )
                                        "
                                        @update:checked="
                                            (v: boolean | 'indeterminate') =>
                                                toggleIncome(
                                                    transaction.id,
                                                    v === true,
                                                )
                                        "
                                    />
                                </td>
                                <td
                                    class="px-3 py-3 text-center leading-tight font-normal text-white sm:px-5"
                                >
                                    <button
                                        class="cursor-pointer text-white hover:text-[#02CD86]"
                                        type="button"
                                        @click="openEditForm(transaction)"
                                    >
                                        <Ciphered
                                            :value="transaction.title"
                                            table="transactions"
                                        />
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[88px] justify-center rounded-md bg-white/10 px-3 py-1.5 leading-tight font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:px-5"
                                >
                                    <CipheredMoney
                                        :amount="transaction.amount"
                                        :display-amount="
                                            transaction.display_amount
                                        "
                                        :currency="transaction.currency"
                                        :display-currency="
                                            transaction.display_currency
                                        "
                                        :rates="props.rates"
                                    />
                                </td>
                                <td
                                    class="hidden px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                                <td class="px-3 py-3 text-center sm:px-5">
                                    <div
                                        class="flex items-center justify-center gap-1"
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
                            <tr v-if="props.transactions.incomes.length === 0">
                                <td
                                    colspan="6"
                                    class="px-5 py-12 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_incomes') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Income table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.incomes &&
                        props.transactions.meta.incomes.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 border-t border-white/[0.07] px-5 py-3 text-xs"
                >
                    <button
                        :disabled="
                            props.transactions.meta.incomes.current_page <= 1
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page -
                                    1,
                            )
                        "
                    >
                        ←
                    </button>
                    <span class="text-[#989898]">
                        {{ props.transactions.meta.incomes.current_page }}
                        /
                        {{ props.transactions.meta.incomes.last_page }}
                        <span class="ml-1 text-[#6b6b6b]"
                            >({{ props.transactions.meta.incomes.total }})</span
                        >
                    </span>
                    <button
                        :disabled="
                            props.transactions.meta.incomes.current_page >=
                            props.transactions.meta.incomes.last_page
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page +
                                    1,
                            )
                        "
                    >
                        →
                    </button>
                </div>
            </section>
        </div>

        <!-- ── Bulk action toolbar ─────────────────────────────────── -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div
                v-if="totalSelected > 0"
                class="fixed inset-x-0 bottom-6 z-50 flex justify-center"
            >
                <div
                    class="flex flex-wrap items-center gap-3 rounded-2xl bg-[#1a1a1a] px-5 py-3 shadow-[0_8px_32px_rgba(0,0,0,0.5)] ring-1 ring-white/15"
                >
                    <span class="text-sm font-medium text-[#989898]">
                        {{ totalSelected }}
                        {{ totalSelected === 1 ? 'item' : 'items' }} selected
                    </span>

                    <div
                        v-if="selectionType !== null"
                        class="flex items-center gap-2"
                    >
                        <select
                            v-model="bulkCategoryId"
                            class="h-8 rounded-lg border border-white/10 bg-[#252525] px-2 text-xs text-white [color-scheme:dark] focus:outline-none"
                        >
                            <option value="">
                                {{ t('finance.actions.bulk_assign') }}…
                            </option>
                            <option
                                v-for="cat in bulkCategories"
                                :key="cat.id"
                                :value="String(cat.id)"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                        <button
                            :disabled="!bulkCategoryId"
                            class="h-8 rounded-lg bg-[#947BFF]/20 px-3 text-xs font-medium text-[#947BFF] ring-1 ring-[#947BFF]/30 transition hover:bg-[#947BFF]/30 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="bulkAssignCategory"
                        >
                            Apply
                        </button>
                    </div>

                    <button
                        v-if="hasGroupSelection"
                        class="h-8 rounded-lg bg-[#E94E50]/15 px-3 text-xs font-medium text-[#E94E50] ring-1 ring-[#E94E50]/25 transition hover:bg-[#E94E50]/25"
                        @click="requestBulkDelete"
                    >
                        {{ t('finance.actions.bulk_delete') }}
                    </button>
                    <button
                        class="h-8 rounded-lg bg-white/5 px-3 text-xs font-medium text-[#6b6b6b] ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white"
                        @click="clearSelection"
                    >
                        {{ t('common.clear') }}
                    </button>
                </div>
            </div>
        </Transition>

        <TransactionDialog
            v-model:open="isDialogOpen"
            :type="dialogTransactionType"
            :transaction="editingTransaction"
            :categories="props.categories"
            :currencies="props.currencies"
        />

        <Dialog
            :open="isImportDialogOpen"
            @update:open="handleImportDialogOpenChange"
        >
            <DialogContent
                class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[25px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-w-[980px]"
            >
                <div class="space-y-6 px-6 py-8">
                    <DialogHeader class="space-y-2 text-start">
                        <DialogTitle
                            class="text-[22px] leading-tight font-medium text-white"
                        >
                            {{ t('finance.import.title') }}
                        </DialogTitle>
                        <DialogDescription
                            class="max-w-3xl text-sm leading-6 text-[#989898]"
                        >
                            {{ t('finance.import.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="importResult"
                        class="rounded-md bg-[#0d2620] px-4 py-3 text-sm text-[#7ee8c4] ring-1 ring-[#02CD86]/20"
                    >
                        {{
                            t('finance.import.result', {
                                imported: importResult.imported,
                                skipped: importResult.skipped,
                            })
                        }}
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[1fr_0.9fr]">
                        <div class="space-y-3">
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <h3 class="text-base font-medium text-white">
                                    {{ t('finance.import.prompt_title') }}
                                </h3>
                                <Button
                                    type="button"
                                    class="h-9 rounded-md bg-white/10 px-3 text-sm text-white shadow-none ring-1 ring-white/15 hover:bg-white/15"
                                    @click="copyImportPrompt"
                                >
                                    <ClipboardCheck
                                        v-if="promptCopied"
                                        class="size-4"
                                    />
                                    <Clipboard v-else class="size-4" />
                                    {{
                                        promptCopied
                                            ? t('finance.import.copied')
                                            : t('finance.import.copy_prompt')
                                    }}
                                </Button>
                            </div>
                            <textarea
                                class="min-h-[260px] w-full resize-y rounded-md border border-white/10 bg-[#111111] p-4 font-mono text-xs leading-5 text-[#d8d8d8] [color-scheme:dark] focus-visible:border-[#947BFF] focus-visible:ring-2 focus-visible:ring-[#947BFF]/25 focus-visible:outline-none"
                                readonly
                                :value="importPrompt"
                            />
                        </div>

                        <div class="space-y-4">
                            <a
                                class="inline-flex h-10 items-center gap-2 rounded-md bg-white/10 px-4 text-sm text-white ring-1 ring-white/15 hover:bg-white/15"
                                href="/transactions/import-template"
                            >
                                <FileDown class="size-4" />
                                {{ t('finance.import.download_template') }}
                            </a>

                            <form
                                class="space-y-3"
                                @submit.prevent="submitImportPreview"
                            >
                                <Label for="transaction_import_file">
                                    {{ t('finance.import.file_label') }}
                                </Label>
                                <Input
                                    id="transaction_import_file"
                                    ref="importFileInput"
                                    class="h-11 rounded-md !border-white/10 !bg-[#252525] text-sm !text-white [color-scheme:dark] file:mr-4 file:rounded-md file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-white"
                                    type="file"
                                    accept=".csv,text/csv"
                                    @change="selectImportFile"
                                />
                                <InputError :message="importForm.errors.file" />
                                <Button
                                    type="submit"
                                    class="h-10 rounded-md bg-[#111111] px-4 text-sm text-white shadow-none ring-1 ring-white/10 hover:bg-[#1f1f1f]"
                                    :disabled="
                                        importForm.processing ||
                                        importForm.file === null
                                    "
                                >
                                    <Spinner v-if="importForm.processing" />
                                    <Upload class="size-4" />
                                    {{ t('finance.import.preview') }}
                                </Button>
                            </form>

                            <div
                                v-if="importPreview"
                                class="grid grid-cols-2 gap-2 text-sm"
                            >
                                <div
                                    class="rounded-md bg-white/5 px-3 py-2 ring-1 ring-white/10"
                                >
                                    <span class="block text-[#989898]">{{
                                        t('finance.import.total_rows')
                                    }}</span>
                                    <span class="text-lg text-white">{{
                                        importPreview.summary.total
                                    }}</span>
                                </div>
                                <div
                                    class="rounded-md bg-white/5 px-3 py-2 ring-1 ring-white/10"
                                >
                                    <span class="block text-[#989898]">{{
                                        t('finance.import.importable_rows')
                                    }}</span>
                                    <span class="text-lg text-white">{{
                                        importPreview.summary.importable
                                    }}</span>
                                </div>
                                <div
                                    class="rounded-md bg-white/5 px-3 py-2 ring-1 ring-white/10"
                                >
                                    <span class="block text-[#989898]">{{
                                        t('finance.import.invalid_rows')
                                    }}</span>
                                    <span class="text-lg text-white">{{
                                        importPreview.summary.invalid
                                    }}</span>
                                </div>
                                <div
                                    class="rounded-md bg-white/5 px-3 py-2 ring-1 ring-white/10"
                                >
                                    <span class="block text-[#989898]">{{
                                        t('finance.import.duplicate_rows')
                                    }}</span>
                                    <span class="text-lg text-white">{{
                                        importPreview.summary.duplicate
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="previewRows.length > 0"
                        class="overflow-x-auto rounded-md ring-1 ring-white/10"
                    >
                        <table class="w-full min-w-[900px] text-sm">
                            <thead class="bg-[#111111] text-[#989898]">
                                <tr>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.import.row') }}
                                    </th>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.import.status') }}
                                    </th>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.fields.date') }}
                                    </th>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.fields.type') }}
                                    </th>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.fields.category') }}
                                    </th>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.fields.amount') }}
                                    </th>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.fields.subject') }}
                                    </th>
                                    <th class="px-3 py-3 text-start">
                                        {{ t('finance.import.notes') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in previewRows"
                                    :key="row.row_number"
                                    class="border-t border-white/10"
                                >
                                    <td class="px-3 py-3 text-[#989898]">
                                        {{ row.row_number }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex rounded-md px-2 py-1 text-xs"
                                            :class="
                                                importStatusClass(row.status)
                                            "
                                        >
                                            {{
                                                t(
                                                    `finance.import.statuses.${row.status}`,
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            row.data?.occurred_at ??
                                            row.original.occurred_at ??
                                            '-'
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            row.data?.type ??
                                            row.original.type ??
                                            '-'
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            row.data?.category ??
                                            row.original.category ??
                                            '-'
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            row.data
                                                ? formatAmount(row.data.amount)
                                                : (row.original.amount ?? '-')
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            row.data?.title ??
                                            row.original.title ??
                                            '-'
                                        }}
                                    </td>
                                    <td class="max-w-[260px] px-3 py-3">
                                        <div
                                            v-if="
                                                row.errors.length > 0 ||
                                                row.warnings.length > 0
                                            "
                                            class="space-y-1"
                                        >
                                            <p
                                                v-for="error in row.errors"
                                                :key="`error-${row.row_number}-${error}`"
                                                class="text-xs text-[#ffb4b4]"
                                            >
                                                {{ error }}
                                            </p>
                                            <p
                                                v-for="warning in row.warnings"
                                                :key="`warning-${row.row_number}-${warning}`"
                                                class="text-xs text-[#ffd58a]"
                                            >
                                                {{ warning }}
                                            </p>
                                        </div>
                                        <span v-else class="text-[#989898]">
                                            {{ t('finance.import.ready') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            class="h-10 rounded-md bg-white/5 px-4 text-sm text-[#989898] shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                            @click="closeImportDialog"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            class="h-10 rounded-md bg-[#111111] px-4 text-sm text-white shadow-none ring-1 ring-white/10 hover:bg-[#1f1f1f]"
                            :disabled="
                                importProcessing ||
                                !importPreview ||
                                importPreview.summary.importable === 0
                            "
                            @click="confirmImport"
                        >
                            <Spinner v-if="importProcessing" />
                            <Check class="size-4" />
                            {{ t('finance.import.confirm') }}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                t('finance.delete.transaction_title', {
                    title: deleteTargetLabel,
                })
            "
            :description="t('finance.delete.transaction_description')"
            @update:open="clearDeleteTarget"
            @confirm="confirmDelete"
        />

        <ConfirmDeleteModal
            :open="isBulkDeleteDialogOpen"
            :title="
                t('finance.delete.bulk_transactions_title', {
                    count: totalSelected,
                })
            "
            :description="t('finance.delete.bulk_transactions_description')"
            @update:open="isBulkDeleteDialogOpen = false"
            @confirm="confirmBulkDelete"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    Clipboard,
    ClipboardCheck,
    FileDown,
    Pencil,
    Plus,
    Download,
    RotateCcw,
    Search,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useVault } from '@/composables/useVault';
import { formatAppDate } from '@/lib/date';
import type { Rates } from '@/lib/money';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';
import type { Encrypted } from '@/types/vault';

type TransactionType = 'cost' | 'income';
type FilterType = TransactionType | 'all';
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
    amount: Encrypted<string>;
    currency: Currency;
    display_amount: string | null;
    display_currency: Currency;
    title: Encrypted<string>;
    description: Encrypted<string> | null;
    occurred_at: string;
    category: Category | null;
    category_id: number;
};

type CurrencyOption = {
    label: string;
    value: Currency;
};

type ImportStatus = 'valid' | 'invalid' | 'duplicate';

type ImportPreviewRow = {
    row_number: number;
    status: ImportStatus;
    original: Record<string, string>;
    data: {
        type: TransactionType;
        category_id: number | null;
        category: string;
        category_is_new: boolean;
        amount: string;
        currency: Currency;
        title: string;
        description: string | null;
        occurred_at: string;
    } | null;
    errors: string[];
    warnings: string[];
};

type ImportPreview = {
    rows: ImportPreviewRow[];
    summary: {
        total: number;
        valid: number;
        invalid: number;
        duplicate: number;
        importable: number;
    };
};

type ImportResult = {
    imported: number;
    skipped: number;
    skipped_duplicates: number;
};

const props = defineProps<{
    filters: {
        search: string;
        type: FilterType;
        /** A category id, `'none'` for uncategorised-only, or null for all. */
        category: number | 'none' | null;
        from: string;
        to: string;
    };
    transactions: {
        costs: Transaction[];
        incomes: Transaction[];
        meta?: {
            costs: {
                current_page: number;
                last_page: number;
                total: number;
            } | null;
            incomes: {
                current_page: number;
                last_page: number;
                total: number;
            } | null;
        } | null;
    };
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    rates: Rates | null;
    summary: {
        cost: string;
        income: string;
        count: number;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Transactions',
                href: transactionsIndex(),
            },
        ],
    },
});

const isDialogOpen = ref(false);
const isImportDialogOpen = ref(false);
const isBulkDeleteDialogOpen = ref(false);
const dialogTransactionType = ref<TransactionType>('cost');
const editingTransaction = ref<Transaction | null>(null);
const deleteTarget = ref<Transaction | null>(null);
const deleteTargetLabel = ref('');
const page = usePage();
const { t } = useI18n();
const { revealAsync } = useVault();
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

const requestDelete = async (transaction: Transaction) => {
    deleteTarget.value = transaction;
    deleteTargetLabel.value =
        (await revealAsync<string>(
            transaction.title,
            'transactions',
            'string',
        )) ?? `#${transaction.id}`;
};

const confirmDelete = () => {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(`/transactions/${deleteTarget.value.id}`, {
        preserveScroll: true,
    });
    clearDeleteTarget();
};

function clearDeleteTarget(): void {
    deleteTarget.value = null;
    deleteTargetLabel.value = '';
}
const selectedCurrency = ref<Currency>(props.selectedCurrency);
const importFileInput = ref<HTMLInputElement | null>(null);
const importPreview = ref<ImportPreview | null>(null);
const importResult = ref<ImportResult | null>(null);
const importProcessing = ref(false);
const promptCopied = ref(false);
const filterSearch = ref(props.filters.search);
const filterCategory = ref(props.filters.category?.toString() ?? 'all');
const filterFrom = ref(props.filters.from);
const filterTo = ref(props.filters.to);
const filterFieldClass =
    'h-9 w-full rounded-md !border-white/10 !bg-[#252525] px-3 text-sm font-normal !text-white shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

// ── Bulk selection ────────────────────────────────────────────
const selectedCostIds = ref<Set<number>>(new Set());
const selectedIncomeIds = ref<Set<number>>(new Set());
const bulkCategoryId = ref<string>('');

const totalSelected = computed(
    () => selectedCostIds.value.size + selectedIncomeIds.value.size,
);
const hasGroupSelection = computed(() => totalSelected.value > 1);
const selectionType = computed<TransactionType | null>(() => {
    if (selectedCostIds.value.size > 0 && selectedIncomeIds.value.size === 0) {
        return 'cost';
    }

    if (selectedIncomeIds.value.size > 0 && selectedCostIds.value.size === 0) {
        return 'income';
    }

    return null;
});
const bulkCategories = computed(() => {
    if (selectionType.value === 'cost') {
        return props.categories.cost ?? [];
    }

    if (selectionType.value === 'income') {
        return props.categories.income ?? [];
    }

    return [];
});
const costsSelectionState = computed(() =>
    selectionState(props.transactions.costs, selectedCostIds.value),
);
const incomesSelectionState = computed(() =>
    selectionState(props.transactions.incomes, selectedIncomeIds.value),
);

function toggleCost(id: number, selected: boolean): void {
    const next = new Set(selectedCostIds.value);

    if (selected) {
        next.add(id);
    } else {
        next.delete(id);
    }

    selectedCostIds.value = next;
}
function toggleIncome(id: number, selected: boolean): void {
    const next = new Set(selectedIncomeIds.value);

    if (selected) {
        next.add(id);
    } else {
        next.delete(id);
    }

    selectedIncomeIds.value = next;
}
function toggleAllCosts(checked: boolean | 'indeterminate'): void {
    selectedCostIds.value =
        checked !== false
            ? new Set(props.transactions.costs.map((t) => t.id))
            : new Set();
}
function toggleAllIncomes(checked: boolean | 'indeterminate'): void {
    selectedIncomeIds.value =
        checked !== false
            ? new Set(props.transactions.incomes.map((t) => t.id))
            : new Set();
}

function selectionState(
    transactions: Transaction[],
    selectedIds: Set<number>,
): boolean | 'indeterminate' {
    if (transactions.length === 0) {
        return false;
    }

    const selectedVisibleCount = transactions.filter((transaction) =>
        selectedIds.has(transaction.id),
    ).length;

    if (selectedVisibleCount === 0) {
        return false;
    }

    return selectedVisibleCount === transactions.length
        ? true
        : 'indeterminate';
}
function clearSelection(): void {
    selectedCostIds.value = new Set();
    selectedIncomeIds.value = new Set();
    bulkCategoryId.value = '';
}

function requestBulkDelete(): void {
    if (!hasGroupSelection.value) {
        return;
    }

    isBulkDeleteDialogOpen.value = true;
}

function confirmBulkDelete(): void {
    const ids = [...selectedCostIds.value, ...selectedIncomeIds.value];
    router.delete('/transactions/bulk', {
        data: { ids },
        preserveScroll: true,
        onSuccess: () => {
            clearSelection();
            isBulkDeleteDialogOpen.value = false;
        },
    });
}
function bulkAssignCategory(): void {
    const type = selectionType.value;

    if (!type || !bulkCategoryId.value) {
        return;
    }

    const ids =
        type === 'cost'
            ? [...selectedCostIds.value]
            : [...selectedIncomeIds.value];
    router.patch(
        '/transactions/bulk/category',
        { ids, type, category_id: Number(bulkCategoryId.value) },
        { preserveScroll: true, onSuccess: () => clearSelection() },
    );
}

const importForm = useForm<{
    file: File | null;
}>({
    file: null,
});

const importPrompt = `Convert the attached bank statement/report into a CSV for my finance app.

The bank report may be Persian or English. Analyze Persian descriptions, Persian dates, Persian digits, deposits, withdrawals, and Rial/Toman amounts correctly.

Create a downloadable CSV file named transactions.csv as the final result. Do not return explanations, markdown, code fences, totals, or extra columns. The file content must be raw CSV only.

Required English header:
occurred_at,type,category,amount,currency,title,description

Rules:
- occurred_at may be converted to Gregorian YYYY-MM-DD if possible. If the report uses Jalali dates, convert them to Gregorian.
- type must be cost for money leaving the account and income for money entering the account.
- category must be one of: Food, Transport, Housing, Health, Shopping, Bills, Other, Salary, Freelance, Gift, Investment.
- amount must be positive, with no thousands separators.
- If the report amount is in Rial, convert it to Toman by dividing by 10 and set currency to toman.
- currency must be one of: toman, usd, eur.
- title can be Persian or English, but keep it short and human-readable.
- description can include the original bank description.
- Ignore balance-only rows, headers, footers, failed transactions, and duplicate summary lines.`;

const previewRows = computed(() => importPreview.value?.rows ?? []);
const filterCategories = computed(() => [
    ...props.categories.cost,
    ...props.categories.income,
]);
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

const openCreateForm = (type: TransactionType) => {
    dialogTransactionType.value = type;
    editingTransaction.value = null;
    isDialogOpen.value = true;
};

const openEditForm = (transaction: Transaction) => {
    dialogTransactionType.value = transaction.type;
    editingTransaction.value = transaction;
    isDialogOpen.value = true;
};

const resetImportDialog = () => {
    importForm.clearErrors();
    importForm.reset();
    importPreview.value = null;
    importResult.value = null;
    promptCopied.value = false;

    if (importFileInput.value) {
        importFileInput.value.value = '';
    }
};

const openImportDialog = () => {
    resetImportDialog();
    isImportDialogOpen.value = true;
};

const closeImportDialog = () => {
    isImportDialogOpen.value = false;
    resetImportDialog();
};

const handleImportDialogOpenChange = (open: boolean) => {
    if (open) {
        isImportDialogOpen.value = true;

        return;
    }

    closeImportDialog();
};

const selectImportFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    importForm.file = input.files?.[0] ?? null;
};

const submitImportPreview = () => {
    importForm.post('/transactions/imports/preview', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset('file');

            if (importFileInput.value) {
                importFileInput.value.value = '';
            }
        },
    });
};

const confirmImport = () => {
    importProcessing.value = true;

    router.post(
        '/transactions/imports',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                importProcessing.value = false;
            },
        },
    );
};

const copyImportPrompt = async () => {
    if (!navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(importPrompt);
    promptCopied.value = true;

    window.setTimeout(() => {
        promptCopied.value = false;
    }, 1800);
};

function importStatusClass(status: ImportStatus): string {
    if (status === 'valid') {
        return 'bg-[#0d2620] text-[#7ee8c4]';
    }

    if (status === 'duplicate') {
        return 'bg-[#2f2815] text-[#ffd58a]';
    }

    return 'bg-[#2f1717] text-[#ffb4b4]';
}

watch(
    () => props.selectedCurrency,
    (value) => {
        selectedCurrency.value = value;
    },
);

watch(
    () => page.props.transactionImportPreview,
    (value) => {
        if (value) {
            importPreview.value = value as ImportPreview;
            importResult.value = null;
            isImportDialogOpen.value = true;
        }
    },
    { immediate: true },
);

watch(
    () => page.props.transactionImportResult,
    (value) => {
        if (value) {
            importResult.value = value as ImportResult;
            importPreview.value = null;
            isImportDialogOpen.value = true;
        }
    },
    { immediate: true },
);

watch(selectedCurrency, (value) => {
    if (value === props.selectedCurrency) {
        return;
    }

    applyFilters(value);
});

watch(
    () => props.filters,
    (filters) => {
        filterSearch.value = filters.search;
        filterCategory.value = filters.category?.toString() ?? 'all';
        filterFrom.value = filters.from;
        filterTo.value = filters.to;
    },
    { deep: true },
);

function applyFilters(
    currency: Currency = selectedCurrency.value,
    costPage: number | null = null,
    incomePage: number | null = null,
): void {
    clearSelection();
    router.get(
        transactionsIndex.url(),
        {
            search: filterSearch.value || null,
            category:
                filterCategory.value === 'all' ? null : filterCategory.value,
            from: filterFrom.value || null,
            to: filterTo.value || null,
            currency,
            cost_page: costPage && costPage > 1 ? costPage : null,
            income_page: incomePage && incomePage > 1 ? incomePage : null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function changeCostPage(page: number): void {
    const ip = props.transactions.meta?.incomes?.current_page;
    applyFilters(selectedCurrency.value, page, ip && ip > 1 ? ip : null);
}

function changeIncomePage(page: number): void {
    const cp = props.transactions.meta?.costs?.current_page;
    applyFilters(selectedCurrency.value, cp && cp > 1 ? cp : null, page);
}

function clearFilters(): void {
    filterSearch.value = '';
    filterCategory.value = 'all';
    filterFrom.value = '';
    filterTo.value = '';
    applyFilters();
}

function formatAmount(amount: string | number): string {
    const number = Number(amount);

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: number % 1 === 0 ? 0 : 2,
    }).format(number);
}

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}
</script>
