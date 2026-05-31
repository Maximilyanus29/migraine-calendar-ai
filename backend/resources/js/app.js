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

app.config.errorHandler = (error, _instance, info) => {
  console.error('Vue render error:', error, info);
};

app.mount('#app');

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    const buildHash =
      document.querySelector('meta[name="app-build-hash"]')?.getAttribute('content') || 'dev';
    navigator.serviceWorker.register(`/sw.js?build=${encodeURIComponent(buildHash)}`)
      .then((registration) => {
        const notifyUpdate = () => {
          window.dispatchEvent(new CustomEvent('sw-update-available'));
        };

        if (registration.waiting) {
          notifyUpdate();
        }

        registration.addEventListener('updatefound', () => {
          const installing = registration.installing;
          if (!installing) return;

          installing.addEventListener('statechange', () => {
            if (installing.state === 'installed' && navigator.serviceWorker.controller) {
              notifyUpdate();
            }
          });
        });
      })
      .catch(() => {
        // no-op: app must continue to work even if SW registration fails
      });
  });
}
