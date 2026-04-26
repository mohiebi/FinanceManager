<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <div
            v-if="requiresProfileCompletion"
            class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700"
        >
            Finish your profile to start using the dashboard.
        </div>

        <Heading
            variant="small"
            :title="requiresProfileCompletion ? 'Complete your profile' : 'Profile information'"
            :description="requiresProfileCompletion
                ? 'We already verified your Google account. Add the last details to continue.'
                : 'Update your personal information'"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    :default-value="user.email"
                    disabled
                    autocomplete="username"
                    placeholder="Email address"
                />
                <p class="text-sm text-muted-foreground">
                    Email changes need a separate verification step.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="birthdate">Birthdate</Label>
                <BirthdatePicker
                    name="birthdate"
                    :default-value="user.birthdate"
                />
                <InputError class="mt-2" :message="errors.birthdate" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-show="recentlySuccessful"
                        class="text-sm text-neutral-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </Form>
    </div>

    <DeleteUser
        v-if="!requiresProfileCompletion"
        :has-password="hasPassword"
    />
</template>
