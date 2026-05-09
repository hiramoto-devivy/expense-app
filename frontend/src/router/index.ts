import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';
import Dashboard from '../views/Dashboard.vue';
import Login from '../views/Login.vue';
import ExpenseList from '../views/ExpenseList.vue';
import AddExpense from '../views/AddExpense.vue';

const routes = [
  { path: '/login', component: Login, meta: { guest: true } },
  { path: '/', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/expenses', component: ExpenseList, meta: { requiresAuth: true } },
  { path: '/add', component: AddExpense, meta: { requiresAuth: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore();
  
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated()) {
      return next('/login');
    }
    // Verify token on first load if user is not set but token exists
    if (!authStore.user) {
      const isValid = await authStore.verifyToken();
      if (!isValid) return next('/login');
    }
    next();
  } else if (to.meta.guest && authStore.isAuthenticated()) {
    next('/');
  } else {
    next();
  }
});

export default router;
