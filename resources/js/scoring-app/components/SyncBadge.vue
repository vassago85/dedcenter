<template>
    <button
        v-if="props.pending > 0"
        @click="$emit('sync')"
        :disabled="props.syncing"
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors"
        :class="props.syncing
            ? 'bg-blue-900/30 text-blue-400 cursor-wait'
            : 'bg-amber-900/30 text-amber-400 hover:bg-amber-900/50'"
    >
        <svg
            v-if="props.syncing"
            class="h-3 w-3 animate-spin"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <svg v-else class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
        </svg>
        {{ props.syncing ? 'Syncing...' : `${props.pending} pending` }}
    </button>
</template>

<script setup>
const props = defineProps({
    pending: { type: Number, default: 0 },
    syncing: { type: Boolean, default: false },
});

defineEmits(['sync']);
</script>
