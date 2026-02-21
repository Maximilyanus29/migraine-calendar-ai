<template>
  <section class="card">
    <h1>{{ isEdit ? 'Редактирование приступа' : 'Новый приступ' }}</h1>

    <form class="form-grid" @submit.prevent="save">
      <label>
        Дата начала
        <input v-model="form.start_at" type="datetime-local" required />
      </label>

      <label>
        Дата окончания
        <input v-model="form.end_at" type="datetime-local" />
      </label>
      <p class="muted" v-if="durationLabel">Длительность: {{ durationLabel }}</p>

      <label>
        Интенсивность (1-10)
        <input v-model.number="form.intensity" type="number" min="1" max="10" required />
      </label>

      <label>
        Лекарства
        <input v-model="form.medications" type="text" />
      </label>

      <label>
        Помогло?
        <select v-model="reliefSelect">
          <option value="">Не указано</option>
          <option value="true">Да</option>
          <option value="false">Нет</option>
        </select>
      </label>

      <label>
        Заметки
        <textarea v-model="form.notes" rows="3" maxlength="2000" />
      </label>

      <fieldset>
        <legend>Что спровоцировало</legend>
        <div class="chip-grid">
          <label v-for="item in options.triggers" :key="item" class="chip">
            <input type="checkbox" :value="item" v-model="form.triggers" />
            <span>{{ item }}</span>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Характер боли</legend>
        <div class="chip-grid">
          <label v-for="item in options.pain_types" :key="item" class="chip">
            <input type="checkbox" :value="item" v-model="form.pain_types" />
            <span>{{ item }}</span>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Локализация</legend>
        <div class="chip-grid">
          <label v-for="item in options.localizations" :key="item" class="chip">
            <input type="checkbox" :value="item" v-model="form.localizations" />
            <span>{{ item }}</span>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Симптомы</legend>
        <div class="chip-grid">
          <label v-for="item in options.symptoms" :key="item" class="chip">
            <input type="checkbox" :value="item" v-model="form.symptoms" />
            <span>{{ item }}</span>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Аура</legend>
        <div class="chip-grid">
          <label v-for="item in options.auras" :key="item" class="chip">
            <input type="checkbox" :value="item" v-model="form.auras" />
            <span>{{ item }}</span>
          </label>
        </div>
      </fieldset>

      <p class="error" v-if="errorMessage">{{ errorMessage }}</p>

      <div class="actions">
        <button class="btn primary" :disabled="loading">{{ loading ? 'Сохраняем...' : 'Сохранить' }}</button>
        <button class="btn" type="button" @click="backToCalendar">Отмена</button>
        <button class="btn danger" v-if="isEdit" type="button" @click="removeAttack">Удалить</button>
      </div>
    </form>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiRequest } from '../lib/api';

const route = useRoute();
const router = useRouter();
const isEdit = computed(() => !!route.params.id);

const options = reactive({
  triggers: [],
  pain_types: [],
  localizations: [],
  symptoms: [],
  auras: [],
});

const form = reactive({
  start_at: '',
  end_at: '',
  intensity: 5,
  medications: '',
  relief: null,
  pain_types: [],
  localizations: [],
  triggers: [],
  symptoms: [],
  auras: [],
  notes: '',
});

const reliefSelect = computed({
  get() {
    if (form.relief === true) return 'true';
    if (form.relief === false) return 'false';
    return '';
  },
  set(value) {
    if (value === 'true') form.relief = true;
    else if (value === 'false') form.relief = false;
    else form.relief = null;
  },
});

const loading = ref(false);
const errorMessage = ref('');

onMounted(async () => {
  await loadOptions();

  if (isEdit.value) {
    await loadAttackForEdit();
  } else {
    await hydrateFromLastAttack();
    applyInitialStartFromCalendarClick();
  }
});

async function loadOptions() {
  const data = await apiRequest('/meta/options');
  Object.assign(options, data);
}

async function loadAttackForEdit() {
  const data = await apiRequest(`/attacks/${route.params.id}`);
  fillForm(data);
}

async function hydrateFromLastAttack() {
  const data = await apiRequest('/attacks/last');
  if (!data || !data.id) return;

  form.intensity = data.intensity ?? form.intensity;
  form.medications = data.medications ?? '';
  form.relief = data.relief ?? null;
  form.pain_types = data.pain_types ?? [];
  form.localizations = data.localizations ?? [];
  form.triggers = data.triggers ?? [];
  form.symptoms = data.symptoms ?? [];
  form.auras = data.auras ?? [];
  form.notes = data.notes ?? '';
}

