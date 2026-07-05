<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();

const { t } = useI18n();

const breadcrumbTranslationKeys: Record<string, string> = {
    Dashboard: 'navigation.dashboard',
    Transactions: 'navigation.transactions',
    Reports: 'finance.reports.title',
    Investments: 'navigation.investments',
    Portfolio: 'navigation.portfolio',
    Bills: 'navigation.bills',
    Notifications: 'navigation.notifications',
    Preferences: 'settings.preferences.title',
    'Profile settings': 'settings.profile.title',
    'Security settings': 'settings.security.title',
    'Telegram settings': 'settings.telegram.title',
    'Category settings': 'settings.categories.title',
    'Asset settings': 'settings.assets.title',
    'Appearance settings': 'settings.appearance.title',
};

function breadcrumbTitle(title: string): string {
    const key = breadcrumbTranslationKeys[title];

    return key ? t(key) : title;
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
