<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthIllustration from '@/components/auth/AuthIllustration.vue';
import AuthIllustrationDe from '@/components/auth/AuthIllustrationDe.vue';
import AuthIllustrationFa from '@/components/auth/AuthIllustrationFa.vue';
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

const page = usePage();
const { t } = useI18n();

const locale = computed(
    () => (page.props.locale as string | undefined) ?? 'en',
);

const illustration = computed(() => {
    if (locale.value === 'fa') {
        return AuthIllustrationFa;
    }

    if (locale.value === 'de') {
        return AuthIllustrationDe;
    }

    return AuthIllustration;
});
</script>

<template>
    <div
        class="auth-theme min-h-svh bg-[var(--auth-page-bg)] px-4 py-6 sm:px-6 lg:py-8"
    >
        <Head>
            <meta head-key="robots" name="robots" content="noindex,follow" />
        </Head>

        <div
            class="mx-auto flex min-h-[calc(100svh-3rem)] max-w-[1100px] items-center lg:min-h-[calc(100svh-4rem)]"
        >
            <div
                class="grid w-full gap-0 overflow-hidden rounded-2xl bg-[var(--auth-frame-bg)] ring-1 ring-white/10 lg:min-h-[640px] lg:grid-cols-2"
            >
                <aside
                    class="order-last flex min-h-[20rem] flex-col bg-[#161616] p-6 text-white sm:p-8 lg:order-none lg:min-h-0 lg:border-r lg:border-white/10 lg:px-10 lg:py-[35px]"
                >
                    <p class="text-[0.95rem] font-medium text-white/90">
                        {{ t('landing.auth_panel.tagline') }}
                    </p>

                    <div class="flex flex-1 items-center justify-center py-8">
                        <component
                            :is="illustration"
                            class="w-full max-w-[300px] object-contain"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="space-y-4 lg:max-w-none">
                        <h2
                            class="text-4xl leading-[1.05] font-medium tracking-tight"
                        >
                            {{ t('landing.auth_panel.headline_1') }} <br />
                            {{ t('landing.auth_panel.headline_2') }} <br />
                            {{ t('landing.auth_panel.headline_3') }}
                        </h2>
                        <p
                            class="text-base leading-6 font-normal text-white/80"
                        >
                            {{ t('landing.auth_panel.description') }}
                        </p>
                    </div>
                </aside>

                <section
                    class="auth-surface order-first flex min-h-[20rem] items-center px-5 py-8 sm:px-8 lg:order-none lg:min-h-0 lg:rounded-none lg:px-[60px] lg:py-[35px]"
                >
                    <div class="mx-auto flex w-full max-w-[418px] flex-col">
                        <Link
                            :href="home()"
                            class="mx-auto inline-flex items-center"
                        >
                            <img
                                :src="authLogo"
                                alt="App logo"
                                class="h-10 w-auto"
                            />
                            <span class="sr-only">{{ title }}</span>
                        </Link>

                        <div class="flex flex-col pt-10 sm:pt-12">
                            <div class="space-y-2 text-center">
                                <h1
                                    class="leading-tight font-medium tracking-normal"
                                    :class="
                                        title === 'Check your email'
                                            ? 'text-2xl'
                                            : 'text-[28px]'
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

                            <div class="mt-10">
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
