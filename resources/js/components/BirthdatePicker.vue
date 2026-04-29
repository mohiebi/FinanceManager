<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = withDefaults(
    defineProps<{
        name?: string;
        defaultValue?: string | null;
        triggerClass?: string;
    }>(),
    {
        name: 'birthdate',
        defaultValue: null,
        triggerClass: '',
    },
);

const currentYear = new Date().getFullYear();
const selectedYear = ref('');
const selectedMonth = ref('');
const selectedDay = ref('');

const years = computed(() =>
    Array.from({ length: 121 }, (_, index) => String(currentYear - index)),
);

const months = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

const days = computed(() => {
    const year = Number(selectedYear.value || currentYear);
    const month = Number(selectedMonth.value || 1);
    const daysInMonth = new Date(year, month, 0).getDate();

    return Array.from({ length: daysInMonth }, (_, index) =>
        String(index + 1).padStart(2, '0'),
    );
});

const birthdate = computed(() => {
    if (!selectedYear.value || !selectedMonth.value || !selectedDay.value) {
        return '';
    }

    return `${selectedYear.value}-${selectedMonth.value}-${selectedDay.value}`;
});

watch(
    () => props.defaultValue,
    (value) => {
        if (!value) {
            return;
        }

        const [year, month, day] = value.split('-');

        selectedYear.value = year ?? '';
        selectedMonth.value = month ?? '';
        selectedDay.value = day ?? '';
    },
    { immediate: true },
);

watch(days, (availableDays) => {
    if (selectedDay.value && !availableDays.includes(selectedDay.value)) {
        selectedDay.value = availableDays.at(-1) ?? '';
    }
});
</script>

<template>
    <input type="hidden" :name="name" :value="birthdate" />

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1.2fr_1fr_1fr]">
        <Select v-model="selectedMonth" required>
            <SelectTrigger :class="cn('w-full', triggerClass)">
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

        <Select v-model="selectedDay" required>
            <SelectTrigger :class="cn('w-full', triggerClass)">
                <SelectValue placeholder="Day" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="day in days" :key="day" :value="day">
                    {{ Number(day) }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select v-model="selectedYear" required>
            <SelectTrigger :class="cn('w-full', triggerClass)">
                <SelectValue placeholder="Year" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="year in years" :key="year" :value="year">
                    {{ year }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
