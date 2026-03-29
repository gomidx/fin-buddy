import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', name: 'auth', component: () => import('../views/AuthView.vue'), meta: { guest: true } },
  { path: '/dashboard', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { requiresAuth: true } },
  { path: '/insights', name: 'insights', component: () => import('../views/InsightsView.vue'), meta: { requiresAuth: true } },
  { path: '/history', name: 'history', component: () => import('../views/TransactionHistoryView.vue'), meta: { requiresAuth: true } },
  { path: '/emergency-fund', name: 'emergency-fund', component: () => import('../views/EmergencyFundView.vue'), meta: { requiresAuth: true } },
  { path: '/investments', name: 'investments', component: () => import('../views/InvestmentsView.vue'), meta: { requiresAuth: true } },
  { path: '/goals', name: 'goals', component: () => import('../views/GoalsView.vue'), meta: { requiresAuth: true } },
  { path: '/profile', name: 'profile', component: () => import('../views/ProfileView.vue'), meta: { requiresAuth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) return { name: 'auth' }
  if (to.meta.guest && auth.isAuthenticated) return { name: 'dashboard' }
})

export default router
