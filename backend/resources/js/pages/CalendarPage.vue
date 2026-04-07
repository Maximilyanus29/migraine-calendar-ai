<template>
  <section
    class="card calendar-card"
    @touchstart="onTouchStart"
    @touchend="onTouchEnd"
    @wheel.prevent="onWheel"
  >
    <div class="calendar-head" data-testid="calendar-head">
      <button type="button" class="btn" aria-label="Предыдущий месяц" @click="changeMonth(-1)">←</button>
      <h1>{{ monthLabel }}</h1>
      <button type="button" class="btn" :disabled="!canGoNextMonth" aria-label="Следующий месяц" @click="changeMonth(1)">→</button>
    </div>

    <Transition :name="transitionName" mode="out-in">
      <div :key="monthKey" class="calendar-body">
        <div class="weekday-row">
          <div v-for="day in weekdayNames" :key="day" class="weekday">{{ day }}</div>
        </div>

        <div class="calendar-grid" data-testid="calendar-grid">
          <div
            v-for="day in gridDays"
            :key="day.key"
            class="day-cell"
            :class="{ 'outside': !day.inMonth, 'today': day.isToday }"
            @click="onDayClick(day, $event)"
          >
            <div class="segments">
              <RouterLink
                v-for="segment in segmentsByDay[day.key] || []"
                :key="segment.attack_id"
                class="segment"
                :to="`/attacks/${segment.attack_id}/edit`"
                :style="segmentStyle(segment)"
                :title="segment.title"
                @click.stop
              />
            </div>
            <div class="day-number">{{ day.date.getDate() }}</div>
          </div>
        </div>
      </div>
    </Transition>
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../lib/api';
import { attacksToSegmentsForDay, intensityColorRgba } from '../lib/attackSegments';

const router = useRouter();
const now = new Date();
const currentMonth = ref(new Date(now.getFullYear(), now.getMonth(), 1));
const attacks = ref([]);
const touchStartX = ref(null);
const touchStartY = ref(null);
const transitionName = ref('month-next');
const wheelDeltaSum = ref(0);
const wheelLockedUntil = ref(0);

const weekdayNames = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

const monthLabel = computed(() =>
  currentMonth.value.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' })
);
const canGoNextMonth = computed(() => {
  const todayMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  return currentMonth.value.getTime() < todayMonth.getTime();
});
const monthKey = computed(
  () => `${currentMonth.value.getFullYear()}-${String(currentMonth.value.getMonth() + 1).padStart(2, '0')}`
);

const gridDays = computed(() => buildMonthGrid(currentMonth.value));

const segmentsByDay = computed(() => {
  const map = {};
  const now = new Date();

  for (const day of gridDays.value) {
    for (const segment of attacksToSegmentsForDay(day.date, attacks.value, now)) {
      if (!map[day.key]) {
        map[day.key] = [];
      }

      const attack = attacks.value.find((a) => a.id === segment.attack_id);
      const attackStart = attack ? new Date(attack.start_at) : null;
      const attackEnd = attack?.end_at ? new Date(attack.end_at) : null;

      map[day.key].push({
        ...segment,
        roundRight: attack?.end_at ? segment.roundRight : false,
        title:
          attackStart
            ? `${formatDateTime(attackStart)} - ${attackEnd ? formatDateTime(attackEnd) : 'сейчас'}; интенсивность ${segment.intensity}`
            : `Приступ #${segment.attack_id}`,
      });
    }
  }

  return map;
});

function segmentStyle(segment) {
  return {
    left: `${segment.offset}%`,
    width: `${segment.width}%`,
    background: intensityColorRgba(segment.intensity),
    borderTopLeftRadius: segment.roundLeft ? '15px' : '0',
    borderBottomLeftRadius: segment.roundLeft ? '15px' : '0',
    borderTopRightRadius: segment.roundRight ? '15px' : '0',
    borderBottomRightRadius: segment.roundRight ? '15px' : '0',
  };
}

function changeMonth(delta) {
  if (delta > 0 && !canGoNextMonth.value) {
    return;
  }
  transitionName.value = delta >= 0 ? 'month-next' : 'month-prev';
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + delta, 1);
}

function onTouchStart(event) {
  if (isLandscapeTouchDevice()) {
    touchStartX.value = null;
    touchStartY.value = null;
    return;
  }

  const touch = event.changedTouches?.[0];
  if (!touch) return;
  touchStartX.value = touch.clientX;
  touchStartY.value = touch.clientY;
}

function onTouchEnd(event) {
  if (isLandscapeTouchDevice()) {
    return;
  }

  const touch = event.changedTouches?.[0];
  if (!touch || touchStartX.value === null || touchStartY.value === null) {
    return;
  }

  const deltaX = touch.clientX - touchStartX.value;
  const deltaY = touch.clientY - touchStartY.value;
  touchStartX.value = null;
  touchStartY.value = null;

  if (Math.abs(deltaY) < 40 || Math.abs(deltaY) < Math.abs(deltaX)) {
    return;
  }

  if (deltaY < 0) {
    changeMonth(1);
  } else {
    changeMonth(-1);
  }
}

function onWheel(event) {
  if (!window.matchMedia('(pointer: fine)').matches) {
    return;
  }

  if (Math.abs(event.deltaY) < Math.abs(event.deltaX)) {
    return;
  }

  const nowMs = Date.now();
  if (nowMs < wheelLockedUntil.value) {
    return;
  }

  wheelDeltaSum.value += event.deltaY;
  if (Math.abs(wheelDeltaSum.value) < 42) {
    return;
  }

  const direction = wheelDeltaSum.value > 0 ? 1 : -1;
  wheelDeltaSum.value = 0;

  changeMonth(direction);
  wheelLockedUntil.value = nowMs + 260;
}

function isLandscapeTouchDevice() {
  return window.matchMedia('(pointer: coarse) and (orientation: landscape)').matches;
}

async function loadAttacks() {
  const days = gridDays.value;
  const from = toDateKey(days[0].date);
  const to = toDateKey(days[days.length - 1].date);
  attacks.value = await apiRequest(`/attacks?from=${from}&to=${to}`);
}

function onDayClick(day, event) {
  const nowDate = new Date();
  const clickedDate = new Date(day.date.getFullYear(), day.date.getMonth(), day.date.getDate());
  const today = new Date(nowDate.getFullYear(), nowDate.getMonth(), nowDate.getDate());
  if (clickedDate > today) {
    alert('Нельзя создавать приступ в будущем');
    return;
  }

  const rect = event.currentTarget.getBoundingClientRect();
  const x = Math.max(0, Math.min(rect.width, event.clientX - rect.left));
  const ratio = rect.width > 0 ? x / rect.width : 0;

  const dateKey = toDateKey(day.date);
  router.push(`/attacks/new?date=${dateKey}&ratio=${ratio.toFixed(4)}`);
}

watch(currentMonth, () => {
  loadAttacks();
});

onMounted(() => {
  loadAttacks();
});

function toDateKey(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function formatDateTime(date) {
  return date.toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' });
}

function buildMonthGrid(monthDate) {
  const first = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1);
  const firstWeekDay = (first.getDay() + 6) % 7;
  const start = new Date(first);
  start.setDate(first.getDate() - firstWeekDay);

  const todayKey = toDateKey(new Date());
  const result = [];

  for (let i = 0; i < 42; i += 1) {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    const key = toDateKey(d);

    result.push({
      date: d,
      key,
      inMonth: d.getMonth() === monthDate.getMonth(),
      isToday: key === todayKey,
    });
  }

  return result;
}
</script>
