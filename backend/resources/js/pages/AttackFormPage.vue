<template>
  <section class="card" data-testid="attack-form">
    <div v-if="pageLoading" class="page-loading" data-testid="attack-form-loading">
      <p class="muted">Загрузка формы...</p>
    </div>

    <div v-else-if="loadError" class="page-loading">
      <p class="error">{{ loadError }}</p>
      <button type="button" class="btn" @click="backToCalendar">Вернуться в календарь</button>
    </div>

    <template v-else>
    <h1 data-testid="attack-form-title">{{ isEdit ? 'Редактирование приступа' : 'Новый приступ' }}</h1>

    <form class="form-grid" @submit.prevent="save" @keydown.enter="onFormEnter">
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
        <input v-model.number="form.intensity" type="number" min="1" max="10" required data-testid="attack-intensity" />
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
          <div
            v-for="item in allOptions('triggers')"
            :key="item"
            class="chip"
            :class="{ selected: isSelected('triggers', item) }"
            role="checkbox"
            :aria-checked="isSelected('triggers', item)"
            tabindex="0"
            @click="toggleOption('triggers', item)"
            @keydown.enter.prevent="toggleOption('triggers', item)"
            @keydown.space.prevent="toggleOption('triggers', item)"
          >
            <span>{{ optionLabel('triggers', item) }}</span>
          </div>
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
      </fieldset>

      <fieldset>
        <legend>Характер боли</legend>
        <div class="chip-grid">
          <div
            v-for="item in allOptions('pain_types')"
            :key="item"
            class="chip"
            :class="{ selected: isSelected('pain_types', item) }"
            role="checkbox"
            :aria-checked="isSelected('pain_types', item)"
            tabindex="0"
            @click="toggleOption('pain_types', item)"
            @keydown.enter.prevent="toggleOption('pain_types', item)"
            @keydown.space.prevent="toggleOption('pain_types', item)"
          >
            <span>{{ optionLabel('pain_types', item) }}</span>
          </div>
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
      </fieldset>

      <fieldset>
        <legend>Локализация</legend>
        <div class="chip-grid">
          <div
            v-for="item in allOptions('localizations')"
            :key="item"
            class="chip"
            :class="{ selected: isSelected('localizations', item) }"
            role="checkbox"
            :aria-checked="isSelected('localizations', item)"
            tabindex="0"
            @click="toggleOption('localizations', item)"
            @keydown.enter.prevent="toggleOption('localizations', item)"
            @keydown.space.prevent="toggleOption('localizations', item)"
          >
            <span>{{ optionLabel('localizations', item) }}</span>
          </div>
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
      </fieldset>

      <fieldset>
        <legend>Симптомы</legend>
        <div class="chip-grid">
          <div
            v-for="item in allOptions('symptoms')"
            :key="item"
            class="chip"
            :class="{ selected: isSelected('symptoms', item) }"
            role="checkbox"
            :aria-checked="isSelected('symptoms', item)"
            tabindex="0"
            @click="toggleOption('symptoms', item)"
            @keydown.enter.prevent="toggleOption('symptoms', item)"
            @keydown.space.prevent="toggleOption('symptoms', item)"
          >
            <span>{{ optionLabel('symptoms', item) }}</span>
          </div>
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
      </fieldset>

      <fieldset>
        <legend>Аура</legend>
        <div class="chip-grid">
          <div
            v-for="item in allOptions('auras')"
            :key="item"
            class="chip"
            :class="{ selected: isSelected('auras', item) }"
            role="checkbox"
            :aria-checked="isSelected('auras', item)"
            tabindex="0"
            @click="toggleOption('auras', item)"
            @keydown.enter.prevent="toggleOption('auras', item)"
            @keydown.space.prevent="toggleOption('auras', item)"
          >
            <span>{{ optionLabel('auras', item) }}</span>
          </div>
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
      </fieldset>

      <p class="error" v-if="errorMessage">{{ errorMessage }}</p>

      <div class="actions">
        <button type="submit" class="btn primary" :disabled="loading" data-testid="attack-save">
          {{ loading ? 'Сохраняем...' : 'Сохранить' }}
        </button>
        <button class="btn" type="button" @click="backToCalendar">Отмена</button>
        <button class="btn danger" v-if="isEdit" type="button" data-testid="attack-delete" @click="removeAttack">
          Удалить
        </button>
      </div>
    </form>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiRequest, formatApiError } from '../lib/api';
import {
  LOCAL_OPTION_CATEGORIES,
  isLocalKey,
  migrateLocalCustomOptionsToServer,
} from '../lib/localCustomOptions';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
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

const pageLoading = ref(true);
const loadError = ref('');
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
  triggers: '',
  pain_types: '',
  localizations: '',
  symptoms: '',
  auras: '',
});
const customOptionLoading = reactive({
  triggers: false,
  pain_types: false,
  localizations: false,
  symptoms: false,
  auras: false,
});
const localOptionLabels = reactive(
  Object.fromEntries(LOCAL_OPTION_CATEGORIES.map((category) => [category, {}]))
);

