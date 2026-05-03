import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import router from './router';
import App from './App.vue';

import '../../css/scoring.css';

axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Hard cap so a flaky WebView / DNS hang doesn't freeze the boot. The
// userStore boot call has its own tighter timeout, this is the floor for
// everything else. 12s was picked because PRS scoreboard requests on big
// matches can take 5-7s on a cold cache.
axios.defaults.timeout = 12000;

// Surfaces boot failures to the user instead of letting them die silently
// in the WebView console (which the user can't see on their phone). If Vue
// fails to mount, or the first paint throws, we paint a basic error screen
// over the boot splash so they at least know what happened and can send
// a screenshot.
function showBootError(err) {
    try {
        const root = document.getElementById('scoring-app') || document.body;
        const message = (err && (err.stack || err.message)) || String(err || 'Unknown error');
        root.innerHTML = `
            <div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;background:#0f172a;color:#fef2f2;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
                <div style="max-width:480px;width:100%;border:1px solid #7f1d1d;border-radius:12px;padding:20px;background:#1e293b;">
                    <h1 style="margin:0 0 8px;font-size:18px;font-weight:700;color:#fca5a5;">DeadCenter failed to start</h1>
                    <p style="margin:0 0 12px;font-size:13px;color:#cbd5e1;">Try clearing the app's cache or reinstalling. Send the message below to support if it keeps happening.</p>
                    <pre style="margin:0;padding:12px;background:#0f172a;color:#fda4af;font-size:11px;border-radius:8px;overflow:auto;max-height:240px;white-space:pre-wrap;word-break:break-word;">${message.replace(/[<>&]/g, (c) => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</pre>
                </div>
            </div>
        `;
    } catch {
        // Last-ditch — at least don't propagate.
    }
}

window.addEventListener('error', (e) => showBootError(e.error || e.message));
window.addEventListener('unhandledrejection', (e) => showBootError(e.reason));

try {
    const app = createApp(App);
    app.config.errorHandler = (err) => {
        // Vue swallows render errors by default — re-surface them so the
        // user gets a visible error instead of a half-rendered blank page.
        console.error('[vue]', err);
        showBootError(err);
    };
    app.use(createPinia());
    app.use(router);
    app.mount('#scoring-app');
} catch (err) {
    showBootError(err);
}
