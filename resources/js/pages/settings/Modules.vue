<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bot,
    ChartPie,
    Lock,
    Plane,
    Receipt,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { ref } from 'vue';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import { update as updateModules } from '@/routes/modules';
import type { CoreModuleCard, FeatureKey, ModuleCard } from '@/types/features';

const props = defineProps<{
    modules: ModuleCard[];
    coreModules: CoreModuleCard[];
    status: string | null;
}>();

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Modules', href: '/settings/modules' }],
    },
});

const icons: Record<string, Component> = {
    Bot,
    ChartPie,
    Plane,
    Receipt,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    TrendingUp,
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

        <ul class="space-y-3">
            <li
                v-for="module in props.modules"
                :key="module.key"
                class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10"
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
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-white">
                                {{ module.label }}
                            </p>
                            <span
                                v-if="module.tier !== 'free'"
                                class="rounded-md bg-[#02CD86]/10 px-2 py-0.5 text-[11px] font-medium text-[#02CD86]"
                            >
                                {{ t('modules.tiers.pro') }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-[#989898]">
                            {{ module.description }}
                        </p>
                        <p
                            v-if="module.requires.length > 0"
                            class="mt-1.5 text-xs text-[#6f6f6f]"
                        >
                            {{
                                t('modules.requires', {
                                    features: module.requires.join(', '),
                                })
                            }}
                        </p>
                    </div>

                    <!-- Self-managed modules are advertised here but switched on
                         their own page, because arming one re-keys the user's data
                         and cannot be done by a plain toggle. -->
                    <Link
                        v-if="module.manage_url"
                        :href="module.manage_url"
                        class="shrink-0 rounded-xl bg-white/10 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-white/15"
                    >
                        {{ t('modules.manage') }}
                    </Link>
                    <Switch
                        v-else
                        :checked="module.enabled"
                        :disabled="processing === module.key || !module.may_use"
                        :aria-label="module.label"
                        @update:checked="toggle(module, $event)"
                    />
                </div>

                <p
                    v-if="module.manage_url"
                    class="mt-3 border-t border-white/5 pt-3 text-xs text-[#6f6f6f]"
                >
                    {{ t('modules.managed_elsewhere') }}
                </p>

                <label
                    v-else-if="!module.enabled && module.in_nav"
                    class="mt-3 flex cursor-pointer items-center gap-2 border-t border-white/5 pt-3"
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
            <ul class="space-y-3">
                <li
                    v-for="module in props.coreModules"
                    :key="module.key"
                    class="flex items-start gap-3 rounded-2xl bg-white/[0.02] p-4 ring-1 ring-white/5"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/5 text-[#6f6f6f]"
                    >
                        <component
                            :is="icons[module.icon]"
                            class="size-[18px]"
                        />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-[#989898]">
                            {{ module.label }}
                        </p>
                        <p class="mt-1 text-sm text-[#6f6f6f]">
                            {{ module.description }}
                        </p>
                    </div>
                    <Lock class="mt-1 size-4 shrink-0 text-[#6f6f6f]" />
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
                                module: pendingDisable.label,
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
