import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from './pages/LoginPage.vue';
import RegisterPage from './pages/RegisterPage.vue';
import CalendarPage from './pages/CalendarPage.vue';
import AttackFormPage from './pages/AttackFormPage.vue';
import GraphsPage from './pages/GraphsPage.vue';
import AdminTriggersPage from './pages/AdminTriggersPage.vue';

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/calendar' },
    { path: '/login', component: LoginPage, meta: { guestOnly: true } },
    { path: '/register', component: RegisterPage, meta: { guestOnly: true } },
    { path: '/calendar', component: CalendarPage },
    { path: '/graphs', component: GraphsPage },
    { path: '/admin/triggers', component: AdminTriggersPage, meta: { requiresAdmin: true } },
    { path: '/attacks/new', component: AttackFormPage },
    { path: '/attacks/:id/edit', component: AttackFormPage },
  ],
});
