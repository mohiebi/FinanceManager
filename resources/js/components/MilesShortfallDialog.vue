<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Gauge, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { index as milesIndex } from '@/routes/miles';
import type { MilesShortfall } from '@/types/miles';

const shortfall = ref<MilesShortfall | null>(null);

function receive(event: Event): void {
    shortfall.value = (event as CustomEvent<MilesShortfall>).detail;
}

function close(): void {
    shortfall.value = null;
}

onMounted(() => window.addEventListener('miles:shortfall', receive));
onBeforeUnmount(() => window.removeEventListener('miles:shortfall', receive));
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
                v-if="shortfall"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="miles-shortfall-title"
                @click.self="close"
            >
                <div
                    class="w-full max-w-sm rounded-2xl border border-white/10 bg-[#161616] p-6 text-white shadow-2xl"
                >
                    <div class="flex items-start justify-between gap-4">
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-[#02cd86]/12 text-[#02cd86]"
                        >
                            <Gauge class="size-5" aria-hidden="true" />
                        </span>
                        <button
                            type="button"
                            class="grid size-11 cursor-pointer place-items-center rounded-xl text-[#989898] transition-colors hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none"
                            aria-label="Close"
                            @click="close"
                        >
                            <X class="size-5" />
                        </button>
                    </div>
                    <h2
                        id="miles-shortfall-title"
                        class="mt-4 text-lg font-semibold"
                    >
                        {{ shortfall.shortfall }} more Miles needed
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-[#a3a3a3]">
                        This action costs {{ shortfall.cost }} Miles. You
                        currently have {{ shortfall.available }}.
                    </p>
                    <Link
                        :href="milesIndex()"
                        class="mt-6 inline-flex min-h-11 w-full cursor-pointer items-center justify-center rounded-xl bg-[#02cd86] px-4 text-sm font-semibold text-[#07130e] transition-colors hover:bg-[#32dda0] focus-visible:ring-2 focus-visible:ring-[#5eeeb5] focus-visible:ring-offset-2 focus-visible:ring-offset-[#161616] focus-visible:outline-none"
                        @click="close"
                    >
                        See ways to earn Miles
                    </Link>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
