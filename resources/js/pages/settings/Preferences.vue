<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    flight_terminology_enabled:
        (page.props.flightTerminologyEnabled as boolean | undefined) ?? true,
});

const terminologyMappings = [
    'dashboard',
    'report',
    'investments',
    'goals',
    'budgets',
    'advisor',
    'ai',
    'settings',
    'admin',
] as const;

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
    <div>
        <Head :title="t('settings.preferences.title')" />

        <form class="space-y-[18px]" @submit.prevent="submit">
            <SettingsSection
                :title="t('settings.preferences.eyebrow')"
                :description="t('settings.preferences.description')"
            >
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
                            class="finance-dialog-field finance-dialog-field-income w-full sm:w-[220px]"
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
                    <Select
                        id="default_currency"
                        v-model="form.default_currency"
                    >
                        <SelectTrigger
                            class="finance-dialog-field finance-dialog-field-income w-full sm:w-[220px]"
                        >
                            <SelectValue
                                :placeholder="
                                    t(
                                        'settings.preferences.no_default_currency',
                                    )
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
            </SettingsSection>

            <SettingsSection
                :title="t('settings.preferences.terminology.title')"
                :description="t('settings.preferences.terminology.description')"
            >
                <SettingsRow
                    :label="t('settings.preferences.terminology.field_label')"
                    :help="t('settings.preferences.terminology.checkbox_help')"
                    control-id="flight_terminology_enabled"
                >
                    <label
                        for="flight_terminology_enabled"
                        class="inline-flex cursor-pointer items-center gap-3 rounded-xl bg-white/[0.035] px-4 py-3 ring-1 ring-white/10 transition-colors hover:bg-white/[0.06]"
                    >
                        <Checkbox
                            id="flight_terminology_enabled"
                            :checked="form.flight_terminology_enabled"
                            @update:checked="
                                form.flight_terminology_enabled =
                                    $event === true
                            "
                        />
                        <span class="text-sm text-white">
                            {{
                                t(
                                    'settings.preferences.terminology.checkbox_label',
                                )
                            }}
                        </span>
                    </label>
                    <InputError
                        :message="form.errors.flight_terminology_enabled"
                    />
                </SettingsRow>

                <SettingsRow last>
                    <div
                        class="overflow-hidden rounded-2xl bg-[#141414] ring-1 ring-white/10"
                    >
                        <div
                            class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 border-b border-white/10 px-4 py-3 text-[11px] font-medium tracking-[0.16em] text-[#989898] uppercase"
                        >
                            <span>{{
                                t(
                                    'settings.preferences.terminology.standard_heading',
                                )
                            }}</span>
                            <span aria-hidden="true"></span>
                            <span>{{
                                t(
                                    'settings.preferences.terminology.flight_heading',
                                )
                            }}</span>
                        </div>
                        <div
                            v-for="(mapping, index) in terminologyMappings"
                            :key="mapping"
                            class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 px-4 py-3 text-sm"
                            :class="
                                index < terminologyMappings.length - 1
                                    ? 'border-b border-white/5'
                                    : ''
                            "
                        >
                            <span class="min-w-0 text-white/75">
                                {{
                                    t(
                                        `settings.preferences.terminology.mappings.${mapping}.standard`,
                                    )
                                }}
                            </span>
                            <ArrowRight
                                class="size-4 shrink-0 text-[#6C4EE9] rtl:rotate-180"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 font-medium text-[#02CD86]">
                                {{
                                    t(
                                        `settings.preferences.terminology.mappings.${mapping}.flight`,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </SettingsRow>

                <template #footer>
                    <div class="flex items-center gap-4">
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
                </template>
            </SettingsSection>
        </form>
    </div>
</template>
