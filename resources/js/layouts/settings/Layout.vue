<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAiConnections } from '@/routes/ai-connections';
import { edit as editCategories } from '@/routes/categories';
import { edit as editInvestmentAssets } from '@/routes/investment-assets';
import { edit as editModules } from '@/routes/modules';
import { edit as editNotifications } from '@/routes/notifications';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editTelegram } from '@/routes/telegram';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();

// Custom assets only mean anything with the investments module switched on.
const showAssets = computed(
    () => page.props.features?.investments?.enabled !== false,
);

const sidebarNavItems = computed<NavItem[]>(() => [
    { title: t('settings.navigation.profile'), href: editProfile() },
    {
        title: t('settings.navigation.notifications'),
        href: editNotifications(),
    },
    { title: t('settings.navigation.security'), href: editSecurity() },
    {
        title: t('settings.navigation.preferences'),
        href: '/settings/preferences',
    },
    { title: t('settings.navigation.modules'), href: editModules() },
    { title: t('settings.navigation.categories'), href: editCategories() },
    ...(showAssets.value
        ? [
              {
                  title: t('settings.navigation.assets'),
                  href: editInvestmentAssets(),
              },
          ]
        : []),
    { title: t('settings.navigation.telegram'), href: editTelegram() },
    { title: t('settings.navigation.ai'), href: editAiConnections() },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-col bg-[#101010] px-[18px] py-[18px]"
    >
        <div class="mb-[18px]">
            <h1 class="text-[22px] font-normal text-white">
                {{ t('settings.title') }}
            </h1>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('settings.description') }}
            </p>
        </div>

        <div class="flex flex-col gap-[18px] lg:flex-row lg:items-start">
            <!-- Settings nav -->
            <aside class="w-full shrink-0 lg:w-44">
                <nav
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-2 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    :aria-label="t('settings.title')"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        :class="[
                            'flex w-full cursor-pointer items-center rounded-xl px-3 py-2.5 text-sm transition',
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-[#02CD86]/10 font-medium text-[#02CD86]'
                                : 'text-[#989898] hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <!-- Page content -->
            <div class="min-w-0 flex-1">
                <div
                    class="rounded-[22px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10 md:max-w-2xl"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
