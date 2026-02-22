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
          <label
            v-for="item in standardOptions('triggers')"
            :key="item"
            class="chip"
            :class="{ selected: form.triggers.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.triggers" />
            <span>{{ optionLabel('triggers', item) }}</span>
          </label>
        </div>

        <div class="custom-inline-add">
          <input
            v-model="customOptionDraft.triggers"
            type="text"
            maxlength="80"
            placeholder="Добавить свой триггер"
            @keydown.enter.prevent="addCustomOption('triggers')"
          />
          <button type="button" class="btn" :disabled="customOptionLoading.triggers" @click="addCustomOption('triggers')">
            {{ customOptionLoading.triggers ? 'Добавляем...' : 'Добавить' }}
          </button>
        </div>
        <p class="muted" v-if="customOptionHint.triggers">{{ customOptionHint.triggers }}</p>
        <p class="error" v-if="customOptionError.triggers">{{ customOptionError.triggers }}</p>
        <div class="chip-grid" v-if="customOptions('triggers').length > 0">
          <label
            v-for="item in customOptions('triggers')"
            :key="item"
            class="chip"
            :class="{ selected: form.triggers.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.triggers" />
            <span>{{ optionLabel('triggers', item) }}</span>
            <small class="chip-note">{{ statusLabel(customStatus('triggers', item)) }}</small>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Характер боли</legend>
        <div class="chip-grid">
          <label
            v-for="item in standardOptions('pain_types')"
            :key="item"
            class="chip"
            :class="{ selected: form.pain_types.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.pain_types" />
            <span>{{ optionLabel('pain_types', item) }}</span>
          </label>
        </div>
        <div class="custom-inline-add">
          <input
            v-model="customOptionDraft.pain_types"
            type="text"
            maxlength="80"
            placeholder="Добавить свой вариант"
            @keydown.enter.prevent="addCustomOption('pain_types')"
          />
          <button type="button" class="btn" :disabled="customOptionLoading.pain_types" @click="addCustomOption('pain_types')">
            {{ customOptionLoading.pain_types ? 'Добавляем...' : 'Добавить' }}
          </button>
        </div>
        <p class="muted" v-if="customOptionHint.pain_types">{{ customOptionHint.pain_types }}</p>
        <p class="error" v-if="customOptionError.pain_types">{{ customOptionError.pain_types }}</p>
        <div class="chip-grid" v-if="customOptions('pain_types').length > 0">
          <label
            v-for="item in customOptions('pain_types')"
            :key="item"
            class="chip"
            :class="{ selected: form.pain_types.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.pain_types" />
            <span>{{ optionLabel('pain_types', item) }}</span>
            <small class="chip-note">{{ statusLabel(customStatus('pain_types', item)) }}</small>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Локализация</legend>
        <div class="chip-grid">
          <label
            v-for="item in standardOptions('localizations')"
            :key="item"
            class="chip"
            :class="{ selected: form.localizations.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.localizations" />
            <span>{{ optionLabel('localizations', item) }}</span>
          </label>
        </div>
        <div class="custom-inline-add">
          <input
            v-model="customOptionDraft.localizations"
            type="text"
            maxlength="80"
            placeholder="Добавить свой вариант"
            @keydown.enter.prevent="addCustomOption('localizations')"
          />
          <button type="button" class="btn" :disabled="customOptionLoading.localizations" @click="addCustomOption('localizations')">
            {{ customOptionLoading.localizations ? 'Добавляем...' : 'Добавить' }}
          </button>
        </div>
        <p class="muted" v-if="customOptionHint.localizations">{{ customOptionHint.localizations }}</p>
        <p class="error" v-if="customOptionError.localizations">{{ customOptionError.localizations }}</p>
        <div class="chip-grid" v-if="customOptions('localizations').length > 0">
          <label
            v-for="item in customOptions('localizations')"
            :key="item"
            class="chip"
            :class="{ selected: form.localizations.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.localizations" />
            <span>{{ optionLabel('localizations', item) }}</span>
            <small class="chip-note">{{ statusLabel(customStatus('localizations', item)) }}</small>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Симптомы</legend>
        <div class="chip-grid">
          <label
            v-for="item in standardOptions('symptoms')"
            :key="item"
            class="chip"
            :class="{ selected: form.symptoms.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.symptoms" />
            <span>{{ optionLabel('symptoms', item) }}</span>
          </label>
        </div>
        <div class="custom-inline-add">
          <input
            v-model="customOptionDraft.symptoms"
            type="text"
            maxlength="80"
            placeholder="Добавить свой вариант"
            @keydown.enter.prevent="addCustomOption('symptoms')"
          />
          <button type="button" class="btn" :disabled="customOptionLoading.symptoms" @click="addCustomOption('symptoms')">
            {{ customOptionLoading.symptoms ? 'Добавляем...' : 'Добавить' }}
          </button>
        </div>
        <p class="muted" v-if="customOptionHint.symptoms">{{ customOptionHint.symptoms }}</p>
        <p class="error" v-if="customOptionError.symptoms">{{ customOptionError.symptoms }}</p>
        <div class="chip-grid" v-if="customOptions('symptoms').length > 0">
          <label
            v-for="item in customOptions('symptoms')"
            :key="item"
            class="chip"
            :class="{ selected: form.symptoms.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.symptoms" />
            <span>{{ optionLabel('symptoms', item) }}</span>
            <small class="chip-note">{{ statusLabel(customStatus('symptoms', item)) }}</small>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Аура</legend>
        <div class="chip-grid">
          <label
            v-for="item in standardOptions('auras')"
            :key="item"
            class="chip"
            :class="{ selected: form.auras.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.auras" />
            <span>{{ optionLabel('auras', item) }}</span>
          </label>
        </div>
        <div class="custom-inline-add">
          <input
            v-model="customOptionDraft.auras"
            type="text"
            maxlength="80"
            placeholder="Добавить свой вариант"
            @keydown.enter.prevent="addCustomOption('auras')"
          />
          <button type="button" class="btn" :disabled="customOptionLoading.auras" @click="addCustomOption('auras')">
            {{ customOptionLoading.auras ? 'Добавляем...' : 'Добавить' }}
          </button>
        </div>
        <p class="muted" v-if="customOptionHint.auras">{{ customOptionHint.auras }}</p>
        <p class="error" v-if="customOptionError.auras">{{ customOptionError.auras }}</p>
        <div class="chip-grid" v-if="customOptions('auras').length > 0">
          <label
            v-for="item in customOptions('auras')"
            :key="item"
            class="chip"
            :class="{ selected: form.auras.includes(item) }"
          >
            <input type="checkbox" :value="item" v-model="form.auras" />
            <span>{{ optionLabel('auras', item) }}</span>
            <small class="chip-note">{{ statusLabel(customStatus('auras', item)) }}</small>
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
  labels: {},
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
const customOptionDraft = reactive({
  triggers: '',
  pain_types: '',
  localizations: '',
  symptoms: '',
  auras: '',
});
const customOptionError = reactive({
  triggers: '',
  pain_types: '',
  localizations: '',
  symptoms: '',
  auras: '',
});
const customOptionHint = reactive({
  triggers: 'Можно добавить свой вариант в эту группу.',
  pain_types: 'Можно добавить свой вариант в эту группу.',
  localizations: 'Можно добавить свой вариант в эту группу.',
  symptoms: 'Можно добавить свой вариант в эту группу.',
  auras: 'Можно добавить свой вариант в эту группу.',
});
const customOptionLoading = reactive({
  triggers: false,
  pain_types: false,
  localizations: false,
  symptoms: false,
  auras: false,
});
const customOptionStatusByCategory = reactive({
  triggers: {},
  pain_types: {},
  localizations: {},
  symptoms: {},
  auras: {},
});

