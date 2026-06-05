import { ref, onMounted, onUnmounted } from 'vue';

export function useSyncStatus() {
    const syncStatus = ref(null);
    const syncing = ref(false);
    const error = ref(null);
    // /api/sync-status only exists on the native Android hub. On the cloud PWA
    // it 404s, so once we see that we stop polling to avoid a flood of failed
    // requests every 5 seconds.
    const supported = ref(true);
    let interval = null;

    function stopPolling() {
        if (interval) {
            clearInterval(interval);
            interval = null;
        }
    }

    async function fetchStatus() {
        try {
            const resp = await fetch('/api/sync-status');
            if (resp.ok) {
                syncStatus.value = await resp.json();
                error.value = null;
            } else if (resp.status === 404) {
                supported.value = false;
                stopPolling();
            }
        } catch (e) {
            error.value = e.message;
        }
    }

    async function triggerSync(target = 'both') {
        syncing.value = true;
        try {
            await fetch('/api/trigger-sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ target }),
            });
            await new Promise(r => setTimeout(r, 2000));
            await fetchStatus();
        } catch (e) {
            error.value = e.message;
        } finally {
            syncing.value = false;
        }
    }

    function formatTimeAgo(iso) {
        if (!iso) return 'never';
        const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
        if (diff < 5) return 'just now';
        if (diff < 60) return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        return `${Math.floor(diff / 3600)}h ago`;
    }

    onMounted(() => {
        fetchStatus();
        interval = setInterval(fetchStatus, 5000);
    });

    onUnmounted(stopPolling);

    return { syncStatus, syncing, error, supported, triggerSync, formatTimeAgo };
}
