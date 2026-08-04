<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type Option = {
    label: string;
    value: string;
};

type TimezoneGroup = {
    label: string;
    options: Option[];
};

const props = defineProps<{
    locales: Option[];
    calendars: Option[];
    currencies: Option[];
    timezoneGroups: TimezoneGroup[];
    defaultCurrency: string | null;
}>();

const page = usePage();
const { t } = useI18n();

const form = useForm({
    locale: (page.props.locale as string | undefined) ?? 'en',
    calendar: (page.props.calendar as string | undefined) ?? 'gregorian',
    timezone: (page.props.timezone as string | undefined) ?? 'UTC',
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

    <SettingsSection
        :title="t('settings.preferences.eyebrow')"
        :description="t('settings.preferences.description')"
    >
        <form @submit.prevent="submit">
            <SettingsRow
                :label="t('settings.preferences.language')"
                control-id="locale"
            >
                <Select id="locale" v-model="form.locale">
                    <SelectTrigger
                        class="finance-dialog-field finance-dialog-field-income w-full sm:w-[220px]"
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
                <InputError :message="form.errors.locale" />
            </SettingsRow>

            <SettingsRow
                :label="t('settings.preferences.calendar')"
                control-id="calendar"
            >
                <Select id="calendar" v-model="form.calendar">
                    <SelectTrigger
                        class="finance-dialog-field finance-dialog-field-income w-full sm:w-[220px]"
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
                <InputError :message="form.errors.calendar" />
            </SettingsRow>

            <SettingsRow
                :label="t('settings.preferences.timezone')"
                :help="t('settings.preferences.timezone_hint')"
                control-id="timezone"
            >
                <Select id="timezone" v-model="form.timezone">
                    <SelectTrigger
                        class="finance-dialog-field finance-dialog-field-income w-full sm:w-[320px]"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="finance-dialog-select-content">
                        <SelectGroup
                            v-for="group in props.timezoneGroups"
                            :key="group.label"
                        >
                            <SelectLabel>{{ group.label }}</SelectLabel>
                            <SelectItem
                                v-for="zone in group.options"
                                :key="zone.value"
                                :value="zone.value"
                            >
                                {{ zone.label }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.timezone" />
            </SettingsRow>

            <SettingsRow
                :label="t('settings.preferences.default_currency')"
                control-id="default_currency"
                last
            >
                <Select id="default_currency" v-model="form.default_currency">
                    <SelectTrigger
                        class="finance-dialog-field finance-dialog-field-income w-full sm:w-[220px]"
                    >
                        <SelectValue
                            :placeholder="
                                t('settings.preferences.no_default_currency')
                            "
                        />
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
                <InputError :message="form.errors.default_currency" />
            </SettingsRow>

            <div class="flex items-center gap-4 pt-5">
                <Button
                    class="bg-[#02CD86] text-[#101010] hover:bg-[#08dd93]"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ t('common.save') }}
                </Button>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-show="form.recentlySuccessful"
                        class="text-sm text-[#02CD86]"
                    >
                        {{ t('common.saved') }}
                    </p>
                </Transition>
            </div>
        </form>
    </SettingsSection>
</template>
