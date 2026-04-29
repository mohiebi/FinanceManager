<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';
import authIllustration from '../../../img/auth/investor-illustration-dark.png';
import authLogo from '../../../img/auth/logo.png';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        caption?: string;
    }>(),
    {
        caption: '',
    },
);
</script>

<template>
    <div
        class="auth-theme min-h-svh bg-[var(--auth-page-bg)] px-3 py-4 sm:px-4 lg:px-6 lg:py-6"
    >
        <div
            class="mx-auto flex min-h-[calc(100svh-2rem)] max-w-[1230px] items-center"
        >
            <div
                class="grid w-full gap-4 rounded-[2rem] border-[11px] border-white bg-white lg:grid-cols-[minmax(0,1fr)_minmax(31rem,0.92fr)]"
            >
                <aside
                    class="auth-surface flex min-h-[20rem] flex-col bg-[#0B191D] p-6 text-white sm:p-8 lg:min-h-[41rem] lg:p-10"
                >
                    <p class="text-[0.95rem] font-medium text-white/90">
                        All in one simple, powerful place
                    </p>

                    <div
                        class="flex flex-1 items-center justify-center py-6 lg:py-8"
                    >
                        <img
                            :src="authIllustration"
                            alt="Investor illustration"
                            class="w-full max-w-[25rem] object-contain"
                        />
                    </div>

                    <div class="max-w-sm space-y-4">
                        <h2
                            class="max-w-[17rem] text-[clamp(3.1rem,6vw,4.4rem)] leading-[0.9] font-semibold tracking-[-0.06em]"
                        >
                            Your Money, on Autopilot
                        </h2>
                        <p
                            class="max-w-[20rem] text-sm leading-6 text-white/82 sm:text-base"
                        >
                            See your finances clearly, stay in control, and make
                            smarter decisions without the guesswork.
                        </p>
                    </div>
                </aside>

                <section
                    class="auth-surface flex min-h-[20rem] px-5 py-6 sm:px-8 sm:py-8 lg:min-h-[41rem] lg:px-12 lg:py-10"
                >
                    <div class="mx-auto flex w-full max-w-[31rem] flex-col">
                        <Link
                            :href="home()"
                            class="mx-auto inline-flex items-center"
                        >
                            <img
                                :src="authLogo"
                                alt="App logo"
                                class="h-8 w-auto sm:h-9"
                            />
                            <span class="sr-only">{{ title }}</span>
                        </Link>

                        <div
                            class="flex flex-1 flex-col justify-center py-8 sm:py-10"
                        >
                            <div class="space-y-2 text-center">
                                <h1
                                    class="text-[2.2rem] font-semibold tracking-[-0.05em]"
                                >
                                    {{ title }}
                                </h1>
                                <p
                                    class="auth-copy-muted text-sm leading-6 sm:text-[0.98rem]"
                                >
                                    {{ description }}
                                </p>
                            </div>

                            <div class="mt-12 sm:mt-14">
                                <slot />
                            </div>
                        </div>

                        <p
                            v-if="caption"
                            class="auth-copy-caption pt-4 text-center text-sm font-medium"
                        >
                            {{ caption }}
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
