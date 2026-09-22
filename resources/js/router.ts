import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import MetaIndex from '@/pages/MetaIndex.vue';
import SpeciesDetail from '@/pages/SpeciesDetail.vue';

const routes: RouteRecordRaw[] = [
    { path: '/', name: 'meta', component: MetaIndex },
    { path: '/especie/:slug', name: 'especie', component: SpeciesDetail, props: true },
    { path: '/:pathMatch(.*)*', redirect: { name: 'meta' } },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});
