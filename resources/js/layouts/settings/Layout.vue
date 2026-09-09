<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Blocks,
    Bot,
    Coins,
    CreditCard,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
    Tags,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    /** Matches `settings.navigation.descriptions.*`, shown in search results. */
    description: string;
};

type SettingsNavGroup = {
    id: string;
    label: string;
    /** One line describing what lives in the group — the v3 tab card's summary. */
    summary: string;
    items: SettingsNavItem[];
};

/**
 * Four tab cards, not ten links in a sidebar.
 *
 * The v3 design collapses every settings page into four groups (Account /
 * Money / Connections / App), shown as a grid of cards up top rather than a
 * nav rail down the side — this file still routes to eleven real pages
 * underneath (keeping every page's own controller, form and dialog intact),
 * it just changes what gets you there. A group also survives a module being
 * switched off: an empty one drops out rather than leaving a stray card.
 */
const navGroups = computed<SettingsNavGroup[]>(() =>
    [
        {
            id: 'account',
            label: t('settings.navigation.groups.account'),
            summary: t('settings.navigation.groups_summary.account'),
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
            id: 'money',
            label: t('settings.navigation.groups.money'),
            summary: t('settings.navigation.groups_summary.money'),
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
            id: 'connections',
            label: t('settings.navigation.groups.connections'),
            summary: t('settings.navigation.groups_summary.connections'),
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
            id: 'app',
            label: t('settings.navigation.groups.app'),
            summary: t('settings.navigation.groups_summary.app'),
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

type FlatSettingsNavItem = SettingsNavItem & { groupLabel: string };

const flatNavItems = computed<FlatSettingsNavItem[]>(() =>
    navGroups.value.flatMap((group) =>
        group.items.map((item) => ({ ...item, groupLabel: group.label })),
    ),
);

/** The tab whose card is tinted green, and whose pages fill the sub-nav. */
const activeGroup = computed<SettingsNavGroup | undefined>(() =>
    navGroups.value.find((group) =>
        group.items.some((item) => isCurrentOrParentUrl(item.href)),
    ),
);

const settingsLabel = computed(() =>
    navigationName('settings.title', 'navigation.settings_subtitle'),
);

/**
 * Live search across every settings page's title and description.
 *
 * The v3 mock searches every row on one in-memory page; these eleven pages
 * are separate routes with their own controllers, so a row-level index isn't
 * available without loading all of them up front. Searching the page index
 * instead — title, description, and group — still answers "where do I turn
 * this off" from a couple of letters, just one level coarser than the mock.
 */
const query = ref('');
const searching = computed(() => query.value.trim().length > 1);
const needle = computed(() => query.value.trim().toLowerCase());

const searchResults = computed<FlatSettingsNavItem[]>(() => {
    if (!searching.value) {
        return [];
    }

    return flatNavItems.value.filter((item) =>
        `${item.title} ${item.description} ${item.groupLabel}`
            .toLowerCase()
            .includes(needle.value),
    );
});

const searchSummary = computed(() => {
    const count = searchResults.value.length;

    return t(
        count === 1
            ? 'settings.search.matches_one'
            : 'settings.search.matches_many',
        {
            count,
            query: query.value.trim(),
        },
    );
});
</script>

<template>
    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-col bg-[#101010] px-[18px] py-[18px]"
    >
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-white"
                    >
                        {{ settingsLabel }}
                    </h1>
                    <p class="mt-1 max-w-[60ch] text-sm text-[#989898]">
                        {{ t('settings.description') }}
                    </p>
                </div>

                <div class="relative w-full max-w-xs">
                    <Search
                        class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-[#686868]"
                        aria-hidden="true"
                    />
                    <input
                        v-model="query"
                        type="search"
                        :placeholder="t('settings.search.placeholder')"
                        :aria-label="t('settings.search.placeholder')"
                        class="settings-input h-11 w-full rounded-xl ps-10 pe-3 text-sm"
                    />
                </div>
            </div>

            <template v-if="searching">
                <p class="text-sm text-[#989898]" aria-live="polite">
                    {{ searchSummary }}
                </p>

                <div
                    v-if="searchResults.length > 0"
                    class="flex flex-col gap-2"
                >
                    <Link
                        v-for="item in searchResults"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-xl bg-[#1a1a1a] px-4 py-3 ring-1 ring-white/10 transition-colors duration-200 hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#101010] focus-visible:outline-none"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#02CD86]/10 text-[#02CD86]"
                        >
                            <component
                                :is="item.icon"
                                class="size-[18px]"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="text-sm font-medium text-white">{{
                                    item.title
                                }}</span>
                                <span
                                    class="text-[11px] font-medium tracking-[0.12em] text-[#6f6f6f] uppercase"
                                    >{{ item.groupLabel }}</span
                                >
                            </span>
                            <span
                                class="mt-0.5 block truncate text-xs text-[#989898]"
                                >{{ item.description }}</span
                            >
                        </span>
                    </Link>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center gap-1 rounded-2xl border border-white/10 px-6 py-12 text-center"
                >
                    <p class="text-sm font-medium text-white">
                        {{ t('settings.search.empty_title') }}
                    </p>
                    <p class="max-w-[46ch] text-xs text-[#989898]">
                        {{ t('settings.search.empty_body') }}
                    </p>
                </div>
            </template>

            <template v-else>
                <!-- The 2x2 grid of tab cards. Each links straight to the
                     group's first page; the active group's card gets the
                     green tint the v3 design uses for the selected tab. -->
                <div
                    class="flex gap-2 overflow-x-auto pb-1 sm:grid sm:grid-cols-2 sm:gap-3 lg:grid-cols-4"
                >
                    <Link
                        v-for="group in navGroups"
                        :key="group.id"
                        :href="group.items[0].href"
                        :aria-current="
                            activeGroup?.id === group.id ? 'page' : undefined
                        "
                        :class="[
                            'min-h-11 shrink-0 rounded-xl px-3 py-2 text-start transition-colors duration-200 sm:p-4',
                            'focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#101010] focus-visible:outline-none',
                            activeGroup?.id === group.id
                                ? 'bg-[#02CD86]/8 inset-ring-1 inset-ring-[#02CD86]/40'
                                : 'bg-[#1a1a1a] inset-ring-1 inset-ring-white/10 hover:bg-white/[0.06]',
                        ]"
                    >
                        <span class="flex items-center justify-between gap-2">
                            <span
                                class="text-sm font-medium"
                                :class="
                                    activeGroup?.id === group.id
                                        ? 'text-[#02CD86]'
                                        : 'text-white'
                                "
                                >{{ group.label }}</span
                            >
                            <span class="text-xs text-[#989898] tabular-nums">{{
                                group.items.length
                            }}</span>
                        </span>
                        <p
                            class="mt-2 hidden truncate text-xs text-[#989898] sm:block"
                        >
                            {{ group.summary }}
                        </p>
                    </Link>
                </div>

                <!-- Which real page inside the active tab. Below lg and above
                     it alike — there is no longer a sidebar to compete with
                     for space, so one scrollable pill row covers every
                     width. -->
                <nav
                    v-if="activeGroup && activeGroup.items.length > 1"
                    class="-mx-[18px] overflow-x-auto px-[18px] pb-1 [&::-webkit-scrollbar]:hidden"
                    style="scrollbar-width: none"
                    :aria-label="t('settings.navigation.jump_to')"
                >
                    <div class="flex w-max items-center gap-2">
                        <Link
                            v-for="item in activeGroup.items"
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
                    </div>
                </nav>

                <!-- No card of its own any more: each page composes its own
                     SettingsSection cards, so a form and a destructive action
                     stop sharing one surface. SettingsRow is what keeps
                     controls readable inside it. -->
                <div class="min-w-0 flex-1 space-y-[18px]">
                    <slot />
                </div>
            </template>
        </div>
    </div>
</template>
