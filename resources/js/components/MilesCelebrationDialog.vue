<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { MilesClaimed } from '@/types/miles';

/**
 * The moment a daily reward lands.
 *
 * Mounted at the layout because a claim can be made from the header pill on
 * any page as well as from the hub itself, and both deserve the same moment.
 * It listens for an event rather than taking a prop for the same reason.
 *
 * The confetti is decorative and marked aria-hidden; the reward itself is
 * ordinary text, so a screen reader hears the number rather than the party.
 */
const claimed = ref<MilesClaimed | null>(null);
const { t } = useI18n();

/** Fixed offsets rather than random ones, so every claim looks the same. */
const confetti = [
    { left: '12%', delay: '0ms', color: '#02cd86' },
    { left: '28%', delay: '90ms', color: '#a89bf3' },
    { left: '44%', delay: '40ms', color: '#5eeeb5' },
    { left: '60%', delay: '140ms', color: '#f5c451' },
    { left: '76%', delay: '20ms', color: '#02cd86' },
    { left: '90%', delay: '110ms', color: '#a89bf3' },
];

function receive(event: Event): void {
    claimed.value = (event as CustomEvent<MilesClaimed>).detail;
}

function close(): void {
    claimed.value = null;
}

onMounted(() => window.addEventListener('miles:claimed', receive));
onBeforeUnmount(() => window.removeEventListener('miles:claimed', receive));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="claimed"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="miles-celebration-title"
                @click.self="close"
            >
                <div
                    class="relative w-full max-w-sm overflow-hidden rounded-2xl border border-[#02cd86]/25 bg-[#161616] p-6 text-center text-white shadow-2xl"
                >
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 h-24"
                        aria-hidden="true"
                    >
                        <span
                            v-for="piece in confetti"
                            :key="piece.left"
                            class="miles-confetti absolute top-0 block h-2 w-1.5 rounded-sm"
                            :style="{
                                left: piece.left,
                                animationDelay: piece.delay,
                                backgroundColor: piece.color,
                            }"
                        />
                    </div>

                    <span
                        class="miles-celebration-badge mx-auto grid size-14 place-items-center rounded-2xl bg-[#02cd86]/12 text-[#02cd86]"
                    >
                        <Sparkles class="size-7" aria-hidden="true" />
                    </span>

                    <p class="mt-4 text-3xl font-bold text-[#5eeeb5]">
                        {{
                            t('miles.celebration.reward', {
                                miles: claimed.miles,
                            })
                        }}
                    </p>
                    <h2
                        id="miles-celebration-title"
                        class="mt-1 text-lg font-semibold"
                    >
                        {{
                            t('miles.celebration.title', { step: claimed.step })
                        }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-[#a3a3a3]">
                        {{
                            t('miles.celebration.body', {
                                balance: claimed.balance,
                            })
                        }}
                        <template v-if="claimed.step >= 7">
                            {{ t('miles.celebration.cycle_done') }}
                        </template>
                        <template v-else-if="claimed.nextReward > 0">
                            {{
                                t('miles.celebration.body_next', {
                                    miles: claimed.nextReward,
                                })
                            }}
                        </template>
                    </p>

                    <button
                        type="button"
                        class="mt-6 inline-flex min-h-11 w-full cursor-pointer items-center justify-center rounded-xl bg-[#02cd86] px-4 text-sm font-semibold text-[#07130e] transition-colors hover:bg-[#32dda0] focus-visible:ring-2 focus-visible:ring-[#5eeeb5] focus-visible:ring-offset-2 focus-visible:ring-offset-[#161616] focus-visible:outline-none"
                        autofocus
                        @click="close"
                    >
                        {{ t('miles.celebration.dismiss') }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
