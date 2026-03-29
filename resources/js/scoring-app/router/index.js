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
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
