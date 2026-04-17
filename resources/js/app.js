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
//   1. Strip *unambiguous* extension-only elements on page load & livewire
//      navigation. We do NOT run strippers inside morph hooks (that mutates
//      the DOM mid-walk and causes the null-before crash the strippers are
//      meant to prevent) and we only target tag names that are reserved
//      vendor prefixes. Attribute selectors like [data-gramm] are NOT used
//      because the app itself sets data-gramm="false" on <body> to signal
//      to Grammarly that it should stay out - matching that selector would
//      remove <body>.
//   2. Swallow the specific morph null-before crash globally so one bad
//      diff never dead-pages the user.

// CRITICAL: these must all be custom-element tag selectors unique to an
// extension vendor. Adding attribute selectors here risks matching real
// app elements (including <body>) and wiping the page.
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
