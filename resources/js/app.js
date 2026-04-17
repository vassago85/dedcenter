import './bootstrap';

// Strip browser-extension-injected DOM nodes (Grammarly, LastPass, etc.)
// before Livewire's morph engine sees them. These otherwise trigger
// "Cannot read properties of null (reading 'before')" in wire-transition.js
// because Livewire's diff expects them but they vanish during the morph.
function stripExtensionNoise() {
    document.querySelectorAll(
        'grammarly-desktop-integration, grammarly-extension, [data-grammarly-shadow-root], ' +
        'lastpass-extension-icon, iframe[data-lastpass-iframe-type]'
    ).forEach((n) => n.remove());
}
document.addEventListener('DOMContentLoaded', stripExtensionNoise);
document.addEventListener('livewire:navigated', stripExtensionNoise);
if (window.Livewire) {
    window.Livewire.hook('morph', stripExtensionNoise);
    window.Livewire.hook('morph.updating', stripExtensionNoise);
} else {
    document.addEventListener('livewire:init', () => {
        if (window.Livewire) {
            window.Livewire.hook('morph', stripExtensionNoise);
            window.Livewire.hook('morph.updating', stripExtensionNoise);
        }
    });
}

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
