const VAPID_PUBLIC_KEY_META = 'vapid-public-key';

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function getVapidKey() {
    const meta = document.querySelector(`meta[name="${VAPID_PUBLIC_KEY_META}"]`);
    return meta?.content || null;
}

export async function subscribeToPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn('Push not supported');
        return null;
    }

    const vapidKey = getVapidKey();
    if (!vapidKey) {
        console.warn('VAPID public key not found');
        return null;
    }

    try {
        const registration = await navigator.serviceWorker.ready;

        let subscription = await registration.pushManager.getSubscription();
        if (subscription) return subscription;

        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        });

        const response = await fetch('/api/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh')))),
                    auth: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth')))),
                },
            }),
        });

        if (!response.ok) {
            console.error('Failed to register push subscription on server');
        }

        return subscription;
    } catch (error) {
        console.error('Push subscription failed:', error);
        return null;
    }
}

export async function unsubscribeFromPush() {
    if (!('serviceWorker' in navigator)) return;

    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            await fetch('/api/push/unsubscribe', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });

            await subscription.unsubscribe();
        }
    } catch (error) {
        console.error('Push unsubscribe failed:', error);
    }
}

export async function isPushSubscribed() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;

    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        return !!subscription;
    } catch {
        return false;
    }
}
