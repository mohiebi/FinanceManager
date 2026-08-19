<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Blocks,
    Bot,
    Coins,
    CreditCard,
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
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { toUrl } from '@/lib/utils';
import { edit as editAiConnections } from '@/routes/ai-connections';
import { edit as editBilling } from '@/routes/billing';
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
const { navigationName } = useNavigationNaming();

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
// Billing is not a module, so it is gated on the kill switch rather than on a
// feature toggle: while there is nothing to sell there is nothing to show.
const showBilling = computed(
    () => page.props.subscription?.billing_enabled === true,
);

type SettingsNavItem = NavItem & {
    icon: Component;
    /** Matches `settings.navigation.descriptions.*`, shown in the page header. */
    description: string;
};

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
                    description: t('settings.navigation.descriptions.profile'),
                    href: editProfile(),
                    icon: User,
                },
                {
                    title: t('settings.navigation.security'),
                    description: t('settings.navigation.descriptions.security'),
                    href: editSecurity(),
                    icon: ShieldCheck,
                },
                ...(showBilling.value
                    ? [
                          {
                              title: t('settings.navigation.billing'),
                              description: t(
                                  'settings.navigation.descriptions.billing',
                              ),
                              href: editBilling(),
                              icon: CreditCard,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('settings.navigation.groups.money'),
            items: [
                {
                    title: t('settings.navigation.preferences'),
                    description: t(
                        'settings.navigation.descriptions.preferences',
                    ),
                    href: '/settings/preferences',
                    icon: SlidersHorizontal,
                },
                {
                    title: t('settings.navigation.categories'),
                    description: t(
                        'settings.navigation.descriptions.categories',
                    ),
                    href: editCategories(),
                    icon: Tags,
                },
                ...(showAssets.value
                    ? [
                          {
                              title: t('settings.navigation.assets'),
                              description: t(
                                  'settings.navigation.descriptions.assets',
                              ),
                              href: editInvestmentAssets(),
                              icon: Coins,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('settings.navigation.groups.connections'),
            items: [
                ...(showTelegram.value
                    ? [
                          {
                              title: t('settings.navigation.telegram'),
                              description: t(
                                  'settings.navigation.descriptions.telegram',
                              ),
                              href: editTelegram(),
                              icon: Bot,
                          },
                      ]
                    : []),
                ...(showAiConnections.value
                    ? [
                          {
                              title: t('settings.navigation.ai'),
                              description: t(
                                  'settings.navigation.descriptions.ai',
                              ),
                              href: editAiConnections(),
                              icon: Sparkles,
                          },
                      ]
                    : []),
                {
                    title: t('settings.navigation.notifications'),
                    description: t(
                        'settings.navigation.descriptions.notifications',
                    ),
                    href: editNotifications(),
                    icon: Bell,
                },
            ],
        },
        {
            label: t('settings.navigation.groups.app'),
            items: [
                {
                    title: t('settings.navigation.modules'),
                    description: t('settings.navigation.descriptions.modules'),
                    href: editModules(),
                    icon: Blocks,
                },
            ],
        },
    ].filter((group) => group.items.length > 0),
);

const { isCurrentOrParentUrl } = useCurrentUrl();

const flatNavItems = computed<SettingsNavItem[]>(() =>
    navGroups.value.flatMap((group) => group.items),
);

/**
 * The section being viewed, so the header can name it.
 *
 * Every settings page used to sit under one heading that said "Settings",
 * with the only answer to "which page am I on" being a tinted nav link — and
 * two of the pages then hid a second `<h1>` behind `sr-only`, so a screen
 * reader heard the word twice and a sighted reader never saw it once. The
 * shell names the active section instead, and owns the page's only `<h1>`.
 *
 * Falls back to the generic title rather than rendering an empty heading: a
 * settings page reachable by URL but absent from the nav (a module switched
 * off mid-visit) still needs something above it.
 */
const activeItem = computed<SettingsNavItem | undefined>(() =>
    flatNavItems.value.find((item) => isCurrentOrParentUrl(item.href)),
);

const settingsLabel = computed(() =>
    navigationName('settings.title', 'navigation.settings_subtitle'),
);
</script>

<template>
    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-col bg-[#101010] px-[18px] py-[18px]"
    >
        <div class="w-full">
            <div class="mb-[18px]">
                <!-- Kicker, then the section name. The pair keeps "where am I
                     in the app" and "which page is this" both on screen, which
                     one line saying "Settings" on all ten pages could not. -->
                <p
                    class="text-[11px] font-medium tracking-[0.2em] text-[#6f6f6f] uppercase"
                >
                    {{ settingsLabel }}
                </p>
                <h1 class="mt-1 text-[22px] font-normal text-white">
                    {{ activeItem?.title ?? settingsLabel }}
                </h1>
                <p class="mt-1 max-w-[68ch] text-sm text-[#989898]">
                    {{ activeItem?.description ?? t('settings.description') }}
                </p>
            </div>

            <div class="flex flex-col gap-[18px] lg:flex-row lg:items-start">
                <!-- Below lg the sidebar became a ten-link stack that pushed the
                     page someone had just tapped an entire screen down. Same
                     links, laid out as one scrollable rail instead, so the
                     content starts where the header ends. -->
                <nav
                    class="-mx-[18px] overflow-x-auto px-[18px] pb-1 lg:hidden [&::-webkit-scrollbar]:hidden"
                    style="scrollbar-width: none"
                    :aria-label="t('settings.navigation.jump_to')"
                >
                    <div class="flex w-max items-center gap-2">
                        <template
                            v-for="(group, groupIndex) in navGroups"
                            :key="group.label"
                        >
                            <span
                                v-if="groupIndex > 0"
                                class="h-6 w-px shrink-0 bg-white/10"
                                aria-hidden="true"
                            />
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
                                    'inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-full px-4 text-sm whitespace-nowrap transition-colors duration-200',
                                    'focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#101010] focus-visible:outline-none',
                                    isCurrentOrParentUrl(item.href)
                                        ? 'bg-[#02CD86] font-medium text-[#101010]'
                                        : 'bg-[#1a1a1a] text-[#989898] ring-1 ring-white/10 hover:text-white',
                                ]"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ item.title }}
                            </Link>
                        </template>
                    </div>
                </nav>

                <!-- Settings nav. Sticky from lg up so it stays reachable while
                     a long pane scrolls; 92px clears the app header. -->
                <aside
                    class="hidden w-full shrink-0 lg:sticky lg:top-[92px] lg:block lg:w-60"
                >
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
                                        'relative flex min-h-11 w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors duration-200',
                                        'focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none',
                                        isCurrentOrParentUrl(item.href)
                                            ? 'bg-[#02CD86]/10 font-medium text-[#02CD86]'
                                            : 'text-[#989898] hover:bg-white/5 hover:text-white',
                                    ]"
                                >
                                    <!-- Second marker on the active link, so it
                                         is not tint alone doing the work for
                                         anyone who cannot separate the two
                                         greens. -->
                                    <span
                                        v-if="isCurrentOrParentUrl(item.href)"
                                        class="absolute inset-y-1.5 start-0 w-[3px] rounded-full bg-[#02CD86]"
                                        aria-hidden="true"
                                    />
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
