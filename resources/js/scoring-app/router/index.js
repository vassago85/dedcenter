import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router';
import { useUserStore } from '../stores/userStore';

const LAST_MATCH_KEY = 'dc_last_match_id';
const MODE_KEY = 'dc_pwa_mode';

const routes = [
    // Root redirect — the standalone APK loads `file:///.../scoring-standalone.html`
    // with no path/hash, which lands the hash router on `/`. Without this
    // redirect the user sees a "no route matched" blank page instead of the
    // home screen.
    { path: '/', redirect: { name: 'home' } },
    {
        path: '/score',
        name: 'home',
        component: () => import('../views/HomeView.vue'),
    },
    {
        path: '/score/member',
        name: 'member-home',
        component: () => import('../views/MemberHomeView.vue'),
    },
    {
        path: '/score/matches',
        name: 'match-select',
        component: () => import('../views/MatchSelect.vue'),
    },
    {
        path: '/score/:matchId',
        name: 'match-overview',
        component: () => import('../views/MatchOverview.vue'),
        props: (route) => ({ matchId: Number(route.params.matchId) }),
    },
    {
        path: '/score/:matchId/squad',
        name: 'squad-select',
        component: () => import('../views/SquadSelect.vue'),
        props: (route) => ({ matchId: Number(route.params.matchId) }),
    },
    {
        path: '/score/:matchId/scoring',
        name: 'scoring',
        component: () => import('../views/ScoringRouter.vue'),
        props: (route) => ({ matchId: Number(route.params.matchId) }),
    },
    {
        path: '/score/:matchId/scoreboard',
        name: 'scoreboard',
        component: () => import('../views/Scoreboard.vue'),
        props: (route) => ({ matchId: Number(route.params.matchId) }),
    },
    {
        path: '/score/:matchId/matrix',
        name: 'scoring-matrix',
        component: () => import('../views/ScoringMatrix.vue'),
        props: (route) => ({ matchId: Number(route.params.matchId) }),
    },
    {
        path: '/score/:matchId/relay/:squadId/distance/:targetSetId/rollcall',
        name: 'roll-call',
        component: () => import('../views/RollCall.vue'),
        props: (route) => ({
            matchId: Number(route.params.matchId),
            squadId: Number(route.params.squadId),
            targetSetId: Number(route.params.targetSetId),
        }),
    },
    {
        path: '/score/:matchId/relay/:squadId/distance/:targetSetId/scoring',
        name: 'scoped-scoring',
        component: () => import('../views/ScoringFlow.vue'),
        props: (route) => ({
            matchId: Number(route.params.matchId),
            squadId: Number(route.params.squadId),
            targetSetId: Number(route.params.targetSetId),
        }),
    },
    {
        path: '/score/:matchId/elr',
        name: 'elr-scoring',
        component: () => import('../views/ElrScoringFlow.vue'),
        props: (route) => ({ matchId: Number(route.params.matchId) }),
    },
    {
        path: '/score/seasons',
        name: 'season-list',
        component: () => import('../views/SeasonList.vue'),
    },
    {
        path: '/score/seasons/:seasonId',
        name: 'season-leaderboard',
        component: () => import('../views/SeasonLeaderboard.vue'),
        props: (route) => ({ seasonId: Number(route.params.seasonId) }),
    },
];

// When the standalone APK loads `file:///android_asset/scoring-standalone.html`
// the WebView can't navigate to clean URLs like `/score/123` — there's no
// host to resolve them against, so vue-router's `createWebHistory` silently
// breaks every internal navigation (the symptom is a blank screen the moment
// the user taps anything). Hash history works in both `file://` and HTTPS,
// so we pick it whenever the protocol isn't HTTP(S). The Laravel-served PWA
// continues to use clean URLs.
const isFileProtocol = typeof window !== 'undefined'
    && typeof window.location !== 'undefined'
    && window.location.protocol === 'file:';

const router = createRouter({
    history: isFileProtocol ? createWebHashHistory() : createWebHistory(),
    routes,
});

router.afterEach((to) => {
    const matchId = to.params?.matchId;
    if (matchId) {
        localStorage.setItem(LAST_MATCH_KEY, matchId);
    }
});

router.beforeEach(async (to, from, next) => {
    if (to.name === 'home' || to.name === 'member-home') {
        const userStore = useUserStore();
        await userStore.ensureLoaded();

        const mode = localStorage.getItem(MODE_KEY);

        // Users who can score default to Scoring Mode (HomeView) unless they
        // have explicitly chosen Member Mode. This means MDs/ROs/Owners land
        // on the "Start Scoring" page by default instead of being sent to the
        // member feed where scoring entry points are hidden.
        if (to.name === 'home') {
            if (!userStore.canScore) {
                return next({ name: 'member-home' });
            }
            if (mode === 'member') {
                return next({ name: 'member-home' });
            }
        }

        if (to.name === 'member-home' && userStore.canScore && mode !== 'member') {
            return next({ name: 'home' });
        }
    }

    next();
});

export default router;
