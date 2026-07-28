<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useVault } from '@/composables/useVault';
import { logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const { lock } = useVault();

const handleLogout = () => {
    router.flushAll();

    // Drops the data key from memory and from this device, so signing out on a
    // trusted device genuinely revokes it rather than leaving it for whoever
    // signs in next.
    lock();
};

const { t } = useI18n();
const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem v-if="isAdmin" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="adminDashboard()"
                prefetch
            >
                <ShieldCheck class="mr-2 h-4 w-4" />
                Admin
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('settings.title') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('navigation.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
