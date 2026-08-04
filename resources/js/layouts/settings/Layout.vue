<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Blocks,
    Bot,
    Coins,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
    Tags,
    User,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component } from 'vue';
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
const showTelegram = computed(
    () => page.props.features?.telegram_bot?.enabled !== false,
);
const showAiConnections = computed(
    () => page.props.features?.ai_assistant?.enabled !== false,
);

type SettingsNavItem = NavItem & { icon: Component };

type SettingsNavGroup = { label: string; items: SettingsNavItem[] };

/**
 * Grouped rather than a flat list of nine.
 *
 * Nine links in a row is past the point where scanning works — "Categories"
 * and "Assets" are shaped nothing like "Profile", and a flat list says they
 * are. Groups also survive a module being switched off: an empty one drops out
 * rather than leaving a stray heading.
 */
const navGroups = computed<SettingsNavGroup[]>(() =>
    [
        {
            label: t('settings.navigation.groups.account'),
            items: [
                {
                    title: t('settings.navigation.profile'),
                    href: editProfile(),
                    icon: User,
                },
                {
                    title: t('settings.navigation.notifications'),
                    href: editNotifications(),
                    icon: Bell,
                },
                {
                    title: t('settings.navigation.security'),
                    href: editSecurity(),
                    icon: ShieldCheck,
                },
                {
                    title: t('settings.navigation.preferences'),
                    href: '/settings/preferences',
                    icon: SlidersHorizontal,
                },
            ],
        },
        {
            label: t('settings.navigation.groups.workspace'),
            items: [
                {
                    title: t('settings.navigation.modules'),
                    href: editModules(),
                    icon: Blocks,
                },
                {
                    title: t('settings.navigation.categories'),
                    href: editCategories(),
                    icon: Tags,
                },
                ...(showAssets.value
                    ? [
                          {
                              title: t('settings.navigation.assets'),
                              href: editInvestmentAssets(),
                              icon: Coins,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('settings.navigation.groups.integrations'),
            items: [
                ...(showTelegram.value
                    ? [
                          {
                              title: t('settings.navigation.telegram'),
                              href: editTelegram(),
                              icon: Bot,
                          },
                      ]
                    : []),
                ...(showAiConnections.value
                    ? [
                          {
                              title: t('settings.navigation.ai'),
                              href: editAiConnections(),
                              icon: Sparkles,
                          },
                      ]
                    : []),
            ],
        },
    ].filter((group) => group.items.length > 0),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-col bg-[#101010] px-[18px] py-[18px]"
    >
        <div class="w-full">
            <div class="mb-[18px]">
                <h1 class="text-[22px] font-normal text-white">
                    {{ t('settings.title') }}
                </h1>
                <p class="mt-1 text-sm text-[#989898]">
                    {{ t('settings.description') }}
                </p>
            </div>

            <div class="flex flex-col gap-[18px] lg:flex-row lg:items-start">
                <!-- Settings nav. Sticky from lg up so it stays reachable while
                     a long pane scrolls; 92px clears the app header. -->
                <aside class="w-full shrink-0 lg:sticky lg:top-[92px] lg:w-56">
                    <nav
                        class="space-y-4 overflow-hidden rounded-[22px] bg-[#1a1a1a] p-3 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                        :aria-label="t('settings.title')"
                    >
                        <div v-for="group in navGroups" :key="group.label">
                            <p
                                :id="`settings-nav-${group.label}`"
                                class="px-3 pb-1 text-[11px] font-medium tracking-[0.2em] text-[#6f6f6f] uppercase"
                            >
                                {{ group.label }}
                            </p>
                            <div
                                role="group"
                                :aria-labelledby="`settings-nav-${group.label}`"
                            >
                                <Link
                                    v-for="item in group.items"
                                    :key="toUrl(item.href)"
                                    :href="item.href"
                                    :aria-current="
                                        isCurrentOrParentUrl(item.href)
                                            ? 'page'
                                            : undefined
                                    "
                                    :class="[
                                        'flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors duration-200',
                                        'focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none',
                                        isCurrentOrParentUrl(item.href)
                                            ? 'bg-[#02CD86]/10 font-medium text-[#02CD86]'
                                            : 'text-[#989898] hover:bg-white/5 hover:text-white',
                                    ]"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        item.title
                                    }}</span>
                                </Link>
                            </div>
                        </div>
                    </nav>
                </aside>

                <!-- No card of its own any more: each page composes its own
                     SettingsSection cards, so a form and a destructive action
                     stop sharing one surface. Fills whatever width it is given;
                     SettingsRow is what keeps controls readable inside it. -->
                <div class="min-w-0 flex-1 space-y-[18px]">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
