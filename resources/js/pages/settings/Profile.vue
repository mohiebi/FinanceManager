<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
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

    <h1 class="sr-only">{{ t('settings.profile.title') }}</h1>

    <div class="flex flex-col gap-8">
        <!-- Banner -->
        <div
            v-if="requiresProfileCompletion"
            class="rounded-xl bg-[#02CD86]/10 px-4 py-3 text-sm text-[#02CD86] ring-1 ring-[#02CD86]/20"
        >
            {{ t('settings.profile.finish_banner') }}
        </div>

        <!-- Heading -->
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
            >
                {{ requiresProfileCompletion ? t('settings.profile.complete_heading') : t('settings.profile.heading') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{
                    requiresProfileCompletion
                        ? t('settings.profile.complete_description')
                        : t('settings.profile.description')
                }}
            </p>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-5"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-1.5">
                <label
                    for="name"
                    class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                    >{{ t('fields.name') }}</label
                >
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="t('settings.profile.placeholder_full_name')"
                    class="border-white/10 bg-[#252525] dark:bg-[#252525] text-white placeholder:text-[#686868] focus-visible:ring-1 focus-visible:ring-[#02cd86] focus-visible:border-[#02cd86]"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.5">
                <label
                    for="email"
                    class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                    >{{ t('fields.email_address') }}</label
                >
                <Input
                    id="email"
                    type="email"
                    :default-value="user.email"
                    disabled
                    autocomplete="username"
                    :placeholder="t('fields.email_address')"
                    class="border-white/10 bg-[#252525] dark:bg-[#252525] text-white opacity-60 placeholder:text-[#686868]"
                />
                <p class="text-xs text-[#686868]">
                    {{ t('settings.profile.email_note') }}
                </p>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="birthdate"
                    class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                    >{{ t('fields.birthdate') }}</label
                >
                <BirthdatePicker
                    name="birthdate"
                    :default-value="user.birthdate"
                />
                <InputError :message="errors.birthdate" />
            </div>

            <div class="flex items-center gap-4 pt-1">
                <button
                    type="submit"
                    :disabled="processing"
                    data-test="update-profile-button"
                    class="rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition hover:brightness-110 disabled:opacity-50"
                >
                    {{ t('common.save') }}
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-show="recentlySuccessful" class="text-sm text-[#02CD86]">
                        {{ t('common.saved') }}
                    </p>
                </Transition>
            </div>
        </Form>

        <!-- Delete account -->
        <div v-if="!requiresProfileCompletion" class="border-t border-white/5 pt-8">
            <DeleteUser :has-password="hasPassword" />
        </div>
    </div>
</template>