function applyInitialStartFromCalendarClick() {
  const now = new Date();
  const queryDate = String(route.query.date || '');
  const ratio = Number(route.query.ratio || 0);

  let start;
  if (queryDate === toDateKey(now)) {
    start = now;
  } else {
    const base = parseDateKey(queryDate) || now;
    const minutes = Math.round(Math.max(0, Math.min(1, ratio)) * 24 * 60);
    start = new Date(base.getFullYear(), base.getMonth(), base.getDate(), 0, minutes);
  }

  form.start_at = toInputDateTime(start);
  form.end_at = '';
}

function fillForm(data) {
  form.start_at = toInputDateTime(new Date(data.start_at));
  form.end_at = data.end_at ? toInputDateTime(new Date(data.end_at)) : '';
  form.intensity = data.intensity;
  form.medications = data.medications ?? '';
  form.relief = data.relief ?? null;
  form.pain_types = data.pain_types ?? [];
  form.localizations = data.localizations ?? [];
  form.triggers = data.triggers ?? [];
  form.symptoms = data.symptoms ?? [];
  form.auras = data.auras ?? [];
  form.notes = data.notes ?? '';
}

async function save() {
  loading.value = true;
  errorMessage.value = '';

  try {
    const startDate = parseLocalDateTime(form.start_at);
    const endDate = parseLocalDateTime(form.end_at);

    if (!startDate) {
      throw new Error('Проверь дату и время начала');
    }

    const nowDate = new Date();
    if (startDate > nowDate) {
      throw new Error('Нельзя создавать приступ в будущем');
    }

    if (endDate) {
      if (endDate <= startDate) {
        throw new Error('Дата окончания должна быть позже даты начала');
      }
      if (endDate > nowDate) {
        throw new Error('Дата окончания не может быть в будущем');
      }

      const durationMs = endDate.getTime() - startDate.getTime();
      const durationHours = durationMs / (1000 * 60 * 60);
      if (durationHours > 72) {
        const approved = confirm(
          `Длительность приступа ${Math.round(durationHours)} ч. Это больше 72 часов. Сохранить?`
        );
        if (!approved) {
          loading.value = false;
          return;
        }
      }
    }

    const payload = {
      start_at: startDate.toISOString(),
      end_at: endDate ? endDate.toISOString() : null,
      intensity: Number(form.intensity),
      medications: form.medications || null,
      relief: form.relief,
      pain_types: [...form.pain_types],
      localizations: [...form.localizations],
      triggers: [...form.triggers],
      symptoms: [...form.symptoms],
      auras: [...form.auras],
      notes: form.notes || null,
    };

    if (isEdit.value) {
      await apiRequest(`/attacks/${route.params.id}`, { method: 'PUT', body: payload });
    } else {
      await apiRequest('/attacks', { method: 'POST', body: payload });
    }

    await router.push('/calendar');
  } catch (error) {
    errorMessage.value = error?.payload?.error || 'Ошибка сохранения';
  } finally {
    loading.value = false;
  }
}

async function removeAttack() {
  if (!confirm('Удалить приступ?')) return;

  await apiRequest(`/attacks/${route.params.id}`, { method: 'DELETE' });
  await router.push('/calendar');
}

function backToCalendar() {
  router.push('/calendar');
}

function toInputDateTime(date) {
  const offset = date.getTimezoneOffset();
  const local = new Date(date.getTime() - offset * 60 * 1000);
  return local.toISOString().slice(0, 16);
}

const durationLabel = computed(() => {
  const startDate = parseLocalDateTime(form.start_at);
  const endDate = parseLocalDateTime(form.end_at);
  if (!startDate) return '';

  const effectiveEnd = endDate || new Date();
  if (effectiveEnd <= startDate) return '';

  const minutes = Math.round((effectiveEnd.getTime() - startDate.getTime()) / 60000);
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  const suffix = endDate ? '' : ' (до текущего времени)';
  return `${h} ч ${String(m).padStart(2, '0')} мин${suffix}`;
});

function parseLocalDateTime(value) {
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
  if (!match) return null;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const hours = Number(match[4]);
  const minutes = Number(match[5]);

  const date = new Date(year, month - 1, day, hours, minutes, 0, 0);
  if (Number.isNaN(date.getTime())) return null;
  return date;
}

function toDateKey(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function parseDateKey(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
  const [y, m, d] = value.split('-').map(Number);
  return new Date(y, m - 1, d);
}
</script>
