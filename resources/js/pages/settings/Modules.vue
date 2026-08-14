<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bot,
    BrainCircuit,
    ChartPie,
    Lock,
    Plane,
    Receipt,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    Target,
    TrendingUp,
    Trophy,
    Wallet,
} from 'lucide-vue-next';
import { ref } from 'vue';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { update as updateModules } from '@/routes/modules';
import type { CoreModuleCard, FeatureKey, ModuleCard } from '@/types/features';

const props = defineProps<{
    modules: ModuleCard[];
    coreModules: CoreModuleCard[];
    status: string | null;
}>();

const { t } = useI18n();
const { navigationName } = useNavigationNaming();

const moduleNavigationKeys: Partial<
    Record<FeatureKey, [standardKey: string, flightKey?: string]>
> = {
    reports: ['navigation.report', 'navigation.report_subtitle'],
    investments: [
        'navigation.investments',
        'navigation.investments_subtitle',
    ],
    goals: ['navigation.goals', 'navigation.goals_subtitle'],
    budgets: ['navigation.budgets', 'navigation.budgets_subtitle'],
    advisor: ['navigation.advisor', 'navigation.advisor_subtitle'],
    ai_assistant: [
        'navigation.ai_assistant',
        'navigation.ai_assistant_subtitle',
    ],
};

function moduleLabel(module: ModuleCard | CoreModuleCard): string {
    const keys = moduleNavigationKeys[module.key];

    return keys ? navigationName(keys[0], keys[1]) : module.label;
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Modules', href: '/settings/modules' }],
    },
});

/**
 * Every name App\Enums\Feature::icon() can return.
 *
 * A missing entry renders an empty square rather than failing, so this has to be
 * kept in step by hand whenever a module is added.
 */
const icons: Record<string, Component> = {
    Bot,
    BrainCircuit,
    ChartPie,
    Plane,
    Receipt,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    Target,
    TrendingUp,
    Trophy,
    Wallet,
};

const processing = ref<FeatureKey | null>(null);
const pendingDisable = ref<ModuleCard | null>(null);

function submit(feature: FeatureKey, payload: Record<string, boolean>): void {
    processing.value = feature;

    router.patch(
        updateModules().url,
        { feature, ...payload },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = null;
            },
        },
    );
}

function toggle(module: ModuleCard, next: boolean): void {
    // Switching a module off can cascade. Say so before it happens rather than
    // explaining it afterwards in a flash message.
    if (!next && module.disables.length > 0) {
        pendingDisable.value = module;

        return;
    }

    submit(module.key, { enabled: next });
}

function confirmDisable(): void {
    const module = pendingDisable.value;

    if (module === null) {
        return;
    }

    pendingDisable.value = null;
    submit(module.key, { enabled: false });
}

function togglePromo(module: ModuleCard, hidden: boolean): void {
    submit(module.key, { show_promo: !hidden });
}
</script>

