<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AuthIllustration from '@/components/auth/AuthIllustration.vue';
import { home } from '@/routes';
import authLogo from '../../../img/Logo-green.svg';

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
        class="auth-theme min-h-svh bg-[var(--auth-page-bg)] px-3 py-4 sm:px-4 lg:px-6 lg:py-0"
    >
        <div
            class="mx-auto flex min-h-[calc(100svh-2rem)] max-w-[1318px] items-center lg:min-h-svh"
        >
            <div
                class="grid w-full gap-0 overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 lg:h-[min(894px,calc(100svh-4rem))] lg:min-h-[760px] lg:grid-cols-[minmax(0,688px)_minmax(0,1fr)]"
            >
                <aside
                    class="flex min-h-[20rem] flex-col bg-[#161616] p-6 text-white sm:p-8 lg:min-h-0 lg:border-r lg:border-white/10 lg:px-10 lg:py-[35px]"
                >
                    <p class="text-[0.95rem] font-medium text-white/90">
                        All in one simple, powerful place
                    </p>

                    <div
                        class="flex flex-1 items-center justify-center py-6 lg:pt-[52px]"
                    >
                        <AuthIllustration
                            class="w-full max-w-[420px] object-contain"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="space-y-4 lg:max-w-none">
                        <h2
                            class="text-[70px] leading-[1] font-[500] tracking-normal lg:whitespace-nowrap"
                        >
                            Your <br />
                            Money, <br />
                            on Autopilot
                        </h2>
                        <p
                            class="text-[20px] leading-6 font-[400] text-white/82"
                        >
                            See your finances clearly, stay in control, <br />
                            and make smarter decisions without the guesswork.
                        </p>
                    </div>
                </aside>

                <section
                    class="auth-surface flex min-h-[20rem] px-5 py-6 sm:px-8 sm:py-8 lg:min-h-0 lg:rounded-none lg:px-[100px] lg:py-[35px]"
                >
                    <div class="mx-auto flex w-full max-w-[418px] flex-col">
                        <Link
                            :href="home()"
                            class="mx-auto inline-flex items-center"
                        >
                            <img
                                :src="authLogo"
                                alt="App logo"
                                class="h-[45px] w-auto fill-black"
                            />
                            <span class="sr-only">{{ title }}</span>
                        </Link>

                        <div class="flex flex-1 flex-col pt-20 sm:pt-[86px]">
                            <div class="space-y-2 text-center">
                                <h1
                                    class="leading-tight font-medium tracking-normal"
                                    :class="
                                        title === 'Check your email'
                                            ? 'text-[30px]'
                                            : 'text-[35px]'
                                    "
                                >
                                    {{ title }}
                                </h1>
                                <p
                                    class="auth-copy-muted text-base leading-normal font-normal"
                                >
                                    {{ description }}
                                </p>
                            </div>

                            <div class="mt-[92px]">
                                <slot />
                            </div>
                        </div>

                        <p
                            v-if="caption"
                            class="auth-copy-caption pt-4 text-center text-base font-normal"
                        >
                            {{ caption }}
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
