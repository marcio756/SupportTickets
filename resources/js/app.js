import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Vuestic UI Import
import { createVuestic } from "vuestic-ui";
import "vuestic-ui/css";
import "material-design-icons-iconfont/dist/material-design-icons.min.css"; 

// Vue I18n Modular Import
import { createI18n } from 'vue-i18n';
import messages from './locales/index';

const appName = import.meta.env.VITE_APP_NAME || 'SupportTickets';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        /**
         * Initializes the i18n module.
         * Extracts the initial session locale provided by Laravel's HandleInertiaRequests middleware.
         * Utilizes the dynamically generated messages object containing all namespaces.
         */
        const initialLocale = props.initialPage.props.locale || 'pt';

        const i18n = createI18n({
            legacy: false,
            locale: initialLocale,
            fallbackLocale: 'en',
            messages: messages
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .use(createVuestic({
                config: {
                    colors: {
                        variables: {
                            primary: '#154EC1',
                            secondary: '#767C88',
                            success: '#3D9209',
                            info: '#2C82E0',
                            danger: '#E42222',
                            warning: '#FFD43A',
                        },
                    },
                },
            }))
            .mount(el);
    },
    progress: {
        color: '#154EC1',
    },
});