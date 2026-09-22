import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
    { path: '/', name: 'meta', component: () => import('@/pages/MetaIndex.vue') },
    { path: '/matchups', name: 'matchups', component: () => import('@/pages/Matchups.vue') },
    { path: '/equipos', name: 'equipos', component: () => import('@/pages/Teams.vue') },
    { path: '/equipos/:id', name: 'equipo', component: () => import('@/pages/TeamDetail.vue'), props: true },
    { path: '/constructor', name: 'constructor', component: () => import('@/pages/Builder.vue') },
    { path: '/pokedex', name: 'pokedex', component: () => import('@/pages/Pokedex.vue') },
    { path: '/pokedex/:slug', name: 'especie', component: () => import('@/pages/SpeciesDetail.vue'), props: true },
    { path: '/especie/:slug', redirect: (to) => ({ name: 'especie', params: to.params }) },
    { path: '/:pathMatch(.*)*', redirect: { name: 'meta' } },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});
