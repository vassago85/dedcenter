import { ref, onMounted, onUnmounted } from 'vue';
import { isStandaloneApk } from '../lib/platform';

// One poller shared by every mounted consumer (SyncStatusBar, DeviceRoleChip),
// running at the fastest interval any of them asked for.
const syncStatus = ref(null);
const syncing = ref(false);
const error = ref(null);
// /api/sync-status only exists on the native Android hub, which always serves
// the standalone bundle. Anywhere else (or on a 404) polling is switched off.
const supported = ref(true);
const intervals = [];
let timer = null;
let timerMs = 0;
let inFlight = false;

async function fetchStatus() {
    if (supported.value && !isStandaloneApk()) {
        supported.value = false;
        reschedule();
    }
    if (!supported.value || inFlight) return;
    inFlight = true;
    try {
        const resp = await fetch('/api/sync-status');
        if (resp.ok) {
            syncStatus.value = await resp.json();
            error.value = null;
        } else if (resp.status === 404) {
            supported.value = false;
            reschedule();
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        inFlight = false;
    }
}

function reschedule() {
    const ms = supported.value && intervals.length ? Math.min(...intervals) : 0;
    if (ms === timerMs) return;
    clearInterval(timer);
    timer = null;
    timerMs = ms;
    if (ms) {
        timer = setInterval(() => {
            if (!document.hidden) fetchStatus();
        }, ms);
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

export function useSyncStatus(intervalMs = 5000) {
    onMounted(() => {
        intervals.push(intervalMs);
        fetchStatus();
        reschedule();
    });

    onUnmounted(() => {
        intervals.splice(intervals.indexOf(intervalMs), 1);
        reschedule();
    });

    return { syncStatus, syncing, error, supported, triggerSync, formatTimeAgo };
}
