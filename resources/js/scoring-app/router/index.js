import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/score',
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
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
