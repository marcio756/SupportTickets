// resources/js/app.js
import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createI18n } from 'vue-i18n';
import { createVuestic } from 'vuestic-ui';
import 'vuestic-ui/css';
import messages from './locales/index.js';
import AppNavbar from './Components/Layout/AppNavbar.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Apanha o locale inicial fornecido pelo backend (Inertia Props) ou define "pt" como segurança
        const initialLocale = props.initialPage.props.locale || 'pt';

        const i18n = createI18n({
            legacy: false,
            locale: initialLocale,
            fallbackLocale: 'en',
            messages,
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .use(createVuestic())
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

/**
 * Global micro-interaction boundary for developer standalone interface.
 * Safely renders the reactive AppNavbar inside standard Blade views without triggering full Inertia routing loops.
 */
window.renderDeveloperNavbar = (container, user) => {
    
    // Configuração isolada do i18n para montagens fora do ecossistema do Inertia
    const i18nStandalone = createI18n({
        legacy: false,
        locale: 'pt',
        fallbackLocale: 'en',
        messages,
    });

    createApp({
        render: () => h(AppNavbar, {
            user: user,
            isDeveloperMode: true
        })
    })
    .use(ZiggyVue)
    .use(i18nStandalone)
    .use(createVuestic())
    .mount(container);
};