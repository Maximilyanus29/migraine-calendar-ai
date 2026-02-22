<template>
  <header class="topbar">
    <div class="brand">Migraine AI</div>
    <nav class="nav">
      <RouterLink to="/calendar">Календарь</RouterLink>
      <RouterLink to="/graphs">Графики</RouterLink>
      <RouterLink v-if="auth.user?.is_admin" to="/admin/triggers">Модерация</RouterLink>
    </nav>
    <div class="right">
      <span class="email">{{ auth.user?.email }}</span>
      <button class="btn" @click="onLogout">Выйти</button>
    </div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

async function onLogout() {
  await auth.logout();
  await router.push('/login');
}
</script>
