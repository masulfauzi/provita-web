import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import AppLayout from '@/Layouts/AppLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import id from '@/locale/id';
import Provita from '@/theme/provita';

const appName = import.meta.env.VITE_APP_NAME || 'Bank Provita';

// Halaman umum: resources/js/Pages/**. Halaman modul: app/Http/Modules/{Modul}/Pages/**,
// dirender dari server dengan nama "Modul::Halaman", mis. Inertia::render('Nasabah::Index').
const pages = import.meta.glob('./Pages/**/*.vue');
const modulePages = import.meta.glob('../../app/Http/Modules/*/Pages/**/*.vue');

function resolvePage(name) {
    const [module, page] = name.includes('::') ? name.split('::') : [null, name];
    const path = module ? `../../app/Http/Modules/${module}/Pages/${page}.vue` : `./Pages/${page}.vue`;
    const loader = module ? modulePages[path] : pages[path];

    if (!loader) {
        throw new Error(`Halaman Inertia tidak ditemukan: ${name} (${path})`);
    }

    return loader();
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: resolvePage,
    // Layout default; halaman bisa menimpanya lewat defineOptions({ layout }).
    layout: (name, page) => {
        if (name.startsWith('Auth/')) return GuestLayout;
        if (name === 'Error') return page.props.auth?.user ? AppLayout : GuestLayout;
        return AppLayout;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Provita,
                    options: {
                        darkModeSelector: '.app-dark',
                        cssLayer: {
                            name: 'primevue',
                            order: 'theme, base, primevue',
                        },
                    },
                },
                locale: id,
            })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('tooltip', Tooltip)
            .mount(el);
    },
    progress: {
        color: '#1d4ed8',
    },
});
