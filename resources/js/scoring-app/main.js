import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import router from './router';
import App from './App.vue';

import '../../css/scoring.css';

axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
}

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#scoring-app');
