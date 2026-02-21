<template>
  <section class="card narrow">
    <h1>Регистрация</h1>

    <form @submit.prevent="submit" class="form-grid">
      <label>
        Имя
        <input v-model="name" type="text" maxlength="120" required />
      </label>

      <label>
        Email
        <input v-model="email" type="email" maxlength="190" required />
      </label>

      <label>
        Пароль
        <input v-model="password" type="password" minlength="8" required />
      </label>

      <p class="error" v-if="errorMessage">{{ errorMessage }}</p>

      <button class="btn primary" :disabled="loading">
        {{ loading ? 'Создание...' : 'Создать аккаунт' }}
      </button>

      <RouterLink to="/login" class="btn">Уже есть аккаунт? Войти</RouterLink>
    </form>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const name = ref('');
const email = ref('');
const password = ref('');
const errorMessage = ref('');
const loading = ref(false);

async function submit() {
  loading.value = true;
  errorMessage.value = '';

  try {
    await auth.register(name.value.trim(), email.value.trim(), password.value);
    await router.push('/calendar');
  } catch (error) {
    const details = error?.payload?.errors;
    if (details && typeof details === 'object') {
      const firstField = Object.keys(details)[0];
      const firstError = Array.isArray(details[firstField]) ? details[firstField][0] : null;
      errorMessage.value = firstError || error?.payload?.message || 'Ошибка регистрации';
    } else {
      errorMessage.value = error?.payload?.error || error?.payload?.message || 'Ошибка регистрации';
    }
  } finally {
    loading.value = false;
  }
}
</script>
