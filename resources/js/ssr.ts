import { createInertiaApp } from '@inertiajs/vue3';
import { createSSRApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'CashPilot';

type InitialPageProps = {
    locale?: string;
    translations?: Record<string, any>;
    fallbackLocale?: string;
    fallbackTranslations?: Record<string, any> | null;
};

function messagesFor(props: InitialPageProps): Record<string, any> {
    const locale = props.locale ?? 'en';
    const fallbackLocale = props.fallbackLocale ?? 'en';
    const messages = {
        [locale]: props.translations ?? {},
    } as Record<string, any>;

    if (props.fallbackTranslations) {
        messages[fallbackLocale] = props.fallbackTranslations;
    }

    return messages;
}

function layoutFor(name: string): unknown {
    switch (true) {
        case name === 'Landing':
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
}

createInertiaApp({
    title: (title) => (title ? `${appName} - ${title}` : appName),
    layout: layoutFor,
    setup({ App, props, plugin }) {
        const pageProps = props.initialPage.props as InitialPageProps;
        const locale = pageProps.locale ?? 'en';
        const fallbackLocale = pageProps.fallbackLocale ?? 'en';
        const vueApp = createSSRApp({ render: () => h(App, props) });

        /*
         * One bad render must not take the worker down with it.
         *
         * Inertia wraps the render call itself, so a component that throws
         * during setup is caught and returned as a failed render. An effect
         * scheduled by that same component is not: a watcher or computed that
         * throws after the render call has unwound escapes into Node and kills
         * the process, and supervisord then respawns it — once per request,
         * which is how a single unlucky page took SSR down for every user.
         *
         * Vue routes scheduler errors through here, so catching them turns
         * "the worker dies" back into "this one page falls back to client
         * rendering", which is what the fallback exists for.
         */
        vueApp.config.errorHandler = (error, _instance, info): void => {
            console.error(
                `[ssr] ${props.initialPage.component} failed during ${info}:`,
                error instanceof Error ? error.stack : error,
            );
        };

        vueApp.use(plugin);
        vueApp.use(
            createI18n({
                legacy: false,
                locale,
                fallbackLocale,
                messages: messagesFor(pageProps),
            }),
        );

        return vueApp;
    },
});
