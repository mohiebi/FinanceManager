<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { CalendarCheck, Trash2, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSaveBar from '@/components/settings/SettingsSaveBar.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import { Input } from '@/components/ui/input';
import { edit } from '@/routes/profile';

type Props = {
    mustVerifyEmail: boolean;
    hasPassword: boolean;
    requiresProfileCompletion: boolean;
    status?: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profile settings', href: edit() }],
    },
});

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="t('settings.profile.title')" />

    <div class="flex flex-col gap-[18px]">
        <!-- Profile-completion callout -->
        <div
            v-if="requiresProfileCompletion"
            class="flex gap-4 rounded-2xl border border-[#02CD86]/25 bg-[#02CD86]/8 p-5"
        >
            <!-- Icon -->
            <div
                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#02CD86]/15"
            >
                <CalendarCheck
                    class="size-5 text-[#02CD86]"
                    aria-hidden="true"
                />
            </div>
            <!-- Text -->
            <div>
                <p class="text-sm font-semibold text-[#02CD86]">
                    {{ t('settings.profile.finish_banner_title') }}
                </p>
                <p class="mt-1 text-sm leading-relaxed text-white/70">
                    {{ t('settings.profile.finish_banner_body') }}
                </p>
            </div>
        </div>

        <SettingsSection
            :icon="UserRound"
            :title="
                requiresProfileCompletion
                    ? t('settings.profile.complete_heading')
                    : t('settings.profile.heading')
            "
            :description="
                requiresProfileCompletion
                    ? t('settings.profile.complete_description')
                    : t('settings.profile.description')
            "
        >
            <Form
                v-bind="ProfileController.update.form()"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <SettingsRow :label="t('fields.name')" control-id="name">
                    <Input
                        id="name"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        :placeholder="
                            t('settings.profile.placeholder_full_name')
                        "
                        class="settings-input"
                    />
                    <InputError :message="errors.name" />
                </SettingsRow>

                <SettingsRow
                    :label="t('fields.email_address')"
                    :help="t('settings.profile.email_note')"
                    control-id="email"
                >
                    <Input
                        id="email"
                        type="email"
                        :default-value="user.email"
                        disabled
                        autocomplete="username"
                        :placeholder="t('fields.email_address')"
                        class="settings-input"
                    />
                </SettingsRow>

                <SettingsRow :label="t('fields.birthdate')" last>
                    <BirthdatePicker
                        name="birthdate"
                        :default-value="user.birthdate"
                    />
                    <InputError :message="errors.birthdate" />
                </SettingsRow>

                <div class="pt-5">
                    <SettingsSaveBar
                        :processing="processing"
                        :recently-successful="recentlySuccessful"
                    />
                </div>
            </Form>
        </SettingsSection>

        <!-- Its own card, and a red one: deleting an account should not share a
             surface with editing a name. -->
        <SettingsSection
            v-if="!requiresProfileCompletion"
            danger
            :icon="Trash2"
            :title="t('settings.profile.delete_heading')"
            :description="t('settings.profile.delete_description')"
        >
            <DeleteUser :has-password="hasPassword" />
        </SettingsSection>
    </div>
</template>
