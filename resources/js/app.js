import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/index.esm.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';

import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Ripple from 'primevue/ripple';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import ProgressBar from 'primevue/progressbar';
import MultiSelect from 'primevue/multiselect';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = resolvePageComponent(`./Pages/${name}.vue`, pages);
        page.then((module) => {
            if (name.startsWith('Admin/')) {
                module.default.layout = module.default.layout || AdminLayout;
            }
        });
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
			.use(PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: 'html.dark' } } })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('ripple', Ripple)
            .component('PButton', Button)
            .component('PDialog', Dialog)
            .component('PDrawer', Drawer)
            .component('PInputText', InputText)
            .component('PDropdown', Dropdown)
            .component('PDataTable', DataTable)
            .component('PColumn', Column)
            .component('PToast', Toast)
            .component('PConfirmDialog', ConfirmDialog)
            .component('PProgressBar', ProgressBar)
            .component('PMultiSelect', MultiSelect)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
