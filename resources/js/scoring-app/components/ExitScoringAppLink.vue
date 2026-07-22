<template>
    <!--
        Web-only "escape hatch" out of the scoring SPA back to the rest of
        the DeadCenter app (dashboard / org / admin, whichever matches the
        user's role).

        Hidden on the Android standalone APK — that bundle sets
        `window.__DC_STANDALONE = true` in main-standalone.js so we can
        distinguish it from the Laravel-served web build (which shares
        the same components). We used to check `location.protocol ===
        'file:'`, but the APK actually loads the SPA from
        `http://localhost:PORT/score` served by the on-device Ktor
        server — a real HTTP origin — so that check was wrong.

        The `variant` prop lets a host view choose between the floating
        pill (default, absolute top-left corner) and an inline chip that
        sits inside the view's own header.
    -->
    <a
        v-if="visible"
        :href="userStore.homeUrl"
        :class="[
            'inline-flex items-center gap-1.5 text-xs font-medium transition-colors',
            variant === 'floating'
                ? 'fixed top-3 left-3 z-40 rounded-full border border-slate-700 bg-slate-900/85 px-3 py-1.5 text-slate-200 backdrop-blur hover:border-red-500/70 hover:text-white'
                : 'rounded-lg bg-slate-700 px-2.5 py-1 text-slate-300 hover:bg-slate-600'
        ]"
    >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        DeadCenter
    </a>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useUserStore } from '../stores/userStore';

const props = defineProps({
    variant: {
        type: String,
        default: 'floating', // 'floating' | 'inline'
        validator: (v) => ['floating', 'inline'].includes(v),
    },
});

const userStore = useUserStore();
const route = useRoute();

// The standalone APK bundle (`main-standalone.js`) sets this flag on
// window before Vue mounts. The Laravel-served web bundle does not,
// so the link is only rendered when running as the web app.
const isStandaloneApk = typeof window !== 'undefined'
    && window.__DC_STANDALONE === true;

// The floating variant hides on `home` / `member-home` because those
// views already own their top bar (which carries an inline exit link
// via the same component). Prevents a double pill overlapping the
// DEADCENTER logo. The `inline` variant always renders when on web.
const visible = computed(() => {
    if (isStandaloneApk) return false;
    if (props.variant === 'floating') {
        return !['home', 'member-home'].includes(route.name);
    }
    return true;
});
</script>
