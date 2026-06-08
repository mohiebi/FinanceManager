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
        fallbackLocale?: string;
        fallbackTranslations?: Record<string, any> | null;
    };
};

function initialPage(): InitialPage {
    if (typeof document === 'undefined') {
        return {};
    }

    // Inertia renders the initial page payload as a JSON <script> tag
    // (`<script data-page="app" type="application/json">...</script>`),
    // not as a `data-page` attribute on the `#app` element itself.
    const script = document.querySelector<HTMLScriptElement>(
        'script[data-page="app"][type="application/json"]',
    );

    if (script?.textContent) {
        try {
            return JSON.parse(script.textContent) as InitialPage;
        } catch {
            // fall through to legacy lookup below
        }
    }

    // Fallback for the legacy `<div id="app" data-page="...">` markup.
    const page = document.getElementById('app')?.dataset.page;

    if (!page) {
        return {};
    }

    return JSON.parse(page) as InitialPage;
}

function applyLocale(locale = 'en', dir = 'ltr'): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.lang = locale;
    document.documentElement.dir = dir;
}

const page = initialPage();
const initialLocale = page.props?.locale ?? 'en';
const initialDir = page.props?.dir ?? 'ltr';
const initialFallbackLocale = page.props?.fallbackLocale ?? 'en';
const messages = {
    [initialLocale]: page.props?.translations ?? {},
} as Record<string, any>;

if (page.props?.fallbackTranslations) {
    messages[initialFallbackLocale] = page.props.fallbackTranslations;
}

applyLocale(initialLocale, initialDir);

const i18n = createI18n({
    legacy: false,
    locale: initialLocale,
    fallbackLocale: initialFallbackLocale,
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
        fallbackLocale?: string;
        fallbackTranslations?: Record<string, any> | null;
    };
    const locale = props.locale ?? 'en';
    const fallbackLocale = props.fallbackLocale ?? 'en';

    if (props.translations) {
        i18n.global.setLocaleMessage(locale, props.translations);
    }

    if (props.fallbackTranslations) {
        i18n.global.setLocaleMessage(fallbackLocale, props.fallbackTranslations);
    }

    (i18n.global.fallbackLocale as unknown as { value: string }).value = fallbackLocale;
    (i18n.global.locale as unknown as { value: string }).value = locale;
    applyLocale(locale, props.dir ?? 'ltr');
});

// This will set light / dark mode on page load...
initializeTheme();
