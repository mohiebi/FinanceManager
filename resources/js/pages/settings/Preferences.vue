<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type Option = {
    label: string;
    value: string;
};

const props = defineProps<{
    locales: Option[];
    calendars: Option[];
    currencies: Option[];
    defaultCurrency: string | null;
}>();

const page = usePage();
const { t } = useI18n();

const form = useForm({
    locale: (page.props.locale as string | undefined) ?? 'en',
    calendar: (page.props.calendar as string | undefined) ?? 'gregorian',
    default_currency: props.defaultCurrency ?? null,
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Preferences', href: '/settings/preferences' }],
    },
});

function submit(): void {
    form.patch('/settings/preferences', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('settings.preferences.title')" />

    <div class="space-y-6">
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('settings.preferences.eyebrow') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('settings.preferences.description') }}
            </p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <Label class="min-w-24 text-white" for="locale">
                        {{ t('settings.preferences.language') }}:
                    </Label>
                    <Select id="locale" v-model="form.locale">
                        <SelectTrigger
                            class="finance-dialog-field finance-dialog-field-income w-[148px]"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="finance-dialog-select-content">
                            <SelectItem
                                v-for="locale in props.locales"
                                :key="locale.value"
                                :value="locale.value"
                            >
                                {{ locale.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <InputError :message="form.errors.locale" />
            </div>

            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <Label class="min-w-24 text-white" for="calendar">
                        {{ t('settings.preferences.calendar') }}:
                    </Label>
                    <Select id="calendar" v-model="form.calendar">
                        <SelectTrigger
                            class="finance-dialog-field finance-dialog-field-income w-[148px]"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="finance-dialog-select-content">
                            <SelectItem
                                v-for="calendar in props.calendars"
                                :key="calendar.value"
                                :value="calendar.value"
                            >
                                {{ calendar.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <InputError :message="form.errors.calendar" />
            </div>

            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <Label class="min-w-24 text-white" for="default_currency">
                        {{ t('settings.preferences.default_currency') }}:
                    </Label>
                    <Select id="default_currency" v-model="form.default_currency">
                        <SelectTrigger
                            class="finance-dialog-field finance-dialog-field-income w-[148px]"
                        >
                            <SelectValue :placeholder="t('settings.preferences.no_default_currency')" />
                        </SelectTrigger>
                        <SelectContent class="finance-dialog-select-content">
                            <SelectItem
                                v-for="currency in props.currencies"
                                :key="currency.value"
                                :value="currency.value"
                            >
                                {{ currency.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <InputError :message="form.errors.default_currency" />
            </div>

            <Button
                class="bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                :disabled="form.processing"
            >
                <Spinner v-if="form.processing" />
                {{ t('common.save') }}
            </Button>
        </form>
    </div>
</template>
