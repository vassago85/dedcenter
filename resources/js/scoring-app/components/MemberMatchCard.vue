<template>
    <div class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 transition-colors hover:bg-slate-700/80">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium">{{ match.name }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-400">
                    <span v-if="match.date">{{ formatDate(match.date) }}</span>
                    <span v-if="match.location">{{ match.location }}</span>
                    <span v-if="match.organization_name" class="text-slate-500">{{ match.organization_name }}</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                        :class="statusClass"
                    >
                        {{ match.status_label }}
                    </span>
                    <span
                        v-if="match.scoring_type && match.scoring_type !== 'standard'"
                        class="rounded-full bg-red-600/15 px-2 py-0.5 text-[10px] font-bold uppercase text-red-400"
                    >
                        {{ match.scoring_type }}
                    </span>
                    <span class="rounded-full bg-slate-700 px-2 py-0.5 text-[10px] text-slate-400">
                        {{ match.shooters_count }} {{ match.shooters_count === 1 ? 'shooter' : 'shooters' }}
                    </span>
                </div>
            </div>
            <button
                v-if="actionLabel"
                @click.stop="$emit('action')"
                class="shrink-0 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-red-700"
            >
                {{ actionLabel }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    match: { type: Object, required: true },
    actionLabel: { type: String, default: '' },
});

defineEmits(['action']);

const statusColors = {
    pre_registration: 'bg-violet-600/20 text-violet-400',
    registration_open: 'bg-sky-600/20 text-sky-400',
    registration_closed: 'bg-amber-600/20 text-amber-400',
    squadding_open: 'bg-indigo-600/20 text-indigo-400',
    active: 'bg-green-600/20 text-green-400',
    completed: 'bg-zinc-600/20 text-zinc-400',
};

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

const statusClass = computed(() => {
    return statusColors[props.match.status] || 'bg-slate-700 text-slate-400';
});
</script>
