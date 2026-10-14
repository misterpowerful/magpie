import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import LoginView from './views/LoginView.vue';
import DashboardView from './views/DashboardView.vue';
import AdminSitesView from './views/AdminSitesView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
        { path: '/', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
        { path: '/admin/sites', name: 'admin-sites', component: AdminSitesView, meta: { requiresAuth: true } },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (! auth.checked) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && ! auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