onMounted(async () => {
  loadError.value = '';
  try {
    await loadOptions();
    await migrateLegacyLocalOptions();

    if (isEdit.value) {
      await loadAttackForEdit();
    } else {
      await hydrateFromLastAttack();
      applyInitialStartFromCalendarClick();
    }

    ensureLegacyLocalOptionsFromFormValues();
    ensureFormArrays();
  } catch (error) {
    loadError.value = formatApiError(error, 'Не удалось загрузить форму');
  } finally {
    pageLoading.value = false;
  }
});

async function loadOptions() {
  const data = await apiRequest('/meta/options');

  for (const category of LOCAL_OPTION_CATEGORIES) {
    options[category] = Array.isArray(data[category]) ? [...data[category]] : [];
  }

  if (!options.labels || typeof options.labels !== 'object') {
    options.labels = {};
  }

  for (const category of LOCAL_OPTION_CATEGORIES) {
    options.labels[category] = { ...(data.labels?.[category] ?? {}) };
  }
}

function ensureFormArrays() {
  for (const category of LOCAL_OPTION_CATEGORIES) {
    if (!Array.isArray(form[category])) {
      form[category] = [];
    }
  }
}

function isSelected(category, item) {
  return Array.isArray(form[category]) && form[category].includes(item);
}

function toggleOption(category, item) {
  if (!Array.isArray(form[category])) {
    form[category] = [];
  }

  const index = form[category].indexOf(item);
  if (index >= 0) {
    form[category].splice(index, 1);
  } else {
    form[category].push(item);
  }
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
  ensureFormArrays();
}

function applyInitialStartFromCalendarClick() {
  const now = new Date();
  const queryDate = String(route.query.date || '');
  const ratio = Number(route.query.ratio || 0);

  const base = parseDateKey(queryDate) || now;
  const minutes = Math.round(Math.max(0, Math.min(1, ratio)) * 24 * 60);
  let start = new Date(base.getFullYear(), base.getMonth(), base.getDate(), 0, minutes);

  if (queryDate === toDateKey(now) && start > now) {
    start = now;
  }

  form.start_at = toInputDateTime(start);
  form.end_at = '';
}

function onFormEnter(event) {
  const target = event.target;
  if (!(target instanceof HTMLElement)) {
    return;
  }

  if (target.tagName === 'TEXTAREA') {
    return;
  }

  if (target.closest('.custom-inline-add')) {
    return;
  }

  event.preventDefault();
}

function filterSelectableOptions(category) {
  const allowed = new Set(options[category] ?? []);
  if (!Array.isArray(form[category])) {
    return [];
  }
  return form[category].filter((key) => allowed.has(key));
}

async function migrateLegacyLocalOptions() {
  const userId = auth.user?.id;
  if (!userId) {
    return;
  }

  const migrated = await migrateLocalCustomOptionsToServer(userId, (category, name) =>
    apiRequest('/custom-options', {
      method: 'POST',
      body: { category, name },
    })
  );

  if (migrated > 0) {
    await loadOptions();
  }
}

function optionLabel(group, key) {
  return options.labels?.[group]?.[key]
    ?? localOptionLabels[group]?.[key]
    ?? (isLocalKey(key) ? 'Свой вариант' : key);
}

function allOptions(category) {
  return options[category] ?? [];
}

function registerCustomOption(category, key, label) {
  if (!options.labels) {
    options.labels = {};
  }
  if (!options[category]) {
    options[category] = [];
  }
  if (!options[category].includes(key)) {
    options[category].push(key);
  }
  if (!options.labels[category]) {
    options.labels[category] = {};
  }
  options.labels[category][key] = label;
  if (isLocalKey(key)) {
    localOptionLabels[category][key] = label;
  }
}

function selectOption(category, key) {
  if (!isSelected(category, key)) {
    toggleOption(category, key);
  }
}

function ensureLegacyLocalOptionsFromFormValues() {
  for (const category of LOCAL_OPTION_CATEGORIES) {
    for (const key of form[category]) {
      if (!isLocalKey(key) || options[category].includes(key)) {
        continue;
      }

      registerCustomOption(category, key, optionLabel(category, key));
    }
  }
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
    const created = await apiRequest('/custom-options', {
      method: 'POST',
      body: { category, name },
    });
    await loadOptions();
    const key = `custom:${created.id}`;
    selectOption(category, key);
    customOptionDraft[category] = '';
    customOptionHint[category] = 'Свой вариант сохранён в аккаунте и выбран.';
  } catch (error) {
    if (error?.status === 429) {
      customOptionError[category] = 'Слишком много новых значений. Лимит: 10 в день, 2 в минуту.';
    } else {
      customOptionError[category] = formatApiError(error, 'Не удалось добавить значение');
    }
  } finally {
    customOptionLoading[category] = false;
  }
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
  ensureFormArrays();
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
      pain_types: filterSelectableOptions('pain_types'),
      localizations: filterSelectableOptions('localizations'),
      triggers: filterSelectableOptions('triggers'),
      symptoms: filterSelectableOptions('symptoms'),
      auras: filterSelectableOptions('auras'),
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
      errorMessage.value = formatApiError(error, 'Ошибка сохранения');
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
