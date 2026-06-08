import { createInertiaApp, router } from '@inertiajs/vue3';
import { createI18n } from 'vue-i18n';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

type InitialPage = {
    props?: {
        locale?: string;
        dir?: string;
        translations?: Record<string, any>;
    };
};

function initialPage(): InitialPage {
    const page = document.getElementById('app')?.dataset.page;

    if (!page) {
        return {};
    }

    return JSON.parse(page) as InitialPage;
}

function applyLocale(locale = 'en', dir = 'ltr'): void {
    document.documentElement.lang = locale;
    document.documentElement.dir = dir;
}

const page = initialPage();
const initialLocale = page.props?.locale ?? 'en';
const initialDir = page.props?.dir ?? 'ltr';
const messages = {
    [initialLocale]: page.props?.translations ?? {},
} as Record<string, any>;

applyLocale(initialLocale, initialDir);

const i18n = createI18n({
    legacy: false,
    locale: initialLocale,
    fallbackLocale: 'en',
    messages,
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
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
    },
    progress: {
        color: '#4B5563',
    },
    withApp: (app) => {
        app.use(i18n);
    },
});

router.on('success', (event) => {
    const props = event.detail.page.props as {
        locale?: string;
        dir?: string;
        translations?: Record<string, any>;
    };
    const locale = props.locale ?? 'en';

    if (props.translations) {
        i18n.global.setLocaleMessage(locale, props.translations);
    }

    (i18n.global.locale as unknown as { value: string }).value = locale;
    applyLocale(locale, props.dir ?? 'ltr');
});

// This will set light / dark mode on page load...
initializeTheme();