<template>
    <Head :title="t('modules.title')" />

    <div class="space-y-6">
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('modules.title') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('modules.description') }}
            </p>
        </div>

        <Transition
            enter-active-class="transition ease-in-out"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in-out"
            leave-to-class="opacity-0"
        >
            <p v-if="props.status" class="text-sm text-[#02CD86]">
                {{ props.status }}
            </p>
        </Transition>

        <!-- One card on a phone, two from 640, three from 1280 — and three
             stays the ceiling until a genuinely ultrawide 1800px, not
             Tailwind's `2xl` (1536px), which is just an ordinary laptop and
             was cramming a fourth column into too little width per card,
             clipping the description. Column count rather than card width
             does the work, so a card never stretches past the point where
             its description stops being scannable. -->
        <ul
            class="grid gap-4 min-[1800px]:grid-cols-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <li
                v-for="module in props.modules"
                :key="module.key"
                class="flex flex-col rounded-2xl bg-white/5 p-4 ring-1 transition-colors"
                :class="module.enabled ? 'ring-[#02CD86]/25' : 'ring-white/10'"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl transition-colors"
                        :class="
                            module.enabled
                                ? 'bg-[#02CD86]/10 text-[#02CD86]'
                                : 'bg-white/5 text-[#989898]'
                        "
                    >
                        <component
                            :is="icons[module.icon]"
                            class="size-[18px]"
                        />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium break-words text-white">
                            {{ moduleLabel(module) }}
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <!-- Every module is free today, but that is only
                                 obvious once it says so — a switched-off card
                                 otherwise reads as something withheld. -->
                            <span
                                v-if="!module.enabled"
                                class="rounded-md px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    module.tier === 'free'
                                        ? 'bg-[#02CD86]/10 text-[#02CD86]'
                                        : 'bg-[#6C4EE9]/15 text-[#a89bf3]'
                                "
                            >
                                {{ t(`modules.tiers.${module.tier}`) }}
                            </span>
                            <span v-else class="text-[11px] text-[#02CD86]">
                                {{ t('modules.enabled') }}
                            </span>
                        </div>
                    </div>

                    <!-- Self-managed modules keep their header clear: the manage
                         link's label is long, and sitting here as a shrink-0
                         sibling it squeezed the title until it wrapped one
                         letter per line. It lives in the footer instead. -->
                    <Switch
                        v-if="!module.manage_url"
                        :checked="module.enabled"
                        :disabled="processing === module.key || !module.may_use"
                        :aria-label="moduleLabel(module)"
                        @update:checked="toggle(module, $event)"
                    />
                    <Lock
                        v-else
                        class="mt-1 size-4 shrink-0 text-[#6f6f6f]"
                        aria-hidden="true"
                    />
                </div>

                <p class="mt-3 text-sm text-[#989898]">
                    {{ module.description }}
                </p>

                <p
                    v-if="module.requires.length > 0"
                    class="mt-2 text-xs text-[#6f6f6f]"
                >
                    {{
                        t('modules.requires', {
                            features: module.requires.join(', '),
                        })
                    }}
                </p>

                <!-- Pinned to the bottom so cards in a row line their controls
                     up regardless of how long each description runs. -->
                <div
                    v-if="module.manage_url"
                    class="mt-auto border-t border-white/5 pt-3"
                >
                    <p class="text-xs text-[#6f6f6f]">
                        {{ t('modules.managed_elsewhere') }}
                    </p>
                    <Link
                        :href="module.manage_url"
                        class="mt-2 inline-flex cursor-pointer items-center rounded-xl bg-white/10 px-3 py-2 text-xs font-medium text-white transition-colors duration-200 hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
                    >
                        {{ t('modules.manage') }}
                    </Link>
                </div>

                <label
                    v-else-if="!module.enabled && module.in_nav"
                    class="mt-auto flex cursor-pointer items-center gap-2 border-t border-white/5 pt-3"
                >
                    <Checkbox
                        :checked="!module.show_promo"
                        :disabled="processing === module.key"
                        @update:checked="togglePromo(module, $event === true)"
                    />
                    <span class="text-xs text-[#989898]">
                        {{ t('modules.hide_from_menu') }}
                    </span>
                </label>
            </li>
        </ul>

        <div class="space-y-3">
            <p class="text-xs text-[#6f6f6f]">
                {{ t('modules.core_badge') }}
            </p>
            <ul
                class="grid gap-4 min-[1800px]:grid-cols-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <li
                    v-for="module in props.coreModules"
                    :key="module.key"
                    class="flex flex-col rounded-2xl bg-white/[0.02] p-4 ring-1 ring-white/5"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/5 text-[#6f6f6f]"
                        >
                            <component
                                :is="icons[module.icon]"
                                class="size-[18px]"
                            />
                        </span>
                        <p
                            class="min-w-0 flex-1 text-sm font-medium break-words text-[#989898]"
                        >
                            {{ moduleLabel(module) }}
                        </p>
                        <Lock class="mt-1 size-4 shrink-0 text-[#6f6f6f]" />
                    </div>
                    <p class="mt-3 text-sm text-[#6f6f6f]">
                        {{ module.description }}
                    </p>
                </li>
            </ul>
        </div>
    </div>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="pendingDisable"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="pendingDisable = null"
            >
                <div
                    class="w-full max-w-sm rounded-[22px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.5)] ring-1 ring-white/10"
                >
                    <p class="text-[17px] font-medium text-white">
                        {{
                            t('modules.confirm_disable_title', {
                                module: moduleLabel(pendingDisable),
                            })
                        }}
                    </p>
                    <p class="mt-2 text-sm text-[#989898]">
                        {{
                            t('modules.confirm_disable_body', {
                                features: pendingDisable.disables.join(', '),
                            })
                        }}
                    </p>
                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-xl bg-white/10 py-2.5 text-sm font-medium text-white transition hover:bg-white/15"
                            @click="pendingDisable = null"
                        >
                            {{ t('modules.cancel') }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-xl bg-[#E94E50] py-2.5 text-sm font-medium text-white transition hover:bg-[#d43e40]"
                            @click="confirmDisable"
                        >
                            {{ t('modules.confirm_disable_action') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
