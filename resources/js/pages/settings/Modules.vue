<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onKeyStroke } from '@vueuse/core';
import {
    Blocks,
    Bot,
    BrainCircuit,
    ChartPie,
    Check,
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
import SettingsSection from '@/components/settings/SettingsSection.vue';
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
    investments: ['navigation.investments', 'navigation.investments_subtitle'],
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
const pendingActivation = ref<ModuleCard | null>(null);

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
    if (next && module.activation_cost > 0) {
        pendingActivation.value = module;

        return;
    }

    // Switching a module off can cascade, and a module bought with Miles is
    // worth pausing over even when it cascades nowhere. Say either before it
    // happens rather than explaining it afterwards in a flash message.
    if (
        !next &&
        (module.disables.length > 0 || (module.paid && module.unlocked))
    ) {
        pendingDisable.value = module;

        return;
    }

    submit(module.key, { enabled: next });
}

function confirmActivation(): void {
    const module = pendingActivation.value;

    if (module === null || !module.can_afford) {
        return;
    }

    pendingActivation.value = null;
    submit(module.key, { enabled: true });
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

// Escape closes whichever dialog is open — a modal that only the mouse can
// dismiss traps anyone working from the keyboard.
onKeyStroke('Escape', () => {
    pendingActivation.value = null;
    pendingDisable.value = null;
});
</script>

<template>
    <Head :title="t('modules.title')" />

    <div class="flex flex-col gap-[18px]">
        <!-- The page used to open with its own uppercase eyebrow and
             description, which is now what the settings shell prints in the
             header above it — the same two lines, twice. -->
        <Transition
            enter-active-class="transition ease-in-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in-out motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <p
                v-if="props.status"
                class="flex items-center gap-2 rounded-2xl bg-[#02CD86]/10 px-4 py-3 text-sm text-[#02CD86] ring-1 ring-[#02CD86]/25"
                role="status"
                aria-live="polite"
            >
                <Check class="size-4 shrink-0" aria-hidden="true" />
                {{ props.status }}
            </p>
        </Transition>

        <SettingsSection
            :icon="Blocks"
            :title="t('modules.optional_heading')"
            :description="t('modules.description')"
        >
            <!-- One card on a phone, two from 640, three only past 1500px.
                 Three is the ceiling because column count, not card width, is
                 what has to give: a fourth column at laptop widths left each
                 card too narrow to hold its description without clipping. The
                 threshold sits higher than it used to because the grid now
                 lives inside a card, which costs it the card's padding. -->
            <ul class="grid gap-4 min-[1500px]:grid-cols-3 sm:grid-cols-2">
                <li
                    v-for="module in props.modules"
                    :key="module.key"
                    class="flex flex-col rounded-2xl bg-white/5 p-4 ring-1 transition-colors"
                    :class="
                        module.enabled ? 'ring-[#02CD86]/25' : 'ring-white/10'
                    "
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
                                aria-hidden="true"
                            />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p
                                class="text-sm font-medium break-words text-white"
                            >
                                {{ moduleLabel(module) }}
                            </p>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-1.5"
                            >
                                <span
                                    v-if="module.activation_cost > 0"
                                    class="inline-flex items-center gap-1 rounded-md bg-[#d9c48f]/12 px-2 py-0.5 text-[11px] font-medium text-[#d9c48f]"
                                >
                                    <Sparkles
                                        class="size-3"
                                        aria-hidden="true"
                                    />
                                    {{ module.activation_cost }} Miles
                                </span>
                                <span
                                    v-else-if="!module.enabled"
                                    class="rounded-md bg-[#02CD86]/10 px-2 py-0.5 text-[11px] font-medium text-[#02CD86]"
                                >
                                    {{ t('modules.tiers.free') }}
                                </span>
                                <span
                                    v-if="module.enabled"
                                    class="inline-flex items-center gap-1 text-[11px] text-[#02CD86]"
                                >
                                    <Check class="size-3" aria-hidden="true" />
                                    {{ t('modules.enabled') }}
                                </span>
                            </div>
                        </div>

                        <!-- Self-managed modules keep their header clear: the
                             manage link's label is long, and sitting here as a
                             shrink-0 sibling it squeezed the title until it
                             wrapped one letter per line. It lives in the footer
                             instead. -->
                        <Switch
                            v-if="!module.manage_url"
                            :checked="module.enabled"
                            :disabled="processing === module.key"
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

                    <!-- Pinned to the bottom so cards in a row line their
                         controls up regardless of how long each description
                         runs. -->
                    <div
                        v-if="module.manage_url"
                        class="mt-auto border-t border-white/5 pt-3"
                    >
                        <p class="text-xs text-[#6f6f6f]">
                            {{ t('modules.managed_elsewhere') }}
                        </p>
                        <Link
                            :href="module.manage_url"
                            class="mt-2 inline-flex min-h-9 cursor-pointer items-center rounded-xl bg-white/10 px-3 text-xs font-medium text-white transition-colors duration-200 hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
                        >
                            {{ t('modules.manage') }}
                        </Link>
                    </div>

                    <label
                        v-else-if="!module.enabled && module.in_nav"
                        class="mt-auto flex min-h-11 cursor-pointer items-center gap-2 border-t border-white/5 pt-3"
                    >
                        <Checkbox
                            :checked="!module.show_promo"
                            :disabled="processing === module.key"
                            @update:checked="
                                togglePromo(module, $event === true)
                            "
                        />
                        <span class="text-xs text-[#989898]">
                            {{ t('modules.hide_from_menu') }}
                        </span>
                    </label>
                </li>
            </ul>
        </SettingsSection>

        <SettingsSection
            :icon="Lock"
            :title="t('modules.core_heading')"
            :description="t('modules.core_description')"
        >
            <ul class="grid gap-4 min-[1500px]:grid-cols-3 sm:grid-cols-2">
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
                                aria-hidden="true"
                            />
                        </span>
                        <p
                            class="min-w-0 flex-1 text-sm font-medium break-words text-[#989898]"
                        >
                            {{ moduleLabel(module) }}
                        </p>
                        <Lock
                            class="mt-1 size-4 shrink-0 text-[#6f6f6f]"
                            aria-hidden="true"
                        />
                    </div>
                    <p class="mt-3 text-sm text-[#6f6f6f]">
                        {{ module.description }}
                    </p>
                </li>
            </ul>
        </SettingsSection>
    </div>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="pendingActivation"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="module-activation-title"
                @click.self="pendingActivation = null"
            >
                <div
                    class="w-full max-w-sm rounded-[16px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.5)] ring-1 ring-white/10"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-[#02cd86]/12 text-[#02cd86]"
                    >
                        <Sparkles class="size-5" aria-hidden="true" />
                    </span>
                    <p
                        id="module-activation-title"
                        class="mt-4 text-[17px] font-medium text-white"
                    >
                        {{
                            t('modules.activation.title', {
                                module: moduleLabel(pendingActivation),
                            })
                        }}
                    </p>
                    <p class="mt-2 text-sm text-[#989898]">
                        {{
                            t('modules.activation.body', {
                                features:
                                    pendingActivation.unlock_features.join(
                                        ', ',
                                    ),
                                miles: pendingActivation.activation_cost,
                            })
                        }}
                    </p>
                    <p
                        v-if="!pendingActivation.can_afford"
                        class="mt-3 rounded-xl bg-[#E94E50]/10 px-3 py-2 text-xs text-[#ff8d8f]"
                    >
                        {{
                            t('modules.activation.shortfall', {
                                miles: pendingActivation.shortfall,
                            })
                        }}
                    </p>
                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            class="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-xl bg-white/10 text-sm font-medium text-white transition hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
                            @click="pendingActivation = null"
                        >
                            {{ t('modules.cancel') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-xl bg-[#02cd86] text-center text-sm font-semibold text-[#07130e] transition hover:bg-[#32dda0] focus-visible:ring-2 focus-visible:ring-[#5eeeb5] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="!pendingActivation.can_afford"
                            @click="confirmActivation"
                        >
                            {{
                                t('modules.activation.confirm', {
                                    miles: pendingActivation.activation_cost,
                                })
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <!-- The upgrade dialog next door was already announcing itself;
                 this one was a bare div, so a screen reader met an unlabelled
                 blob of text with a destructive button in it. -->
            <div
                v-if="pendingDisable"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="module-disable-title"
                @click.self="pendingDisable = null"
            >
                <div
                    class="w-full max-w-sm rounded-[16px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.5)] ring-1 ring-white/10"
                >
                    <p
                        id="module-disable-title"
                        class="text-[17px] font-medium text-white"
                    >
                        {{
                            t('modules.confirm_disable_title', {
                                module: moduleLabel(pendingDisable),
                            })
                        }}
                    </p>
                    <p class="mt-2 text-sm text-[#989898]">
                        <template v-if="pendingDisable.disables.length > 0">
                            {{
                                t('modules.confirm_disable_body', {
                                    features:
                                        pendingDisable.disables.join(', '),
                                })
                            }}
                        </template>
                        <template v-else>
                            {{ t('modules.confirm_disable_body_simple') }}
                        </template>
                        <template v-if="pendingDisable.paid">
                            {{ t('modules.confirm_disable_paid') }}
                        </template>
                    </p>
                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            class="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-xl bg-white/10 text-sm font-medium text-white transition hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
                            @click="pendingDisable = null"
                        >
                            {{ t('modules.cancel') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-xl bg-[#E94E50] text-sm font-medium text-white transition hover:bg-[#d43e40] focus-visible:ring-2 focus-visible:ring-[#E94E50] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
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
