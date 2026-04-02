import { createRouter, createWebHistory } from 'vue-router';

const LAST_MATCH_KEY = 'dc_last_match_id';
const SQUAD_LOCK_KEY = 'dc_locked_squad';
const STAGE_LOCK_KEY = 'dc_locked_stage';

function getLockedMatchId() {
    try {
        const squad = localStorage.getItem(SQUAD_LOCK_KEY);
        if (squad) return JSON.parse(squad).matchId;
        const stage = localStorage.getItem(STAGE_LOCK_KEY);
        if (stage) return JSON.parse(stage).matchId;
    } catch { /* ignore */ }
    return null;
}

const routes = [
    {
        path: '/score',
        name: 'home',
        component: () => import('../views/HomeView.vue'),
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

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.afterEach((to) => {
    const matchId = to.params?.matchId;
    if (matchId) {
        localStorage.setItem(LAST_MATCH_KEY, matchId);
    }
});

router.beforeEach((to, from, next) => {
    if ((to.name === 'home' || to.name === 'match-select') && !from.name) {
        const lockedMatchId = getLockedMatchId();
        if (lockedMatchId) {
            return next({ name: 'match-overview', params: { matchId: lockedMatchId } });
        }
    }
    next();
});

export default router;