onMounted(async () => {
  await loadOptions();
  try {
    await loadCustomOptionStatuses();
  } catch {
    for (const key of Object.keys(customOptionHint)) {
      customOptionHint[key] = 'Не удалось загрузить статусы модерации. Попробуй обновить страницу позже.';
    }
  }

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

function optionLabel(group, key) {
  return options.labels?.[group]?.[key] ?? key;
}

function standardOptions(category) {
  return (options[category] ?? []).filter((item) => !String(item).startsWith('custom:'));
}

function customOptions(category) {
  return (options[category] ?? []).filter((item) => String(item).startsWith('custom:'));
}

function customStatus(category, key) {
  return customOptionStatusByCategory[category]?.[key] ?? null;
}

async function addCustomOption(category) {
  customOptionError[category] = '';
  const name = String(customOptionDraft[category] ?? '').trim();
  if (name.length < 2) {
    customOptionError[category] = 'Минимум 2 символа';
    return;
  }

  customOptionLoading[category] = true;
  try {
    await apiRequest('/custom-options', {
      method: 'POST',
      body: { category, name },
    });
    customOptionDraft[category] = '';
    customOptionHint[category] = 'Значение добавлено. Можно сразу выбрать.';
    await loadOptions();
    await loadCustomOptionStatuses();
  } catch (error) {
    if (error?.status === 429) {
      customOptionError[category] = 'Слишком много новых значений. Лимит: 10 в день.';
    } else {
      customOptionError[category] = error?.payload?.error || 'Не удалось добавить значение';
    }
  } finally {
    customOptionLoading[category] = false;
  }
}

async function loadCustomOptionStatuses() {
  for (const category of Object.keys(customOptionStatusByCategory)) {
    Object.keys(customOptionStatusByCategory[category]).forEach((key) => {
      delete customOptionStatusByCategory[category][key];
    });
  }

  const items = await apiRequest('/custom-options');
  for (const item of items) {
    if (!customOptionStatusByCategory[item.category]) continue;
    customOptionStatusByCategory[item.category][`custom:${item.id}`] = item.status;
  }
}

function statusLabel(status) {
  if (status === 'approved') return 'подтвержден';
  if (status === 'rejected') return 'отклонен';
  return 'на проверке';
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
    if (error?.status === 429) {
      errorMessage.value = 'Слишком много операций. Попробуй позже.';
    } else {
      errorMessage.value = error?.payload?.error || 'Ошибка сохранения';
    }
  } finally {
    loading.value = false;
  }
}

async function removeAttack() {
  if (!confirm('Удалить приступ?')) return;

  try {
    await apiRequest(`/attacks/${route.params.id}`, { method: 'DELETE' });
    await router.push('/calendar');
  } catch (error) {
    if (error?.status === 429) {
      errorMessage.value = 'Слишком много операций. Попробуй позже.';
    } else {
      errorMessage.value = error?.payload?.error || 'Ошибка удаления';
    }
  }
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
