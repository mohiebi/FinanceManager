<script setup lang="ts">
import type { Component } from 'vue';

/**
 * One titled block of a settings page, as its own card.
 *
 * Settings pages used to pour everything into a single container, which put a
 * name field and "delete my account" on the same surface. A section per card
 * gives each concern its own edge, and lets the destructive one look
 * destructive rather than relying on a red box inside a neutral panel.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        /**
         * Lucide component for the header badge.
         *
         * Optional because it is a scanning aid, not decoration to sprinkle
         * everywhere: it earns its place on pages long enough that the reader
         * arrives mid-scroll and needs to re-find where they are.
         */
        icon?: Component;
        /** Reserved for irreversible actions — red edge, red heading. */
        danger?: boolean;
    }>(),
    { description: undefined, icon: undefined, danger: false },
);
</script>

<template>
    <section
        class="rounded-[22px] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 sm:p-6"
        :class="
            danger
                ? 'bg-[#1a1414] ring-[#E94E50]/25'
                : 'bg-[#1a1a1a] ring-white/10'
        "
    >
        <header class="mb-5 flex items-start gap-3">
            <span
                v-if="icon"
                class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                :class="
                    danger
                        ? 'bg-[#E94E50]/10 text-[#E94E50]'
                        : 'bg-[#02CD86]/10 text-[#02CD86]'
                "
            >
                <component :is="icon" class="size-[18px]" aria-hidden="true" />
            </span>

            <div class="min-w-0 flex-1">
                <h2
                    class="text-[17px] font-medium"
                    :class="danger ? 'text-[#E94E50]' : 'text-white'"
                >
                    {{ title }}
                </h2>
                <!-- Capped at a readable measure even though the card is not: a
                     description running the full width of an ultrawide is a line
                     the eye cannot track back from. -->
                <p
                    v-if="description"
                    class="mt-1 max-w-[68ch] text-sm text-[#989898]"
                >
                    {{ description }}
                </p>
            </div>

            <!-- Section-level controls ("mark all read", "add category") belong
                 beside the heading they act on, not buried under the rows. -->
            <div v-if="$slots.actions" class="shrink-0">
                <slot name="actions" />
            </div>
        </header>

        <div class="space-y-1">
            <slot />
        </div>

        <footer v-if="$slots.footer" class="mt-5 border-t border-white/5 pt-5">
            <slot name="footer" />
        </footer>
    </section>
</template>
