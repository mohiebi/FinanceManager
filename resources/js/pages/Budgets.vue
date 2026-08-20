<template>
    <Head
        :title="
            navigationName('navigation.budgets', 'navigation.budgets_subtitle')
        "
    />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Edit trigger — the mock's own screen has no header of its
             own (the shell already owns the page title), but budgets have
             no other page to manage them from, so this stays. The empty
             state below carries its own "create" call to action. ──────── -->
        <div
            v-if="props.budget !== null"
            class="mx-[18px] mt-5 flex justify-end"
        >
            <Button
                class="h-11 w-max shrink-0 rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010] shadow-[0_10px_20px_rgba(2,205,134,0.22)] hover:brightness-105"
                @click="openDialog()"
            >
                <Pencil class="size-4" />
                {{ t('budgets.edit') }}
            </Button>
        </div>

        <!-- ── Empty state ────────────────────────────────────────── -->
        <div
            v-if="props.budget === null"
            class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
        >
            <Target class="size-10 text-[#6C4EE9]" />
            <h2 class="mt-4 text-lg font-semibold text-white">
                {{ t('budgets.empty_title') }}
            </h2>
            <p class="mt-2 max-w-md text-center text-sm text-[#989898]">
                {{ t('budgets.empty_body') }}
            </p>
            <Button
                class="mt-6 h-11 rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010]"
                @click="openDialog()"
            >
                {{ t('budgets.empty_action') }}
            </Button>
        </div>

        <!-- ── Skeleton while the browser opens the amounts ────────── -->
        <div
            v-else-if="plan === null"
            class="mx-[18px] my-[18px] flex items-center justify-center gap-2 rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
        >
            <Spinner class="size-5 text-[#989898]" />
            <span class="text-sm text-[#989898]">{{
                t('budgets.calculating')
            }}</span>
        </div>

        <template v-else>
            <!-- ── Over-allocation warning ────────────────────────── -->
            <p
                v-if="plan.over_allocated > 0"
                class="mx-[18px] mt-[18px] rounded-[14px] bg-[#E94E50]/10 px-4 py-3 text-sm text-[#E94E50] ring-1 ring-[#E94E50]/25"
            >
                {{
                    t('budgets.over_allocated', {
                        amount: `${formatAmount(plan.over_allocated)} ${currencyLabel}`,
                    })
                }}
                <span class="text-[#E94E50]/70">{{
                    t('budgets.over_allocated_hint')
                }}</span>
            </p>

            <!-- ── One or more lines already over their own limit ──── -->
            <div
                v-if="overspentLines.length > 0"
                class="mx-[18px] mt-[18px] flex flex-wrap items-center justify-between gap-4 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-[#E94E50]/28"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#2e0d0d]"
                    >
                        <AlertTriangle class="size-[18px] text-[#E94E50]" />
                    </span>
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-[#E94E50] uppercase"
                        >
                            {{ overspentLinesTitle }}
                        </p>
                        <p class="mt-1 text-sm text-[#989898]">
                            {{ overspentLinesBody }}
                        </p>
                    </div>
                </div>
                <Button
                    class="shrink-0 rounded-full bg-[#E94E50] px-5 text-white hover:brightness-110"
                    @click="openDialog()"
                >
                    {{ t('budgets.edit_limits') }}
                </Button>
            </div>

            <!-- ── Lines ──────────────────────────────────────────── -->
            <section
                class="mx-[18px] my-[18px] rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
            >
                <h2 class="text-sm font-semibold text-white">
                    {{ t('budgets.lines') }}
                </h2>

                <ul class="mt-4 divide-y divide-white/5">
                    <li
                        v-for="line in plan.lines"
                        :key="line.id"
                        class="py-4 first:pt-0 last:pb-0"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    class="truncate text-sm font-medium text-white"
                                >
                                    {{ lineLabel(line) }}
                                </span>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] whitespace-nowrap"
                                    :class="ruleBadgeClass(line.rule_type)"
                                >
                                    {{ ruleLabel(line) }}
                                </span>
                            </div>

                            <p class="text-sm font-semibold text-white">
                                <span :class="maskClass">{{
                                    formatAmount(line.allocated)
                                }}</span>
                                <span class="text-xs font-normal text-[#989898]"
                                    >{{ currencyLabel }}
                                </span>
                            </p>
                        </div>

                        <div
                            class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/8"
                        >
                            <div
                                class="h-full rounded-full transition-[width]"
                                :class="
                                    line.over ? 'bg-[#E94E50]' : 'bg-[#02CD86]'
                                "
                                :style="{ width: `${barWidth(line)}%` }"
                            />
                        </div>

                        <div
                            class="mt-1.5 flex flex-wrap justify-between gap-x-4 text-xs"
                        >
                            <span class="text-[#989898]">
                                {{
                                    t('budgets.spent_inline', {
                                        amount: formatAmount(line.actual),
                                    })
                                }}
                            </span>
                            <span
                                :class="
                                    line.over
                                        ? 'text-[#E94E50]'
                                        : 'text-[#02CD86]'
                                "
                            >
                                {{ remainingLabel(line) }}
                            </span>
                        </div>
                    </li>
                </ul>
            </section>
        </template>

        <!-- ── Editor ─────────────────────────────────────────────── -->
        <Dialog :open="isDialogOpen" @update:open="handleDialogOpenChange">
            <DialogContent
                class="max-h-[90vh] overflow-y-auto border-white/10 bg-[#1a1a1a] text-white sm:max-w-3xl"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        props.budget === null
                            ? t('budgets.create')
                            : t('budgets.edit')
                    }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitPlan()">
                    <div class="space-y-1.5">
                        <Label for="plan-title">{{
                            t('budgets.plan_title')
                        }}</Label>
                        <Input
                            id="plan-title"
                            v-model="form.title"
                            :placeholder="t('budgets.plan_title_placeholder')"
                            :class="fieldClass"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-1.5">
                        <Label>{{ t('budgets.income_basis.label') }}</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="basis in incomeBases"
                                :key="basis"
                                type="button"
                                class="rounded-[14px] border p-3 text-start transition-colors"
                                :class="
                                    form.income_basis === basis
                                        ? 'border-[#6C4EE9] bg-[#6C4EE9]/10'
                                        : 'border-white/10 bg-white/5 hover:border-white/20'
                                "
                                @click="form.income_basis = basis"
                            >
                                <span class="text-sm font-medium text-white">{{
                                    t(`budgets.income_basis.${basis}`)
                                }}</span>
                                <span
                                    class="mt-1 block text-xs text-[#989898]"
                                    >{{
                                        t(`budgets.income_basis.${basis}_hint`)
                                    }}</span
                                >
                            </button>
                        </div>
                        <InputError :message="form.errors.income_basis" />
                    </div>

                    <div
                        v-if="form.income_basis === 'expected'"
                        class="space-y-1.5"
                    >
                        <Label for="expected-income">{{
                            t('budgets.expected_income')
                        }}</Label>
                        <Input
                            id="expected-income"
                            v-model="form.expected_income"
                            inputmode="numeric"
                            :class="fieldClass"
                        />
                        <InputError :message="form.errors.expected_income" />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>{{ t('budgets.lines') }}</Label>
                            <Button
                                type="button"
                                variant="ghost"
                                class="h-8 rounded-full px-3 text-xs text-[#02CD86] hover:bg-[#02CD86]/10"
                                @click="addLine()"
                            >
                                <Plus class="size-3.5" />
                                {{ t('budgets.add_line') }}
                            </Button>
                        </div>

                        <div
                            v-for="(line, index) in form.lines"
                            :key="index"
                            class="rounded-[14px] border border-white/10 bg-white/5 p-3"
                        >
                            <!-- Nowrap once there is room for it: a fixed line
                                 carries four controls plus the delete button,
                                 and wrapping stranded the button on its own row.
                                 min-w-0 lets the fields shrink instead. -->
                            <div
                                class="flex flex-wrap items-end gap-2 sm:flex-nowrap"
                            >
                                <div class="min-w-0 flex-1 basis-32 space-y-1">
                                    <Label class="text-xs text-[#989898]">{{
                                        t('budgets.rule')
                                    }}</Label>
                                    <select
                                        v-model="line.rule_type"
                                        :class="lineFieldClass"
                                    >
                                        <option
                                            v-for="rule in ruleOptions"
                                            :key="rule"
                                            :value="rule"
                                        >
                                            {{ t(`budgets.rules.${rule}`) }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="line.rule_type !== 'remainder'"
                                    class="min-w-0 flex-1 basis-32 space-y-1"
                                >
                                    <Label class="text-xs text-[#989898]">{{
                                        t('budgets.category')
                                    }}</Label>
                                    <select
                                        v-model="line.category_id"
                                        :class="lineFieldClass"
                                    >
                                        <option :value="null">—</option>
                                        <option
                                            v-for="category in props.categories"
                                            :key="category.id"
                                            :value="category.id"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="line.rule_type === 'percent'"
                                    class="w-24 shrink-0 space-y-1"
                                >
                                    <Label class="text-xs text-[#989898]"
                                        >%</Label
                                    >
                                    <Input
                                        v-model="line.percent"
                                        inputmode="decimal"
                                        :class="lineFieldClass"
                                    />
                                </div>

                                <template v-if="line.rule_type === 'fixed'">
                                    <div
                                        class="min-w-0 flex-1 basis-28 space-y-1"
                                    >
                                        <Label class="text-xs text-[#989898]">{{
                                            t('budgets.target')
                                        }}</Label>
                                        <Input
                                            v-model="line.fixed_amount"
                                            inputmode="numeric"
                                            :class="lineFieldClass"
                                        />
                                    </div>

                                    <!-- Rent in toman and a subscription in
                                         dollars belong in one plan, so the
                                         amount carries its own currency. -->
                                    <div class="w-28 shrink-0 space-y-1">
                                        <Label class="text-xs text-[#989898]">{{
                                            t('budgets.currency')
                                        }}</Label>
                                        <select
                                            v-model="line.currency"
                                            :class="lineFieldClass"
                                        >
                                            <option
                                                v-for="currency in props.currencies"
                                                :key="currency.value"
                                                :value="currency.value"
                                            >
                                                {{ currency.label }}
                                            </option>
                                        </select>
                                    </div>
                                </template>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="size-10 shrink-0 rounded-lg text-[#E94E50] hover:bg-[#E94E50]/10"
                                    :aria-label="t('budgets.remove_line')"
                                    @click="removeLine(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <InputError :message="form.errors.lines" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            v-if="props.budget !== null"
                            type="button"
                            variant="ghost"
                            class="text-[#E94E50] hover:bg-[#E94E50]/10"
                            @click="requestDelete()"
                        >
                            {{ t('budgets.delete.action') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="closeDialog()"
                            >{{ t('budgets.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="form.processing || sealing"
                            class="rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010]"
                        >
                            {{ t('budgets.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteModal
            :open="confirmingDelete"
            :title="t('budgets.delete.title')"
            :description="t('budgets.delete.description')"
            :processing="form.processing"
            @update:open="confirmingDelete = $event"
            @confirm="deletePlan()"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Pencil, Plus, Target, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAmountMask } from '@/composables/useAmountMask';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { useVault } from '@/composables/useVault';
import { useVaultBudget } from '@/composables/useVaultBudget';
import type { VaultBudgetPayload } from '@/composables/useVaultBudget';
import type { BudgetRule } from '@/lib/budget';
import { dashboard } from '@/routes';
import {
    destroy as destroyBudget,
    index as budgetsIndex,
    store as storeBudget,
    update as updateBudget,
} from '@/routes/budgets';
import type {
    BudgetForm,
    BudgetIncomeBasis,
    BudgetLineProgress,
    BudgetProgress,
} from '@/types/budgets';

type CategoryOption = { id: number; name: string };

const props = defineProps<{
    budget: BudgetForm | null;
    /** Resolved server-side; null under the vault, where `vaultBudget` carries the rows. */
    progress: BudgetProgress | null;
    vaultBudget: VaultBudgetPayload | null;
    categories: CategoryOption[];
    currencies: { label: string; value: string }[];
    userCalendar: string;
}>();

const { t } = useI18n();
const { navigationName } = useNavigationNaming();
const { revealAsync, sealForSubmit } = useVault();
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);

const { progress: vaultProgress } = useVaultBudget(() => props.vaultBudget);

/**
 * One plan, whichever side resolved it.
 *
 * Null means "not ready" rather than "not there" — the empty state keys off
 * `props.budget` instead, so someone with a plan sees a skeleton while the
 * browser opens their amounts rather than an invitation to create the plan they
 * already have.
 */
const plan = computed<BudgetProgress | null>(
    () => props.progress ?? vaultProgress.value,
);

const incomeBases: BudgetIncomeBasis[] = ['actual', 'expected'];
const ruleOptions: BudgetRule[] = ['percent', 'fixed', 'remainder'];

/** Lines that have actually spent past their own limit — distinct from the
 *  plan-wide over-allocation warning below, which is about the lines' limits
 *  summing past income rather than any one of them being overspent. */
const overspentLines = computed(
    () => plan.value?.lines.filter((line) => line.over) ?? [],
);

const overspentLinesTitle = computed(() =>
    overspentLines.value.length === 1
        ? t('budgets.line_over_title_one')
        : t('budgets.line_over_title_many', {
              count: overspentLines.value.length,
          }),
);

const overspentLinesBody = computed(() =>
    t('budgets.line_over_body', {
        names: overspentLines.value.map((line) => lineLabel(line)).join(', '),
    }),
);

const currencyLabel = computed(() => {
    const value = plan.value?.currency ?? props.budget?.currency ?? 'toman';

    return (
        props.currencies.find((currency) => currency.value === value)?.label ??
        t(`finance.currencies.${value}`)
    );
});

const formatAmount = (value: number | string): string => {
    const amount = Number(value);

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
    }).format(amount);
};

function lineLabel(line: BudgetLineProgress): string {
    return line.category?.name ?? t('budgets.rules.remainder');
}

function ruleLabel(line: BudgetLineProgress): string {
    return line.rule_type === 'percent' && line.percent !== null
        ? `${line.percent}%`
        : t(`budgets.rules.${line.rule_type}`);
}

function ruleBadgeClass(rule: BudgetRule): string {
    if (rule === 'percent') {
        return 'bg-[#6C4EE9]/15 text-[#a89bf3]';
    }

    return rule === 'fixed'
        ? 'bg-white/8 text-[#989898]'
        : 'bg-[#02CD86]/15 text-[#02CD86]';
}

/** Clamped at 100 so an overspent line fills the bar rather than overflowing it. */
function barWidth(line: BudgetLineProgress): number {
    return Math.min(100, Math.round((line.progress ?? 0) * 100));
}

function remainingLabel(line: BudgetLineProgress): string {
    if (line.allocated === 0) {
        return t('budgets.no_target');
    }

    if (line.remaining < 0) {
        return t('budgets.overspent', {
            amount: formatAmount(Math.abs(line.remaining)),
        });
    }

    return line.remaining === 0
        ? t('budgets.on_target')
        : t('budgets.remaining', { amount: formatAmount(line.remaining) });
}

type FormLine = {
    category_id: number | null;
    rule_type: BudgetRule;
    percent: string;
    fixed_amount: string;
    /** Only meaningful on a fixed line; carried on every line so the select binds. */
    currency: string;
    rollover_enabled: boolean;
};

const isDialogOpen = ref(false);
const confirmingDelete = ref(false);

/** True while the browser is wrapping a payload, so the button stays disabled. */
const sealing = ref(false);

const fieldClass =
    'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25';

/**
 * The shared control style for a plan line.
 *
 * Every control in the row — two selects and two inputs — wears the same class,
 * because `.finance-dialog-field` pins the height with `!important` and anything
 * setting its own sat a few pixels off its neighbours. The income tint is
 * deliberately absent here: a percentage is not money, and a single tinted field
 * among three plain ones reads as an error state.
 */
const lineFieldClass = 'finance-dialog-field finance-dialog-field-compact';

const form = useForm({
    title: '',
    income_basis: 'actual' as BudgetIncomeBasis,
    expected_income: '',
    currency: 'toman',
    is_active: true,
    lines: [] as FormLine[],
});

function blankLine(): FormLine {
    return {
        category_id: null,
        rule_type: 'percent',
        percent: '',
        fixed_amount: '',
        // Defaults to the plan's own currency, which is what most lines are in.
        currency: form.currency,
        rollover_enabled: false,
    };
}

function addLine(): void {
    form.lines.push(blankLine());
}

function removeLine(index: number): void {
    form.lines.splice(index, 1);
}

/**
 * Fill the editor, decrypting the stored amounts first when the vault is armed.
 *
 * The inputs are bound to plaintext throughout, so a failed validation round
 * trip still shows the user what they typed.
 */
async function openDialog(): Promise<void> {
    form.clearErrors();

    const budget = props.budget;

    if (budget === null) {
        form.reset();
        form.currency = props.currencies[0]?.value ?? 'toman';
        form.lines = [blankLine()];
        isDialogOpen.value = true;

        return;
    }

    form.title = (await revealAsync<string>(budget.title, 'budgets')) ?? '';
    form.income_basis = budget.income_basis;
    form.expected_income = String(
        (await revealAsync<string | number>(
            budget.expected_income,
            'budgets',
            'decimal',
        )) ?? '',
    );
    form.currency = budget.currency;
    form.is_active = budget.is_active;
    form.lines = await Promise.all(
        budget.lines.map(async (line): Promise<FormLine> => {
            const fixed = await revealAsync<string | number>(
                line.fixed_amount,
                'budget_lines',
                'decimal',
            );

            return {
                category_id: line.category_id,
                rule_type: line.rule_type,
                percent: line.percent === null ? '' : String(line.percent),
                fixed_amount: fixed === undefined ? '' : String(fixed),
                currency: line.currency,
                rollover_enabled: line.rollover_enabled,
            };
        }),
    );

    isDialogOpen.value = true;
}

function closeDialog(): void {
    isDialogOpen.value = false;
    form.clearErrors();
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) {
        closeDialog();
    }
}

/**
 * Seal every amount before it leaves the browser, and no-op otherwise.
 *
 * The percentages are deliberately left in plaintext: they are not money, and
 * shipping them readable is what lets the server reject a plan that promises
 * more than 100% of an income it cannot see.
 */
async function submitPlan(): Promise<void> {
    sealing.value = true;

    try {
        const header = await sealForSubmit(
            {
                title: form.title,
                expected_income:
                    form.income_basis === 'expected'
                        ? form.expected_income
                        : '',
            },
            'budgets',
            { title: 'string', expected_income: 'decimal' },
        );

        const lines = await Promise.all(
            form.lines.map(async (line) => {
                const sealed = await sealForSubmit(
                    {
                        fixed_amount:
                            line.rule_type === 'fixed' ? line.fixed_amount : '',
                    },
                    'budget_lines',
                    { fixed_amount: 'decimal' },
                );

                return {
                    category_id:
                        line.rule_type === 'remainder'
                            ? null
                            : line.category_id,
                    rule_type: line.rule_type,
                    percent: line.rule_type === 'percent' ? line.percent : null,
                    fixed_amount:
                        sealed.fixed_amount === '' ? null : sealed.fixed_amount,
                    // Only a fixed amount has a currency to be in, so every
                    // other rule sends null rather than a fact about nothing.
                    currency: line.rule_type === 'fixed' ? line.currency : null,
                    rollover_enabled: line.rollover_enabled,
                };
            }),
        );

        form.transform(() => ({
            title: header.title === '' ? null : header.title,
            income_basis: form.income_basis,
            expected_income:
                header.expected_income === '' ? null : header.expected_income,
            currency: form.currency,
            is_active: form.is_active,
            lines,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        };

        if (props.budget === null) {
            form.post(storeBudget.url(), options);
        } else {
            form.put(updateBudget.url(props.budget.id), options);
        }
    } finally {
        sealing.value = false;
    }
}

/**
 * Hand over from the editor to the confirmation.
 *
 * The editor closes first rather than stacking: ConfirmDeleteModal sits at z-50
 * and DialogContent at z-[310], so a confirmation opened over the editor would
 * render behind it. One modal at a time also matches how the investments and
 * transactions pages ask.
 */
function requestDelete(): void {
    closeDialog();
    confirmingDelete.value = true;
}

function deletePlan(): void {
    if (props.budget === null) {
        return;
    }

    form.delete(destroyBudget.url(props.budget.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingDelete.value = false;
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Budgets', href: budgetsIndex() },
        ],
    },
});
</script>
