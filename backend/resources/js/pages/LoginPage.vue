<template>
  <section class="login-page card narrow">
    <h1>Вход</h1>
    <p class="muted">Введи свои данные или используй демо-вход.</p>

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

      <button type="button" class="btn" :disabled="loading" @click="loginAsDemo">
        Войти в демо-аккаунт
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

const email = ref('');
const password = ref('');
const errorMessage = ref('');
const loading = ref(false);

async function submit() {
  loading.value = true;
  errorMessage.value = '';

  try {
    await auth.login(email.value, password.value);
    await router.push('/calendar');
  } catch (error) {
    if (error?.status === 429) {
      errorMessage.value = 'Слишком много попыток входа. Попробуй позже.';
    } else {
      errorMessage.value = error?.payload?.error || 'Ошибка входа';
    }
  } finally {
    loading.value = false;
  }
}

async function loginAsDemo() {
  loading.value = true;
  errorMessage.value = '';

  try {
    await auth.login('demo@example.com', 'password');
    await router.push('/calendar');
  } catch (error) {
    if (error?.status === 429) {
      errorMessage.value = 'Слишком много попыток входа. Попробуй позже.';
    } else {
      errorMessage.value = error?.payload?.error || 'Не удалось войти в демо-аккаунт';
    }
  } finally {
    loading.value = false;
  }
}
</script>
