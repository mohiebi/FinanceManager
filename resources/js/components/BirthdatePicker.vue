<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import jalaali from 'jalaali-js';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        name?: string;
        defaultValue?: string | null;
        triggerClass?: string;
        containerClass?: string;
        required?: boolean;
        yearsBack?: number;
        yearsForward?: number;
        monthPlaceholder?: string;
        dayPlaceholder?: string;
        yearPlaceholder?: string;
    }>(),
    {
        name: 'birthdate',
        defaultValue: null,
        triggerClass: '',
        containerClass: '',
        required: true,
        yearsBack: 120,
        yearsForward: 0,
        monthPlaceholder: '',
        dayPlaceholder: '',
        yearPlaceholder: '',
    },
);

const modelValue = defineModel<string>({ default: '' });
/*
 * jalaali-js is CommonJS. Named imports from it resolve through a bundler but
 * not through Node's ESM loader, so an SSR build fails at module instantiation
 * — before any component renders, which puts it out of reach of Vue's error
 * handler and takes the whole page's SSR down with it. Destructuring the
 * default export works in both. lib/date.ts and lib/bill-recurrence.ts do the
 * same for the same reason.
 */
const { isValidJalaaliDate, jalaaliMonthLength, toGregorian, toJalaali } =
    jalaali;

const page = usePage();
const { t } = useI18n();
const selectedYear = ref('');
const selectedMonth = ref('');
const selectedDay = ref('');

const calendar = computed(() =>
    ((page.props.calendar as string | undefined) ?? 'gregorian') === 'jalali'
        ? 'jalali'
        : 'gregorian',
);
const today = new Date();
const currentYear = computed(() => {
    if (calendar.value === 'jalali') {
        return toJalaali(
            today.getFullYear(),
            today.getMonth() + 1,
            today.getDate(),
        ).jy;
    }

    return today.getFullYear();
});

const years = computed(() =>
    Array.from(
        { length: props.yearsBack + props.yearsForward + 1 },
        (_, index) => String(currentYear.value + props.yearsForward - index),
    ),
);

const gregorianMonths = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const jalaliMonths = [
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
];

const months = computed(() =>
    (calendar.value === 'jalali' ? jalaliMonths : gregorianMonths).map(
        (label, index) => ({
            value: String(index + 1).padStart(2, '0'),
            label,
        }),
    ),
);

const days = computed(() => {
    const year = Number(selectedYear.value || currentYear.value);
    const month = Number(selectedMonth.value || 1);
    const daysInMonth =
        calendar.value === 'jalali'
            ? jalaaliMonthLength(year, month)
            : new Date(year, month, 0).getDate();

    return Array.from({ length: daysInMonth }, (_, index) =>
        String(index + 1).padStart(2, '0'),
    );
});

const birthdate = computed(() => {
    if (!selectedYear.value || !selectedMonth.value || !selectedDay.value) {
        return '';
    }

    if (calendar.value === 'gregorian') {
        return `${selectedYear.value}-${selectedMonth.value}-${selectedDay.value}`;
    }

    const year = Number(selectedYear.value);
    const month = Number(selectedMonth.value);
    const day = Number(selectedDay.value);

    if (!isValidJalaaliDate(year, month, day)) {
        return '';
    }

    const gregorian = toGregorian(year, month, day);

    return `${gregorian.gy}-${String(gregorian.gm).padStart(2, '0')}-${String(gregorian.gd).padStart(2, '0')}`;
});

watch(
    modelValue,
    (value) => {
        syncDate(value);
    },
    { immediate: true },
);

// Registered after the modelValue watcher so its immediate empty-model sync
// cannot wipe the selections initialised from defaultValue.
watch(
    () => props.defaultValue,
    (value) => {
        if (!value || modelValue.value) {
            return;
        }

        syncDate(value);
    },
    { immediate: true },
);

watch(birthdate, (value) => {
    modelValue.value = value;
});

watch(days, (availableDays) => {
    if (selectedDay.value && !availableDays.includes(selectedDay.value)) {
        selectedDay.value = availableDays.at(-1) ?? '';
    }
});

watch(calendar, () => {
    syncDate(modelValue.value);
});

function syncDate(value: string): void {
    if (!value) {
        selectedYear.value = '';
        selectedMonth.value = '';
        selectedDay.value = '';

        return;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (calendar.value === 'jalali') {
        const jalali = toJalaali(year, month, day);

        selectedYear.value = String(jalali.jy);
        selectedMonth.value = String(jalali.jm).padStart(2, '0');
        selectedDay.value = String(jalali.jd).padStart(2, '0');

        return;
    }

    selectedYear.value = String(year);
    selectedMonth.value = String(month).padStart(2, '0');
    selectedDay.value = String(day).padStart(2, '0');
}
</script>

<template>
    <input type="hidden" :name="name" :value="birthdate" />

    <div
        :class="
            cn(
                'grid grid-cols-1 gap-2 sm:grid-cols-[1.2fr_1fr_1fr]',
                containerClass,
            )
        "
    >
        <Select v-model="selectedMonth">
            <SelectTrigger
                :aria-required="required ? 'true' : undefined"
                :class="cn('w-full', triggerClass)"
            >
                <SelectValue
                    :placeholder="monthPlaceholder || t('common.month')"
                />
            </SelectTrigger>
            <SelectContent class="finance-dialog-select-content">
                <SelectItem
                    v-for="month in months"
                    :key="month.value"
                    :value="month.value"
                >
                    {{ month.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select v-model="selectedDay">
            <SelectTrigger
                :aria-required="required ? 'true' : undefined"
                :class="cn('w-full', triggerClass)"
            >
                <SelectValue :placeholder="dayPlaceholder || t('common.day')" />
            </SelectTrigger>
            <SelectContent class="finance-dialog-select-content">
                <SelectItem v-for="day in days" :key="day" :value="day">
                    {{ Number(day) }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select v-model="selectedYear">
            <SelectTrigger
                :aria-required="required ? 'true' : undefined"
                :class="cn('w-full', triggerClass)"
            >
                <SelectValue
                    :placeholder="yearPlaceholder || t('common.year')"
                />
            </SelectTrigger>
            <SelectContent class="finance-dialog-select-content">
                <SelectItem v-for="year in years" :key="year" :value="year">
                    {{ year }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
