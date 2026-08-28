import './bootstrap';

// Livewire hardening for browser-extension noise.
//
// Extensions (Grammarly, LastPass, 1Password, Honey, etc.) inject hidden
// DOM nodes. When Livewire's morph engine diffs the server HTML against
// the live DOM those extra siblings can cause
// "TypeError: Cannot read properties of null (reading 'before')" inside
// the morph walk. Livewire returns HTTP 200 but the commit aborts
// mid-apply; without intervention the component's pending spinner sits
// forever because the commit never reaches its completion callbacks.
//
// Strategy:
//   1. Strip *unambiguous* extension-only custom-elements on page load
//      and after Livewire SPA navigation. We do not touch the DOM during
//      morph hooks (that is what caused the null-before crashes in the
//      first place) and we never match attribute selectors that could
//      collide with real app markup (e.g. data-gramm, set on <body>).
//   2. On a fatal morph error, call Livewire.all().forEach(c => c.$commit?.())
//      is not safe - instead we reset any lingering "processing" flags on
//      Livewire components so spinners clear, and log to console so the
//      error is still visible in devtools for diagnosis.

const EXTENSION_SELECTORS = [
    'grammarly-desktop-integration',
    'grammarly-extension',
    'lastpass-extension-icon',
    'com-1password-button',
    'com-1password-op-menu',
    'com-1password-notification',
    'honey-extension-root',
].join(',');

function stripExtensionNoise() {
    try {
        document.querySelectorAll(EXTENSION_SELECTORS).forEach((n) => n.remove());
    } catch (_) {
        // Never let cleanup itself break the page.
    }
}

document.addEventListener('DOMContentLoaded', stripExtensionNoise);
document.addEventListener('livewire:navigated', stripExtensionNoise);

// Release stuck Livewire spinners after a morph failure.
//
// When morph throws mid-apply, Livewire's commit promise never resolves,
// so wire:loading indicators, wire:dirty classes and the disabled state
// on submit buttons stay pinned. We clear them by hand and let the user
// retry or navigate away. The next full page load will show the persisted
// state (the server already saved successfully).
function clearStuckLivewireLoading() {
    // wire:loading shows via CSS - adding `display:none` directly ensures it hides.
    document.querySelectorAll('[wire\\:loading]').forEach((el) => {
        el.style.display = 'none';
    });
    // Re-enable any buttons Livewire disabled via wire:loading.attr="disabled"
    document.querySelectorAll('[wire\\:loading\\.attr\\.disabled], [wire\\:loading\\.attr="disabled"]').forEach((el) => {
        el.removeAttribute('disabled');
    });
    // Alpine/Livewire also toggle aria-busy on forms - clear it
    document.querySelectorAll('[aria-busy="true"]').forEach((el) => {
        el.setAttribute('aria-busy', 'false');
    });
}

const MORPH_ERROR_PATTERNS = [
    /reading 'before'/i,
    /reading "before"/i,
    /morph/i,
];

function looksLikeMorphError(err) {
    if (!err) return false;
    const msg = typeof err === 'string' ? err : (err.message || '');
    const stack = (err && err.stack) || '';
    const haystack = `${msg}\n${stack}`;
    return MORPH_ERROR_PATTERNS.some((re) => re.test(haystack))
        && /livewire|morph|alpine/i.test(haystack);
}

window.addEventListener('error', (event) => {
    if (!looksLikeMorphError(event.error || event.message)) return;
    // Do NOT preventDefault - let DevTools show the error.
    // Just release stuck UI so the user isn't dead-locked.
    setTimeout(clearStuckLivewireLoading, 0);
}, true);

window.addEventListener('unhandledrejection', (event) => {
    if (!looksLikeMorphError(event.reason)) return;
    setTimeout(clearStuckLivewireLoading, 0);
});

// "Page has expired" (HTTP 419) recovery.
//
// Livewire's default behavior on a 419 (stale CSRF / rotated release_token /
// dropped session) is to show a native `confirm()` modal asking whether to
// reload. That is jarring, especially on a match-day tab that has been
// sitting in the background for a few minutes, or immediately after a
// deploy where every open tab is guaranteed to hit exactly this.
//
// We replace the confirm() with smart recovery:
//   - If the tab isn't visible OR the user hasn't touched keyboard/mouse
//     in a while, silently reload — the user won't notice.
//   - If the user is actively interacting, surface a small,
//     unobtrusive toast with a manual "Refresh" button so they can choose
//     when to reload (avoids nuking half-typed forms).
// Guarded by sessionStorage against reload loops.
const RELOAD_STAMP_KEY = 'dc-419-reload-stamp';
const RELOAD_MIN_GAP_MS = 15_000;
const ACTIVE_INTERACTION_MS = 30_000;

