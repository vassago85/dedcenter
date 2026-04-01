import { ref, onMounted, onUnmounted } from 'vue';

export function useSyncStatus() {
    const syncStatus = ref(null);
    const syncing = ref(false);
    const error = ref(null);
    let interval = null;

    async function fetchStatus() {
        try {
            const resp = await fetch('/api/sync-status');
            if (resp.ok) {
                syncStatus.value = await resp.json();
                error.value = null;
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

    onUnmounted(() => {
        if (interval) clearInterval(interval);
    });

    return { syncStatus, syncing, error, triggerSync, formatTimeAgo };
}
