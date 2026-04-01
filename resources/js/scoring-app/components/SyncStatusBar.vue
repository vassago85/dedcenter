<template>
    <div v-if="syncStatus" class="border-t border-border bg-surface px-4 py-2">
        <div class="mx-auto flex max-w-4xl items-center gap-4 text-[11px]">
            <!-- Hub sync -->
            <div v-if="syncStatus.hub?.enabled" class="flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full" :class="hubDotClass"></span>
                <span class="text-muted">
                    Hub: {{ syncStatus.hub.pending_up }} pending
                    <span class="opacity-60">&middot; {{ formatTimeAgo(syncStatus.hub.last_pull_at) }}</span>
                </span>
            </div>

            <!-- Cloud sync -->
            <div v-if="syncStatus.cloud?.enabled" class="flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full" :class="cloudDotClass"></span>
                <span class="text-muted">
                    Cloud: {{ syncStatus.cloud.pending_up }} pending
                    <span class="opacity-60">&middot; {{ formatTimeAgo(syncStatus.cloud.last_pull_at) }}</span>
                </span>
            </div>

            <!-- Cloud offline -->
            <div v-if="syncStatus.cloud && !syncStatus.cloud.enabled" class="flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                <span class="text-muted opacity-60">Cloud: Offline</span>
            </div>

            <div class="ml-auto">
                <button
                    @click="triggerSync('both')"
                    :disabled="syncing"
                    class="rounded px-2 py-0.5 text-[10px] font-medium transition-colors"
                    :class="syncing ? 'bg-border text-muted cursor-wait' : 'bg-surface-2 text-muted hover:text-primary hover:bg-border'"
                >
                    <template v-if="syncing">Syncing...</template>
                    <template v-else>Sync Now</template>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useSyncStatus } from '../composables/useSyncStatus';

const { syncStatus, syncing, triggerSync, formatTimeAgo } = useSyncStatus();

const hubDotClass = computed(() => {
    if (!syncStatus.value?.hub) return 'bg-slate-500';
    if (syncStatus.value.hub.pending_up > 0) return 'bg-amber-400';
    return 'bg-green-400';
});

const cloudDotClass = computed(() => {
    if (!syncStatus.value?.cloud?.enabled) return 'bg-slate-500';
    if (syncStatus.value.cloud.pending_up > 0) return 'bg-blue-400';
    return 'bg-green-400';
});
</script>
