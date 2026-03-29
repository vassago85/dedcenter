<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="isOnline ? 'bg-green-900/30 text-green-400' : 'bg-red-900/30 text-red-400'"
    >
        <span class="h-1.5 w-1.5 rounded-full" :class="isOnline ? 'bg-green-400' : 'bg-red-400'"></span>
        {{ isOnline ? 'Online' : 'Offline' }}
    </span>
</template>

<script setup>
import { useOnline } from '../composables/useOnline';

const { isOnline } = useOnline();
</script>
