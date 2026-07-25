<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useModuleNav } from '@/composables/useModuleNav';
import type { ModuleNavItem } from '@/composables/useModuleNav';

const { t } = useI18n();
const { isCurrentUrl } = useCurrentUrl();
const { navItems } = useModuleNav();

const page = usePage();
const user = computed(() => page.props.auth.user);

// Promo items all point at the modules page, so matching on the URL alone would
// light every one of them up at once while you are on it.
function isActive(item: ModuleNavItem): boolean {
    return item.state === 'enabled' && isCurrentUrl(item.href);
}
</script>

<template>
    <!-- Visible only below lg breakpoint -->
    <nav
        :aria-label="t('navigation.mobile')"
        class="app-mobile-bottom-nav flex shrink-0 items-stretch border-t border-white/10 bg-[#353535] lg:hidden"
    >
        <!-- Main nav items -->
        <Link
            v-for="item in navItems"
            :key="item.key"
            :href="item.href"
            :aria-current="isActive(item) ? 'page' : undefined"
            class="relative flex flex-1 flex-col items-center justify-center gap-1 transition-colors duration-150"
            :class="
                isActive(item)
                    ? 'text-[#02cd86]'
                    : item.state === 'promo'
                      ? 'text-white/25'
                      : 'text-white/45 hover:text-white/75'
            "
        >
            <!-- Active indicator bar -->
            <span
                v-if="isActive(item)"
                class="absolute inset-x-3 top-0 h-[2px] rounded-b-full bg-[#02cd86]"
            />
            <span class="relative">
                <component :is="item.icon" class="size-[22px] shrink-0" />
                <Plus
                    v-if="item.state === 'promo'"
                    class="rtl:-right-auto absolute -top-1 -right-1 size-3 rounded-full bg-[#02cd86] text-[#353535] rtl:-left-1"
                />
            </span>
            <span
                class="max-w-full truncate text-[9px] leading-tight font-medium tracking-wide"
            >
                {{ item.title }}
            </span>
        </Link>

        <!-- Settings dropdown -->
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    :title="t('settings.title')"
                    class="flex flex-1 flex-col items-center justify-center gap-1 text-white/45 transition-colors duration-150 hover:text-white/75 focus-visible:outline-none"
                >
                    <Settings class="size-[22px] shrink-0" />
                    <span
                        class="max-w-full truncate text-[9px] leading-tight font-medium tracking-wide"
                    >
                        {{ t('settings.title') }}
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="w-64"
                side="top"
                align="end"
                :side-offset="8"
            >
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </nav>
</template>
