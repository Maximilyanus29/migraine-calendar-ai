<template>
  <div class="app-shell">
    <div v-if="updateAvailable" class="update-banner">
      <span>Доступна новая версия приложения</span>
      <button class="btn primary" @click="applyUpdate">Обновить</button>
    </div>
    <AppHeader v-if="auth.isAuthenticated" />
    <main class="page-wrap" :class="{ 'page-wrap--calendar': isCalendarRoute }">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { useAuthStore } from './stores/auth';
import AppHeader from './components/AppHeader.vue';

const auth = useAuthStore();
const route = useRoute();
const isCalendarRoute = computed(() => route.path === '/calendar');
const updateAvailable = ref(false);

let controllerChangeBound = false;

function onUpdateAvailable() {
  updateAvailable.value = true;
}

onMounted(() => {
  window.addEventListener('sw-update-available', onUpdateAvailable);
});

onBeforeUnmount(() => {
  window.removeEventListener('sw-update-available', onUpdateAvailable);
});

async function applyUpdate() {
  const registration = await navigator.serviceWorker.getRegistration();
  const waiting = registration?.waiting;
  if (!waiting) {
    window.location.reload();
    return;
  }

  if (!controllerChangeBound) {
    controllerChangeBound = true;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      window.location.reload();
    });
  }

  waiting.postMessage({ type: 'SKIP_WAITING' });
}
</script>
