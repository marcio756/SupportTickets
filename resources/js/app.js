// resources/js/app.js
import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createI18n } from 'vue-i18n';
import AppNavbar from './Components/Layout/AppNavbar.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
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
    createApp({
        render: () => h(AppNavbar, {
            user: user,
            isDeveloperMode: true
        })
    }).use(ZiggyVue).mount(container);
};