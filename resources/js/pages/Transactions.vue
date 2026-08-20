<template>
    <Head :title="t('finance.transactions.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <!-- ── Filter bar: one dense row of pills, matching the mock exactly
             — no field labels, no explicit Apply button. ─────────────────── -->
        <div
            class="mx-[18px] mt-5 flex flex-wrap items-center gap-2.5 rounded-[16px] bg-[#1a1a1a] p-3.5 ring-1 ring-white/10"
        >
            <Input
                id="transaction_search"
                v-model="filterSearch"
                :class="[filterFieldClass, 'min-w-[220px] flex-1']"
                :placeholder="t('finance.filters.title_or_note')"
                :aria-label="t('finance.fields.search')"
                @keyup.enter="applyFilters()"
            />

            <BirthdatePicker
                v-model="filterFrom"
                name="transaction_from"
                :required="false"
                :years-back="16"
                :years-forward="1"
                :trigger-class="filterFieldClass"
            />
            <BirthdatePicker
                v-model="filterTo"
                name="transaction_to"
                :required="false"
                :years-back="16"
                :years-forward="1"
                :trigger-class="filterFieldClass"
            />

            <Select v-model="filterCategory">
                <SelectTrigger
                    id="transaction_category"
                    :class="[
                        filterFieldClass,
                        '!w-full shrink-0 sm:!w-[220px]',
                    ]"
                    :aria-label="t('finance.fields.category')"
                >
                    <SelectValue
                        :placeholder="t('finance.filters.all_categories')"
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        t('finance.filters.all_categories')
                    }}</SelectItem>
                    <!-- Where the logbook's uncategorised count links to;
                         without the option the filter would be active but
                         invisible in the control. -->
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
            class="grid items-start gap-[18px] px-[18px] py-[18px] xl:grid-cols-2"
        >
            <!-- ── Money going out ──────────────────────────────────────── -->
            <section
                class="overflow-x-auto rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-3.5"
                >
                    <div class="min-w-0">
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="size-[7px] shrink-0 rounded-[2px] bg-[#6C4EE9]"
                            />
                            <p
                                class="text-[11px] font-medium tracking-[0.13em] text-[#947BFF] uppercase"
                            >
                                {{ t('finance.tables.money_going_out') }}
                            </p>
                        </div>
                        <p
                            class="text-[25px] leading-none font-semibold text-white"
                        >
                            <CompactMoney
                                v-if="summaryCost !== null"
                                :value="summaryCost"
                                :currency="selectedCurrency"
                            />
                            <span v-else class="text-base text-[#686868]"
                                >—</span
                            >
                        </p>
                        <p class="mt-1.5 text-[11.5px] text-[#686868]">
                            {{ costTotal }}
                            {{ t('finance.reports.transactions') }} ·
                            {{ selectedCurrencyLabel }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 cursor-pointer rounded-[10px] bg-[#6C4EE9] px-3.5 py-2 text-[13px] font-medium text-white transition hover:brightness-110"
                        @click="openCreateForm('cost')"
                    >
                        + {{ t('finance.form.add_cost') }}
                    </button>
                </div>

                <div
                    class="grid grid-cols-[minmax(120px,1fr)_112px_92px_118px_68px] items-center gap-3 pb-2.5 text-[10px] font-medium tracking-[0.1em] text-[#686868] uppercase"
                >
                    <div>{{ t('finance.fields.subject') }}</div>
                    <div>{{ t('finance.fields.category') }}</div>
                    <div>{{ t('finance.fields.date') }}</div>
                    <div class="text-end">
                        {{ t('finance.fields.amount') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                    <div class="sr-only">{{ t('common.actions') }}</div>
                </div>

                <div
                    v-for="transaction in props.transactions.costs"
                    :key="transaction.id"
                    class="group grid grid-cols-[minmax(120px,1fr)_112px_92px_118px_68px] items-center gap-3 border-t border-white/[0.06] py-2.5 transition-colors hover:bg-white/[0.02]"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm text-white">
                            <Ciphered
                                :value="transaction.title"
                                table="transactions"
                            />
                        </p>
                        <p
                            v-if="transaction.description"
                            class="mt-0.5 truncate text-[11px] text-[#686868]"
                        >
                            <Ciphered
                                :value="transaction.description"
                                table="transactions"
                            />
                        </p>
                    </div>
                    <CategoryChip
                        :name="categoryName(transaction)"
                        :color="categoryColor(transaction)"
                    />
                    <span dir="ltr" class="text-xs text-[#686868] tabular-nums">
                        {{ displayDate(transaction.occurred_at) }}
                    </span>
                    <span
                        class="text-end text-[14.5px] text-[#947BFF] tabular-nums"
                        :class="maskClass"
                        dir="ltr"
                    >
                        <CipheredMoney
                            :amount="transaction.amount"
                            :display-amount="transaction.display_amount"
                            :currency="transaction.currency"
                            :display-currency="transaction.display_currency"
                            :rates="props.rates"
                        />
                    </span>
                    <div
                        class="flex items-center justify-end gap-1 opacity-100 transition-opacity md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                    >
                        <button
                            type="button"
                            :aria-label="t('common.edit')"
                            :title="t('common.edit')"
                            class="grid size-8 cursor-pointer place-items-center rounded-md text-[#947BFF] transition-colors hover:bg-[#947BFF]/10 focus-visible:ring-2 focus-visible:ring-[#947BFF] focus-visible:outline-none"
                            @click="openEditForm(transaction)"
                        >
                            <Pencil class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :aria-label="t('common.delete')"
                            :title="t('common.delete')"
                            class="grid size-8 cursor-pointer place-items-center rounded-md text-[#E94E50] transition-colors hover:bg-[#E94E50]/10 focus-visible:ring-2 focus-visible:ring-[#E94E50] focus-visible:outline-none"
                            @click="void requestDelete(transaction)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <p
                    v-if="props.transactions.costs.length === 0"
                    class="py-10 text-center text-sm text-[#989898]"
                >
                    {{ t('finance.dashboard.no_costs') }}
                </p>

                <!-- Cost table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.costs &&
                        props.transactions.meta.costs.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 py-4 text-xs text-[#686868]"
                >
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.costs.current_page <= 1
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page - 1,
                            )
                        "
                    >
                        ‹
                    </button>
                    <span>
                        {{ props.transactions.meta.costs.current_page }} /
                        {{ props.transactions.meta.costs.last_page }} ·
                        {{ props.transactions.meta.costs.total }}
                        {{ t('finance.reports.transactions') }}
                    </span>
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.costs.current_page >=
                            props.transactions.meta.costs.last_page
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page + 1,
                            )
                        "
                    >
                        ›
                    </button>
                </div>
            </section>

            <!-- ── Money coming in ──────────────────────────────────────── -->
            <section
                class="overflow-x-auto rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-3.5"
                >
                    <div class="min-w-0">
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="size-[7px] shrink-0 rounded-[2px] bg-[#02CD86]"
                            />
                            <p
                                class="text-[11px] font-medium tracking-[0.13em] text-[#02CD86] uppercase"
                            >
                                {{ t('finance.tables.money_coming_in') }}
                            </p>
                        </div>
                        <p
                            class="text-[25px] leading-none font-semibold text-white"
                        >
                            <CompactMoney
                                v-if="summaryIncome !== null"
                                :value="summaryIncome"
                                :currency="selectedCurrency"
                            />
                            <span v-else class="text-base text-[#686868]"
                                >—</span
                            >
                        </p>
                        <p class="mt-1.5 text-[11.5px] text-[#686868]">
                            {{ incomeTotal }}
                            {{ t('finance.reports.transactions') }} ·
                            {{ selectedCurrencyLabel }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 cursor-pointer rounded-[10px] bg-[#02CD86] px-3.5 py-2 text-[13px] font-medium text-[#101010] transition hover:brightness-110"
                        @click="openCreateForm('income')"
                    >
                        + {{ t('finance.form.add_income') }}
                    </button>
                </div>

                <div
                    class="grid grid-cols-[minmax(120px,1fr)_112px_92px_118px_68px] items-center gap-3 pb-2.5 text-[10px] font-medium tracking-[0.1em] text-[#686868] uppercase"
                >
                    <div>{{ t('finance.fields.subject') }}</div>
                    <div>{{ t('finance.fields.category') }}</div>
                    <div>{{ t('finance.fields.date') }}</div>
                    <div class="text-end">
                        {{ t('finance.fields.amount') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                    <div class="sr-only">{{ t('common.actions') }}</div>
                </div>

                <div
                    v-for="transaction in props.transactions.incomes"
                    :key="transaction.id"
                    class="group grid grid-cols-[minmax(120px,1fr)_112px_92px_118px_68px] items-center gap-3 border-t border-white/[0.06] py-2.5 transition-colors hover:bg-white/[0.02]"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm text-white">
                            <Ciphered
                                :value="transaction.title"
                                table="transactions"
                            />
                        </p>
                        <p
                            v-if="transaction.description"
                            class="mt-0.5 truncate text-[11px] text-[#686868]"
                        >
                            <Ciphered
                                :value="transaction.description"
                                table="transactions"
                            />
                        </p>
                    </div>
                    <CategoryChip
                        :name="categoryName(transaction)"
                        :color="categoryColor(transaction)"
                    />
                    <span dir="ltr" class="text-xs text-[#686868] tabular-nums">
                        {{ displayDate(transaction.occurred_at) }}
                    </span>
                    <span
                        class="text-end text-[14.5px] text-[#02CD86] tabular-nums"
                        :class="maskClass"
                        dir="ltr"
                    >
                        <CipheredMoney
                            :amount="transaction.amount"
                            :display-amount="transaction.display_amount"
                            :currency="transaction.currency"
                            :display-currency="transaction.display_currency"
                            :rates="props.rates"
                        />
                    </span>
                    <div
                        class="flex items-center justify-end gap-1 opacity-100 transition-opacity md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                    >
                        <button
                            type="button"
                            :aria-label="t('common.edit')"
                            :title="t('common.edit')"
                            class="grid size-8 cursor-pointer place-items-center rounded-md text-[#02CD86] transition-colors hover:bg-[#02CD86]/10 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:outline-none"
                            @click="openEditForm(transaction)"
                        >
                            <Pencil class="size-3.5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :aria-label="t('common.delete')"
                            :title="t('common.delete')"
                            class="grid size-8 cursor-pointer place-items-center rounded-md text-[#E94E50] transition-colors hover:bg-[#E94E50]/10 focus-visible:ring-2 focus-visible:ring-[#E94E50] focus-visible:outline-none"
                            @click="void requestDelete(transaction)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <p
                    v-if="props.transactions.incomes.length === 0"
                    class="py-10 text-center text-sm text-[#989898]"
                >
                    {{ t('finance.dashboard.no_incomes') }}
                </p>

                <!-- Income table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.incomes &&
                        props.transactions.meta.incomes.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 py-4 text-xs text-[#686868]"
                >
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.incomes.current_page <= 1
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page -
                                    1,
                            )
                        "
                    >
                        ‹
                    </button>
                    <span>
                        {{ props.transactions.meta.incomes.current_page }} /
                        {{ props.transactions.meta.incomes.last_page }} ·
                        {{ props.transactions.meta.incomes.total }}
                        {{ t('finance.reports.transactions') }}
                    </span>
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.incomes.current_page >=
                            props.transactions.meta.incomes.last_page
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page +
                                    1,
                            )
                        "
                    >
                        ›
                    </button>
                </div>
            </section>
        </div>

        <!-- ── Import / export — a new bar below the tables, matching the
             mock's Report-screen footer treatment. ────────────────────── -->
        <div
            class="mx-[18px] mb-[38px] flex flex-wrap items-center justify-between gap-4 rounded-[16px] bg-[#1a1a1a] px-[22px] py-[18px] ring-1 ring-white/10"
        >
            <p class="text-[13.5px] text-[#989898]">
                {{ t('finance.import.footer_hint') }}
            </p>
            <div class="flex shrink-0 gap-2">
                <button
                    type="button"
                    class="cursor-pointer rounded-[10px] bg-[#252525] px-[15px] py-2 text-[13.5px] text-white ring-1 ring-white/[0.14] transition-colors hover:bg-[#2e2e2e]"
                    @click="openImportDialog()"
                >
                    {{ t('finance.import.action') }}
                </button>
            </div>
        </div>

        <TransactionDialog
            v-model:open="isDialogOpen"
            :type="dialogTransactionType"
            :transaction="editingTransaction"
            :categories="props.categories"
            :currencies="props.currencies"
        />

        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                t('finance.delete.transaction_title', {
                    title: deleteTargetTitle,
                })
            "
            :description="t('finance.delete.transaction_description')"
            :processing="deleteForm.processing"
            @update:open="(open) => !open && (deleteTarget = null)"
            @confirm="confirmDelete"
        />

        <!-- ── Import ─────────────────────────────────────────────── -->
        <Dialog
            :open="isImportDialogOpen"
            @update:open="handleImportDialogOpenChange"
        >
            <DialogContent
                class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[16px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-w-[980px]"
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
                                                ? formatCurrencyDisplay(
                                                      row.data.amount,
                                                      row.data.currency,
                                                  )
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
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import CompactMoney from '@/components/CompactMoney.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import CategoryChip from '@/components/transactions/CategoryChip.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
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
import { useAmountMask } from '@/composables/useAmountMask';
import { useDisplayAmounts } from '@/composables/useDisplayAmounts';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import { useVault } from '@/composables/useVault';
import { formatAppDate } from '@/lib/date';
import {
    currencySymbol,
    formatCurrencyDisplay,
    formatCurrencyNumber,
} from '@/lib/money';
import type { Rates } from '@/lib/money';
import { dashboard } from '@/routes';
import {
    destroy as destroyTransaction,
    index as transactionsIndex,
} from '@/routes/transactions';
import type { Encrypted } from '@/types/vault';

type TransactionType = 'cost' | 'income';
type FilterType = TransactionType | 'all';
type Currency = 'toman' | 'usd' | 'eur';

type Category = {
    id: number;
    name: string;
    slug: string;
    type: TransactionType;
    color: string | null;
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
const dialogTransactionType = ref<TransactionType>('cost');
const editingTransaction = ref<Transaction | null>(null);
const page = usePage();
const { t } = useI18n();
const { revealAsync } = useVault();
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

const selectedCurrency = ref<Currency>(props.selectedCurrency);
const selectedCurrencyLabel = computed(
    () =>
        props.currencies.find(
            (currency) => currency.value === selectedCurrency.value,
        )?.label ?? t(`finance.currencies.${selectedCurrency.value}`),
);
const selectedCurrencySymbol = computed(() =>
    currencySymbol(selectedCurrency.value),
);
const filterSearch = ref(props.filters.search);
const filterCategory = ref(props.filters.category?.toString() ?? 'all');
const filterFrom = ref(props.filters.from);
const filterTo = ref(props.filters.to);
const filterFieldClass =
    'h-9 w-full rounded-[10px] !border-white/[0.08] !bg-[#252525] px-3 text-[13px] font-normal !text-[#e5e5e5] shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

const filterCategories = computed(() => [
    ...props.categories.cost,
    ...props.categories.income,
]);

// The mock's per-row category tag colours text on a fixed dark chip using
// each category's own colour — never a generic badge. Falls back to the
// prop lookup for rows whose relation wasn't eager-loaded.
function findCategory(transaction: Transaction): Category | null {
    if (transaction.category) {
        return transaction.category;
    }

    return (
        (props.categories[transaction.type] ?? []).find(
            (category) => category.id === transaction.category_id,
        ) ?? null
    );
}

function categoryName(transaction: Transaction): string {
    return (
        findCategory(transaction)?.name ?? t('finance.categories.uncategorized')
    );
}

function categoryColor(transaction: Transaction): string | null {
    return findCategory(transaction)?.color ?? null;
}

const costTotal = computed(
    () =>
        props.transactions.meta?.costs?.total ??
        props.transactions.costs.length,
);
const incomeTotal = computed(
    () =>
        props.transactions.meta?.incomes?.total ??
        props.transactions.incomes.length,
);

// The server's `summary` is the true filtered total (every page, not just the
// one shown) and is used whenever it could read the amounts. Under the vault
// it can't, so `summary` arrives null — this page's own rows are decrypted
// here instead, but only stand in for the true total when there is exactly
// one page, since a page total presented as the grand total would be wrong
// the moment there is a second page.
const { totalOf } = useDisplayAmounts(
    () => [...props.transactions.costs, ...props.transactions.incomes],
    () => selectedCurrency.value,
    () => props.rates,
);

const costIsSinglePage = computed(
    () => (props.transactions.meta?.costs?.last_page ?? 1) <= 1,
);
const incomeIsSinglePage = computed(
    () => (props.transactions.meta?.incomes?.last_page ?? 1) <= 1,
);

const summaryCost = computed<string | null>(() => {
    if (props.summary) {
        return props.summary.cost;
    }

    if (!costIsSinglePage.value) {
        return null;
    }

    const total = totalOf(props.transactions.costs);

    return total === null ? null : formatAmount(total);
});

const summaryIncome = computed<string | null>(() => {
    if (props.summary) {
        return props.summary.income;
    }

    if (!incomeIsSinglePage.value) {
        return null;
    }

    const total = totalOf(props.transactions.incomes);

    return total === null ? null : formatAmount(total);
});

function formatAmount(amount: string | number): string {
    return formatCurrencyNumber(amount, selectedCurrency.value);
}

usePageSubtitle(() =>
    t('finance.transactions.subtitle', {
        count: props.transactions.meta
            ? (props.transactions.meta.costs?.total ?? 0) +
              (props.transactions.meta.incomes?.total ?? 0)
            : (props.summary?.count ?? 0),
    }),
);

const openCreateForm = (type: TransactionType) => {
    dialogTransactionType.value = type;
    editingTransaction.value = null;
    isDialogOpen.value = true;
};

function openEditForm(transaction: Transaction): void {
    dialogTransactionType.value = transaction.type;
    editingTransaction.value = transaction;
    isDialogOpen.value = true;
}

const deleteTarget = ref<Transaction | null>(null);
const deleteTargetTitle = ref('');
const deleteForm = useForm({});

async function requestDelete(transaction: Transaction): Promise<void> {
    deleteTarget.value = transaction;
    deleteTargetTitle.value =
        (await revealAsync<string>(
            transaction.title,
            'transactions',
            'string',
        )) ?? '';
}

function confirmDelete(): void {
    if (!deleteTarget.value) {
        return;
    }

    deleteForm.delete(destroyTransaction.url(deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
            deleteTargetTitle.value = '';
        },
    });
}

watch(
    () => props.selectedCurrency,
    (value) => {
        selectedCurrency.value = value;
    },
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

// No explicit "Filter" button in the design — category and date changes
// apply immediately; the search field applies on Enter.
watch([filterCategory, filterFrom, filterTo], () => applyFilters());

function applyFilters(
    currency: Currency = selectedCurrency.value,
    costPage: number | null = null,
    incomePage: number | null = null,
): void {
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

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}

// ── Import ─────────────────────────────────────────────────────────────
const isImportDialogOpen = ref(false);
const importFileInput = ref<HTMLInputElement | null>(null);
const importPreview = ref<ImportPreview | null>(null);
const importResult = ref<ImportResult | null>(null);
const importProcessing = ref(false);
const promptCopied = ref(false);

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

function resetImportDialog(): void {
    importForm.clearErrors();
    importForm.reset();
    importPreview.value = null;
    importResult.value = null;
    promptCopied.value = false;

    if (importFileInput.value) {
        importFileInput.value.value = '';
    }
}

function openImportDialog(): void {
    resetImportDialog();
    isImportDialogOpen.value = true;
}

function closeImportDialog(): void {
    isImportDialogOpen.value = false;
    resetImportDialog();
}

function handleImportDialogOpenChange(open: boolean): void {
    if (open) {
        isImportDialogOpen.value = true;

        return;
    }

    closeImportDialog();
}

function selectImportFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    importForm.file = input.files?.[0] ?? null;
}

function submitImportPreview(): void {
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
}

function confirmImport(): void {
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
}

async function copyImportPrompt(): Promise<void> {
    if (!navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(importPrompt);
    promptCopied.value = true;

    window.setTimeout(() => {
        promptCopied.value = false;
    }, 1800);
}

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
</script>
