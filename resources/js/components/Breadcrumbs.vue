<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();

const { navigationName } = useNavigationNaming();

const breadcrumbTranslationKeys: Record<string, [string, string?]> = {
    Dashboard: ['navigation.dashboard', 'navigation.dashboard_subtitle'],
    Transactions: ['navigation.transactions'],
    Reports: ['navigation.report', 'navigation.report_subtitle'],
    Report: ['navigation.report', 'navigation.report_subtitle'],
    Investments: ['navigation.investments', 'navigation.investments_subtitle'],
    Portfolio: ['navigation.portfolio'],
    Goals: ['navigation.goals', 'navigation.goals_subtitle'],
    Budgets: ['navigation.budgets', 'navigation.budgets_subtitle'],
    Bills: ['navigation.bills'],
    Advisor: ['navigation.advisor', 'navigation.advisor_subtitle'],
    AI: ['navigation.ai_assistant', 'navigation.ai_assistant_subtitle'],
    Settings: ['settings.title', 'navigation.settings_subtitle'],
    Admin: ['settings.navigation.admin', 'settings.navigation.admin_subtitle'],
    Notifications: ['navigation.notifications'],
    Preferences: ['settings.preferences.title'],
    'Profile settings': ['settings.profile.title'],
    'Security settings': ['settings.security.title'],
    'Telegram settings': ['settings.telegram.title'],
    'Category settings': ['settings.categories.title'],
    'Asset settings': ['settings.assets.title'],
    'Appearance settings': ['settings.appearance.title'],
};

function breadcrumbTitle(title: string): string {
    const key = breadcrumbTranslationKeys[title];

    return key ? navigationName(key[0], key[1]) : title;
}
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage>{{
                            breadcrumbTitle(item.title)
                        }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href">{{
                                breadcrumbTitle(item.title)
                            }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
