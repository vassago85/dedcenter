<script setup>
import { useRouter } from 'vue-router';
import { backTo } from '../lib/backTo';

const props = defineProps({
    to: { type: [String, Object], required: true },
});

const router = useRouter();

function leave() {
    backTo(router, props.to);
}
</script>

<template>
    <button type="button" @click="leave">
        <slot />
    </button>
</template>
