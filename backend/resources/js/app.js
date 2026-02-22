import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { router } from './router';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

const auth = useAuthStore();

router.beforeEach(async (to) => {
  if (!auth.initialized) {
    await auth.loadMe();
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return '/calendar';
  }

  if (!to.meta.guestOnly && !auth.isAuthenticated) {
    return '/login';
  }

  if (to.meta.requiresAdmin && !auth.user?.is_admin) {
    return '/calendar';
  }

  return true;
});

app.use(router);
app.mount('#app');
