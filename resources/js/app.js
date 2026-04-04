import './bootstrap';

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
