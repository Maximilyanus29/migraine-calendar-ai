<template>
  <section class="login-page card narrow">
    <h1>Вход</h1>
    <p class="muted">Используй demo-аккаунт: demo@example.com / password</p>

    <form @submit.prevent="submit" class="form-grid">
      <label>
        Email
        <input v-model="email" type="email" required />
      </label>

      <label>
        Пароль
        <input v-model="password" type="password" required />
      </label>

      <p class="error" v-if="errorMessage">{{ errorMessage }}</p>

      <button class="btn primary" :disabled="loading">
        {{ loading ? 'Вход...' : 'Войти' }}
      </button>

      <RouterLink to="/register" class="btn">Нет аккаунта? Зарегистрироваться</RouterLink>
    </form>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const email = ref('demo@example.com');
const password = ref('password');
const errorMessage = ref('');
const loading = ref(false);

async function submit() {
  loading.value = true;
  errorMessage.value = '';

  try {
    await auth.login(email.value, password.value);
    await router.push('/calendar');
  } catch (error) {
    errorMessage.value = error?.payload?.error || 'Ошибка входа';
  } finally {
    loading.value = false;
  }
}
</script>
