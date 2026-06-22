import axios from 'axios';
import { router } from '@inertiajs/vue3';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Same-origin API calls use the session + XSRF-TOKEN cookie Laravel sets on each response.
// Do not send X-CSRF-TOKEN from <meta> on XHR (it goes stale after Inertia navigations).
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

router.on('start', () => {
	// Hook available if needed later
});

export { axios };


