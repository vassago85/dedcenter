import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import router from './router';
import App from './App.vue';
import { initTheme } from './composables/useTheme';

import '../../css/scoring.css';

// Apply the saved dark/light preference before Vue mounts so the
// first paint matches the user's choice — no flash of dark content
// on a tablet that was set to light at the previous match.
initTheme();

axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Hard cap so a flaky network / WebView DNS hang doesn't freeze the boot.
// 12s covers cold PRS scoreboard fetches; userStore.fetchUser overrides
// with a tighter timeout so the home screen stays responsive offline.
axios.defaults.timeout = 12000;

const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
}

const apiToken = document.querySelector('meta[name="api-token"]');
if (apiToken) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${apiToken.getAttribute('content')}`;
}

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#scoring-app');
