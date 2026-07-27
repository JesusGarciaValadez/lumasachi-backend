import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { createI18nInstance, normalizeLocale } from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const initialLocale = normalizeLocale((props.initialPage.props.i18n as { locale?: string } | undefined)?.locale);
        const i18n = createI18nInstance(initialLocale);

        router.on('success', (event) => {
            const locale = (event.detail.page.props.i18n as { locale?: string } | undefined)?.locale;

            if (locale) {
                const normalizedLocale = normalizeLocale(locale);
                i18n.global.locale.value = normalizedLocale;
                document.documentElement.lang = normalizedLocale;
            }
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
