<template>
    <Head :title="t('finance.transactions.title')" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#111111]"
    >
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.9fr_0.8fr_0.8fr_auto]"
            >
                <div class="grid gap-2">
                    <Label for="transaction_search">{{
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

                <div class="grid gap-2">
                    <Label for="transaction_type">{{
                        t('finance.fields.type')
                    }}</Label>
                    <Select
                        v-model="filterType"
                        @update:model-value="applyTypeFilter"
                    >
                        <SelectTrigger
                            id="transaction_type"
                            :class="filterFieldClass"
                        >
                            <SelectValue
                                :placeholder="t('finance.filters.all_types')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{
                                t('finance.filters.all_types')
                            }}</SelectItem>
                            <SelectItem value="cost">{{
                                t('finance.filters.costs')
                            }}</SelectItem>
                            <SelectItem value="income">{{
                                t('finance.filters.incomes')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label for="transaction_category">{{
                        t('finance.fields.category')
                    }}</Label>
                    <Select
                        v-model="filterCategory"
                        @update:model-value="applyFilters()"
                    >
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

                <div class="grid gap-2">
                    <Label for="transaction_from">{{
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

                <div class="grid gap-2">
                    <Label for="transaction_to">{{
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

                <div class="flex items-end gap-2">
                    <Button
                        class="rounded-full bg-white/10 px-5 text-white shadow-none ring-1 ring-white/20 hover:bg-white/15"
                        @click="openImportDialog"
                    >
                        <Upload class="size-4" />
                        {{ t('finance.actions.import_transactions') }}
                    </Button>
                    <a
                        :href="`/transactions/export?currency=${selectedCurrency}`"
                        class="inline-flex items-center gap-2 rounded-full bg-white/10 px-5 py-2 text-sm text-white ring-1 ring-white/20 transition-colors hover:bg-white/15"
                    >
                        <Download class="size-4" />
                        Export
                    </a>
                    <Button
                        class="rounded-full bg-white/10 px-5 text-white shadow-none ring-1 ring-white/20 hover:bg-white/15"
                        @click="applyFilters()"
                    >
                        <Search class="size-4" />
                        {{ t('finance.actions.filter') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="size-10 rounded-full bg-white/10 p-0 text-[#989898] ring-1 ring-white/15 hover:bg-white/15"
                        @click="clearFilters"
                    >
                        <RotateCcw class="size-4" />
                        <span class="sr-only">{{
                            t('finance.actions.reset_filters')
                        }}</span>
                    </Button>
                </div>
            </div>
        </section>

        <div
            class="grid items-start gap-[18px] px-[18px] py-[38px] lg:grid-cols-2"
        >
            <section
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <h2
                            class="text-[22px] leading-none font-normal text-white"
                        >
                            {{ t('finance.tables.money_going_out') }}
                        </h2>
                    </div>
                    <Button
                        class="h-14 w-max justify-between rounded-md bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-3.5 text-[22px] leading-none font-bold text-white shadow-[0_10px_20px_rgba(108,78,233,0.22)] transition hover:brightness-105"
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
                            <Plus class="size-6" />
                        </span>
                    </Button>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base">
                                <th
                                    class="rounded-l-2xl bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#24212f] px-3 py-4 sm:px-5"
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
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    <button
                                        class="cursor-pointer text-white hover:text-[#947BFF]"
                                        type="button"
                                        @click="openEditForm(transaction)"
                                    >
                                        {{ transaction.title }}
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-white/10 px-4 py-2 text-[17px] leading-none font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    {{
                                        formatAmount(transaction.display_amount)
                                    }}
                                </td>
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
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
                            <tr v-if="props.transactions.costs.length === 0">
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_costs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <h2
                            class="text-[22px] leading-none font-normal text-white"
                        >
                            {{ t('finance.tables.money_coming_in') }}
                        </h2>
                    </div>
                    <Button
                        class="h-14 w-max justify-between rounded-md bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-3.5 text-[22px] leading-none font-bold text-white shadow-[0_10px_20px_rgba(2,205,134,0.22)] transition hover:brightness-105"
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
                            <Plus class="size-6" />
                        </span>
                    </Button>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base">
                                <th
                                    class="rounded-l-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#0d2620] px-3 py-4 sm:px-5"
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
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    <button
                                        class="cursor-pointer text-white hover:text-[#02CD86]"
                                        type="button"
                                        @click="openEditForm(transaction)"
                                    >
                                        {{ transaction.title }}
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-white/10 px-4 py-2 text-[17px] leading-none font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    {{
                                        formatAmount(transaction.display_amount)
                                    }}
                                </td>
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
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
                            <tr v-if="props.transactions.incomes.length === 0">
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center text-[#989898]"
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
                            <CategoryCreator
                                :type="form.type"
                                :field-class="fieldControlClass"
                                @created="form.category_id = $event"
                            />

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
                            :class="confirmButtonClass"
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

        <Dialog v-model:open="isImportDialogOpen">
            <DialogContent
                class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[25px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-w-[980px]"
            >
                <div class="space-y-6 px-6 py-8">
                    <DialogHeader class="space-y-2 text-left">
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
                                    <th class="px-3 py-3 text-left">
                                        {{ t('finance.import.row') }}
                                    </th>
                                    <th class="px-3 py-3 text-left">
                                        {{ t('finance.import.status') }}
                                    </th>
                                    <th class="px-3 py-3 text-left">
                                        {{ t('finance.fields.date') }}
                                    </th>
                                    <th class="px-3 py-3 text-left">
                                        {{ t('finance.fields.type') }}
                                    </th>
                                    <th class="px-3 py-3 text-left">
                                        {{ t('finance.fields.category') }}
                                    </th>
                                    <th class="px-3 py-3 text-left">
                                        {{ t('finance.fields.amount') }}
                                    </th>
                                    <th class="px-3 py-3 text-left">
                                        {{ t('finance.fields.subject') }}
                                    </th>
                                    <th class="px-3 py-3 text-left">
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
                            @click="isImportDialogOpen = false"
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
import CategoryCreator from '@/components/CategoryCreator.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatAppDate } from '@/lib/date';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';

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
        category: number | null;
        from: string;
        to: string;
    };
    transactions: {
        costs: Transaction[];
        incomes: Transaction[];
    };
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    summary: {
        cost: string;
        income: string;
        count: number;
    };
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
const editingTransactionId = ref<number | null>(null);
const deleteTarget = ref<Transaction | null>(null);
const page = usePage();
const { t } = useI18n();
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

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
const selectedCurrency = ref<Currency>(props.selectedCurrency);
const importFileInput = ref<HTMLInputElement | null>(null);
const importPreview = ref<ImportPreview | null>(null);
const importResult = ref<ImportResult | null>(null);
const importProcessing = ref(false);
const promptCopied = ref(false);
const filterSearch = ref(props.filters.search);
const filterType = ref<FilterType>(props.filters.type);
const filterCategory = ref(props.filters.category?.toString() ?? 'all');
const filterFrom = ref(props.filters.from);
const filterTo = ref(props.filters.to);
const filterFieldClass =
    'h-9 w-full rounded-md !border-white/10 !bg-[#252525] px-3 text-sm font-normal !text-white shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

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

const selectedCategories = computed(() => props.categories[form.type] ?? []);
const previewRows = computed(() => importPreview.value?.rows ?? []);
const filterCategories = computed(() => {
    if (filterType.value === 'cost' || filterType.value === 'income') {
        return props.categories[filterType.value] ?? [];
    }

    return [...props.categories.cost, ...props.categories.income];
});
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
const confirmButtonClass = computed(() =>
    form.type === 'cost'
        ? 'h-11 flex-1 rounded-[8px] bg-[#6C4EE9] px-[10px] py-[3px] text-base font-semibold text-white shadow-none hover:bg-[#7D61F0] disabled:opacity-50 sm:h-9 sm:w-[135px] sm:flex-none sm:text-[20px]'
        : 'h-11 flex-1 rounded-[8px] bg-[#02CD86] px-[10px] py-[3px] text-base font-semibold text-[#101010] shadow-none hover:bg-[#08dd93] disabled:opacity-50 sm:h-9 sm:w-[135px] sm:flex-none sm:text-[20px]',
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
    const categories = props.categories[type] ?? [];

    form.clearErrors();
    form.reset();
    form.type = type;
    form.category_id = categories[0]?.id.toString() ?? '';
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

const openImportDialog = () => {
    isImportDialogOpen.value = true;
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

const submitTransaction = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false;
            editingTransactionId.value = null;
            resetForm(form.type);
        },
    };

    if (editingTransactionId.value) {
        form.patch(`/transactions/${editingTransactionId.value}`, options);

        return;
    }

    form.post('/transactions', options);
};

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
        filterType.value = filters.type;
        filterCategory.value = filters.category?.toString() ?? 'all';
        filterFrom.value = filters.from;
        filterTo.value = filters.to;
    },
    { deep: true },
);

watch(filterType, () => {
    if (
        filterCategory.value !== 'all' &&
        !filterCategories.value.some(
            (category) => category.id.toString() === filterCategory.value,
        )
    ) {
        filterCategory.value = 'all';
    }
});

function applyFilters(currency: Currency = selectedCurrency.value): void {
    router.get(
        transactionsIndex.url(),
        {
            search: filterSearch.value || null,
            type: filterType.value === 'all' ? null : filterType.value,
            category:
                filterCategory.value === 'all' ? null : filterCategory.value,
            from: filterFrom.value || null,
            to: filterTo.value || null,
            currency,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function applyTypeFilter(): void {
    if (
        filterCategory.value !== 'all' &&
        !filterCategories.value.some(
            (category) => category.id.toString() === filterCategory.value,
        )
    ) {
        filterCategory.value = 'all';
    }

    applyFilters();
}

function clearFilters(): void {
    filterSearch.value = '';
    filterType.value = 'all';
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
