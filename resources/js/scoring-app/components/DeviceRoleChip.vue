<template>
    <span
        v-if="role"
        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold"
        :class="roleClass"
    >
        <svg v-if="role === 'cloud'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 .75-7.425A4.502 4.502 0 0 0 14.25 7.5a4.5 4.5 0 0 0-8.086 2.727A4.5 4.5 0 0 0 2.25 15Z" />
        </svg>
        <svg v-else-if="role === 'hub'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
        </svg>
        <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
        </svg>
        {{ label }}
    </span>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';

const syncStatus = ref(null);
let interval = null;

async function fetchStatus() {
    try {
        const resp = await fetch('/api/sync-status');
        if (resp.ok) syncStatus.value = await resp.json();
    } catch { /* offline */ }
}

onMounted(() => {
    fetchStatus();
    interval = setInterval(fetchStatus, 10000);
});
onUnmounted(() => { if (interval) clearInterval(interval); });

const role = computed(() => {
    if (!syncStatus.value) return null;
    if (syncStatus.value.hub?.enabled) return 'hub';
    if (syncStatus.value.cloud?.enabled) return 'cloud';
    return 'local';
});

const label = computed(() => {
    if (role.value === 'cloud') return 'Connected';
    if (role.value === 'hub') return 'Hub';
    return 'Local';
});

const roleClass = computed(() => {
    if (role.value === 'cloud') return 'bg-blue-600/15 text-blue-400';
    if (role.value === 'hub') return 'bg-emerald-600/15 text-emerald-400';
    return 'bg-slate-700 text-slate-400';
});
</script>
