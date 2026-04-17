import './bootstrap';

// Browser-extension DOM noise defense for Livewire morph.
//
// Extensions (Grammarly, LastPass, 1Password, Honey, ColorZilla, Brave
// price-tracking, iOS webview injections, etc.) inject hidden nodes and
// attributes into the DOM. When Livewire's morph engine diffs the server
// HTML against the live DOM, these extra siblings/attributes cause
// "TypeError: Cannot read properties of null (reading 'before')" inside
// wire-transition.js and the page stops responding to server updates.
//
// Two-layer defense:
//   1. Strip known extension nodes/attributes before & during morph.
//   2. Swallow the morph null-before crash globally so one bad diff
//      never dead-pages the user.

const EXTENSION_SELECTORS = [
    // Grammarly
    'grammarly-desktop-integration',
    'grammarly-extension',
    '[data-grammarly-shadow-root]',
    '[data-gramm]',
    // LastPass
    'lastpass-extension-icon',
    'iframe[data-lastpass-iframe-type]',
    'div[data-lastpass-icon-root]',
    // 1Password
    'com-1password-button',
    'com-1password-op-menu',
    'com-1password-notification',
    // Honey / PayPal
    'honey-extension-root',
    '#honey-side-app-iframe',
    // Dashlane
    'iframe[data-dashlane-autofill]',
    'div[data-dashlane-label]',
    // Bitwarden
    '[data-bwignore]',
    // Misc known injectors
    'iframe[src*="chrome-extension"]',
    'iframe[src*="moz-extension"]',
].join(',');

const EXTENSION_ATTRIBUTES = [
    'data-new-gr-c-s-check-loaded',
    'data-gr-ext-installed',
    'data-new-gr-c-s-loaded',
    'data-lt-installed',
    'cz-shortcut-listen',
    'monica-id',
    'monica-version',
];

function stripExtensionNoise() {
    try {
        document.querySelectorAll(EXTENSION_SELECTORS).forEach((n) => n.remove());
        const body = document.body;
        if (body) {
            EXTENSION_ATTRIBUTES.forEach((a) => body.removeAttribute(a));
        }
    } catch (_) {
        // Never let cleanup itself break the page.
    }
}

document.addEventListener('DOMContentLoaded', stripExtensionNoise);
document.addEventListener('livewire:navigated', stripExtensionNoise);

function registerLivewireHooks() {
    if (!window.Livewire) return;
    // Strip right before Livewire reads the DOM for morph.
    window.Livewire.hook('morph', stripExtensionNoise);
    window.Livewire.hook('morph.updating', stripExtensionNoise);
    window.Livewire.hook('morph.added', stripExtensionNoise);
}
if (window.Livewire) {
    registerLivewireHooks();
} else {
    document.addEventListener('livewire:init', registerLivewireHooks);
}

// Layer 2: swallow the specific morph race-condition error.
// Extensions inject/remove nodes between snapshot and patch; the resulting
// "Cannot read properties of null (reading 'before')" is visual-only and
// should not stop subsequent Livewire round-trips.
const MORPH_NULL_BEFORE = /reading 'before'|reading "before"/i;

function isLivewireMorphNullBefore(err) {
    if (!err) return false;
    const msg = typeof err === 'string' ? err : (err.message || '');
    if (!MORPH_NULL_BEFORE.test(msg)) return false;
    const stack = (err.stack || '') + '';
    return /morph|wire-transition|supportMorphDom|livewire/i.test(stack);
}

window.addEventListener('error', (event) => {
    if (isLivewireMorphNullBefore(event.error || event.message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);

window.addEventListener('unhandledrejection', (event) => {
    if (isLivewireMorphNullBefore(event.reason)) {
        event.preventDefault();
    }
});

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