let lastInteractionAt = Date.now();
['mousemove', 'keydown', 'touchstart', 'pointerdown', 'wheel'].forEach((evt) => {
    document.addEventListener(evt, () => { lastInteractionAt = Date.now(); }, { passive: true, capture: true });
});

function safeReload() {
    try {
        const last = Number(sessionStorage.getItem(RELOAD_STAMP_KEY) || 0);
        if (Date.now() - last < RELOAD_MIN_GAP_MS) {
            // Just reloaded — another 419 this fast means a real backend
            // problem (mis-set APP_KEY, HTTPS/session mismatch etc.).
            // Let Livewire's default handler run so the user sees the modal
            // and can escalate, rather than us hammering reload forever.
            console.warn('[dc-419] second 419 within reload guard window; letting default handler run');
            return false;
        }
        sessionStorage.setItem(RELOAD_STAMP_KEY, String(Date.now()));
    } catch (_) { /* sessionStorage disabled — reload anyway */ }
    window.location.reload();
    return true;
}

function showExpiredToast() {
    // No dependency on Flux/toast here — this runs when Livewire itself
    // is broken, so we render a minimal HTML banner and get out of the way.
    if (document.getElementById('dc-expired-toast')) return;
    const el = document.createElement('div');
    el.id = 'dc-expired-toast';
    el.setAttribute('role', 'status');
    el.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:9999;'
        + 'display:flex;align-items:center;gap:.75rem;'
        + 'background:#1c1917;color:#fafafa;border:1px solid #57534e;'
        + 'padding:.75rem 1rem;border-radius:.5rem;'
        + 'font:500 13px/1.2 ui-sans-serif,system-ui,sans-serif;'
        + 'box-shadow:0 10px 25px -5px rgba(0,0,0,.5);max-width:22rem;';
    el.innerHTML = ''
        + '<span>A newer version is available. Refresh to continue.</span>'
        + '<button type="button" id="dc-expired-toast-refresh"'
        + ' style="background:#e11d48;color:#fff;border:0;'
        + 'padding:.4rem .75rem;border-radius:.375rem;cursor:pointer;'
        + 'font:600 12px/1 inherit;">Refresh</button>';
    document.body.appendChild(el);
    document.getElementById('dc-expired-toast-refresh')?.addEventListener('click', () => safeReload());
}

function handleExpired() {
    const idle = Date.now() - lastInteractionAt > ACTIVE_INTERACTION_MS;
    if (document.hidden || idle) {
        safeReload();
    } else {
        showExpiredToast();
    }
}

// Also register Livewire hooks so we clear spinners on commit failures
// that Livewire itself catches internally (these don't bubble as window errors).
// Plus an 8-second watchdog: if a request was sent but never completed its
// morph (response.received fires but commit.succeed/failed doesn't), force
// the UI back to a responsive state so the user isn't dead-locked.
function registerLivewireHooks() {
    if (!window.Livewire?.hook) return;

    const pendingCommits = new Map();
    const WATCHDOG_MS = 8000;

    window.Livewire.hook('commit.prepare', ({ component }) => {
        if (!component) return;
        const id = component.id || Math.random().toString(36);
        // Clear any previous watchdog for this component.
        const existing = pendingCommits.get(id);
        if (existing) clearTimeout(existing);
        const t = setTimeout(() => {
            console.warn('[livewire-watchdog] commit for component', id, 'exceeded', WATCHDOG_MS, 'ms - clearing spinner');
            clearStuckLivewireLoading();
            pendingCommits.delete(id);
        }, WATCHDOG_MS);
        pendingCommits.set(id, t);
    });

    const clearWatchdog = ({ component }) => {
        if (!component) return;
        const id = component.id;
        const existing = pendingCommits.get(id);
        if (existing) {
            clearTimeout(existing);
            pendingCommits.delete(id);
        }
    };

    window.Livewire.hook('commit.succeed', clearWatchdog);
    window.Livewire.hook('commit.failed', (ctx) => {
        clearWatchdog(ctx);
        setTimeout(clearStuckLivewireLoading, 0);
    });

    // Livewire fires `request` per XHR with a `fail` registrar. Calling
    // preventDefault() on the 419 branch is what stops the built-in
    // "This page has expired" confirm dialog from ever surfacing.
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status !== 419) return;
            preventDefault?.();
            handleExpired();
        });
    });
}
document.addEventListener('livewire:init', registerLivewireHooks);
document.addEventListener('livewire:navigated', registerLivewireHooks);

if ('serviceWorker' in navigator && document.querySelector('meta[name="vapid-public-key"]')) {
    import('./scoring-app/lib/pushSubscription.js').then((mod) => {
        window.pushUtils = {
            subscribe: mod.subscribeToPush,
            unsubscribe: mod.unsubscribeFromPush,
            isSubscribed: mod.isPushSubscribed,
        };
        mod.subscribeToPush().catch(() => {});
    });
}
