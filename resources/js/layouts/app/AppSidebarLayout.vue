<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { providePageSubtitle } from '@/composables/usePageSubtitle';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

// Provided here rather than read from a prop: the subtitle is per-page data
// (an entry count, a days-left figure) that only the routed page component
// inside the default slot below can compute, not something passed down from
// this layout's own caller. See usePageSubtitle().
const subtitle = providePageSubtitle();
</script>

<template>
    <AppShell variant="sidebar">
        <div class="hidden lg:block">
            <AppSidebar />
        </div>
        <AppContent
            variant="sidebar"
            class="app-page-scroll app-scroll-thin h-svh min-h-0 overflow-x-hidden overflow-y-auto pb-4"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" :subtitle="subtitle" />
            <!-- Caps content width on wide monitors, same as the v3 design's
                 1440px content frame — a full-bleed grid of cards past that
                 width stops reading as a page and starts reading as a table.
                 Centered within the space beside the sidebar, not the whole
                 viewport. -->
            <div class="mx-auto w-full max-w-[1440px]">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
