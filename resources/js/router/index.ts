import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/features/auth/stores/auth'
import DashboardView from '@/features/listings/views/DashboardView.vue'

declare module 'vue-router' {
    interface RouteMeta {
        requiresAuth?: boolean
        guestOnly?: boolean
    }
}

// The dashboard is the landing page, so it ships with the app; every
// other page is its own chunk, loaded the first time it's opened.
const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: () => import('@/features/auth/views/LoginView.vue'), meta: { guestOnly: true } },
        { path: '/', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
        { path: '/insights', name: 'insights', component: () => import('@/features/insights/views/InsightsView.vue'), meta: { requiresAuth: true } },
        { path: '/admin/sites', name: 'admin-sites', component: () => import('@/features/sites/views/AdminSitesView.vue'), meta: { requiresAuth: true } },
    ],
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()

    if (!auth.checked) {
        await auth.fetchUser()
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' }
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' }
    }

    return true
})

export default router
