<template>
  <div class="app-shell">
    <AppHeader v-if="auth.isAuthenticated" />
    <main class="page-wrap">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { RouterView } from 'vue-router';
import { useAuthStore } from './stores/auth';
import AppHeader from './components/AppHeader.vue';

const auth = useAuthStore();
</script>
