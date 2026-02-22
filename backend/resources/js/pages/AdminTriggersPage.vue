<template>
  <section class="card">
    <h1>Модерация пользовательских значений</h1>

    <div class="admin-toolbar">
      <label>
        Статус
        <select v-model="statusFilter" @change="loadTriggers">
          <option value="pending">На проверке</option>
          <option value="approved">Подтверждено</option>
          <option value="rejected">Отклонено</option>
          <option value="all">Все</option>
        </select>
      </label>
      <label>
        Категория
        <select v-model="categoryFilter" @change="loadTriggers">
          <option value="all">Все</option>
          <option value="triggers">Триггеры</option>
          <option value="pain_types">Характер боли</option>
          <option value="localizations">Локализация</option>
          <option value="symptoms">Симптомы</option>
          <option value="auras">Аура</option>
        </select>
      </label>
      <button class="btn" @click="loadTriggers" :disabled="loading">{{ loading ? 'Обновляем...' : 'Обновить' }}</button>
    </div>

    <p class="error" v-if="errorMessage">{{ errorMessage }}</p>
    <p class="muted" v-if="!loading && !errorMessage && items.length === 0">Ничего не найдено.</p>

    <div class="admin-list" v-if="items.length > 0">
      <article class="admin-item" v-for="item in items" :key="item.id">
        <div class="admin-item-title">
          <strong>{{ item.name }}</strong>
          <span class="status-pill" :class="`status-${item.status}`">{{ statusLabel(item.status) }}</span>
        </div>
        <p class="muted">Категория: {{ categoryLabel(item.category) }}</p>
        <p class="muted">Пользователь: {{ item.user?.email || '—' }}</p>
        <p class="muted">Создан: {{ formatDate(item.created_at) }}</p>
        <p class="muted">Использований: {{ item.usage_count }} (у {{ item.unique_users_count }} пользователей)</p>
        <p class="muted" v-if="item.approved_at">Подтвержден: {{ formatDate(item.approved_at) }}</p>

        <div class="actions">
          <button class="btn primary" @click="approve(item.id)" :disabled="loading || item.status === 'approved'">
            Подтвердить
          </button>
          <button class="btn danger" @click="reject(item.id)" :disabled="loading || item.status === 'rejected'">
            Отклонить
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { apiRequest } from '../lib/api';

const statusFilter = ref('pending');
const categoryFilter = ref('all');
const items = ref([]);
const loading = ref(false);
const errorMessage = ref('');

onMounted(() => {
  loadTriggers();
});

async function loadTriggers() {
  loading.value = true;
  errorMessage.value = '';

  try {
    items.value = await apiRequest(
      `/admin/custom-triggers?status=${encodeURIComponent(statusFilter.value)}&category=${encodeURIComponent(categoryFilter.value)}`
    );
  } catch (error) {
    if (error?.status === 403) {
      errorMessage.value = 'Доступ только для администратора';
    } else {
      errorMessage.value = error?.payload?.error || 'Не удалось загрузить список';
    }
  } finally {
    loading.value = false;
  }
}

async function approve(id) {
  loading.value = true;
  errorMessage.value = '';
  try {
    await apiRequest(`/admin/custom-triggers/${id}/approve`, { method: 'POST' });
    await loadTriggers();
  } catch (error) {
    errorMessage.value = error?.payload?.error || 'Не удалось подтвердить триггер';
    loading.value = false;
  }
}

async function reject(id) {
  loading.value = true;
  errorMessage.value = '';
  try {
    await apiRequest(`/admin/custom-triggers/${id}/reject`, { method: 'POST' });
    await loadTriggers();
  } catch (error) {
    errorMessage.value = error?.payload?.error || 'Не удалось отклонить триггер';
    loading.value = false;
  }
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' });
}

function statusLabel(status) {
  if (status === 'approved') return 'Подтвержден';
  if (status === 'rejected') return 'Отклонен';
  return 'На проверке';
}

function categoryLabel(category) {
  if (category === 'pain_types') return 'Характер боли';
  if (category === 'localizations') return 'Локализация';
  if (category === 'symptoms') return 'Симптомы';
  if (category === 'auras') return 'Аура';
  return 'Триггеры';
}
</script>
