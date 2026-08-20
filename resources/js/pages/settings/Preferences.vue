<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Globe, Plane } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSaveBar from '@/components/settings/SettingsSaveBar.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
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

function discard(): void {
    form.reset();
}
</script>

<template>
    <div>
        <Head :title="t('settings.preferences.title')" />

        <!-- One form, two cards, and until now one save button — sitting in the
             footer of the *second* card. Change your language in the first and
             the only control that would commit it was two screens down, under a
             heading about terminology. The sticky bar below covers the whole
             form, appears the moment anything differs from what is stored, and
             stays on screen wherever you are in it. -->
        <form class="space-y-[18px]" @submit.prevent="submit">
            <SettingsSection
                :icon="Globe"
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
                :icon="Plane"
                :title="t('settings.preferences.terminology.title')"
                :description="t('settings.preferences.terminology.description')"
            >
                <SettingsRow
                    :label="t('settings.preferences.terminology.field_label')"
                    :help="t('settings.preferences.terminology.checkbox_help')"
                    control-id="flight_terminology_enabled"
                    last
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
            </SettingsSection>

            <SettingsSaveBar
                sticky
                :processing="form.processing"
                :dirty="form.isDirty"
                :recently-successful="form.recentlySuccessful"
                @discard="discard"
            />
        </form>
    </div>
</template>
