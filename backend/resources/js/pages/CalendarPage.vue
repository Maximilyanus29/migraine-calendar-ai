<template>
  <section class="card calendar-card">
    <div class="calendar-head">
      <button class="btn" @click="changeMonth(-1)">←</button>
      <h1>{{ monthLabel }}</h1>
      <button class="btn" @click="changeMonth(1)">→</button>
    </div>

    <div class="weekday-row">
      <div v-for="day in weekdayNames" :key="day" class="weekday">{{ day }}</div>
    </div>

    <div class="calendar-grid">
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
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../lib/api';

const router = useRouter();
const now = new Date();
const currentMonth = ref(new Date(now.getFullYear(), now.getMonth(), 1));
const attacks = ref([]);

const weekdayNames = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

const monthLabel = computed(() =>
  currentMonth.value.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' })
);

const gridDays = computed(() => buildMonthGrid(currentMonth.value));

const segmentsByDay = computed(() => {
  const map = {};

  for (const day of gridDays.value) {
    const dayStart = new Date(day.date.getFullYear(), day.date.getMonth(), day.date.getDate());
    const dayEnd = new Date(dayStart);
    dayEnd.setDate(dayEnd.getDate() + 1);

    for (const attack of attacks.value) {
      const attackStart = new Date(attack.start_at);
      const attackEnd = attack.end_at ? new Date(attack.end_at) : new Date();

      const segmentStart = new Date(Math.max(dayStart.getTime(), attackStart.getTime()));
      const segmentEnd = new Date(Math.min(dayEnd.getTime(), attackEnd.getTime()));

      if (segmentStart >= segmentEnd) {
        continue;
      }

      const dayMs = 24 * 60 * 60 * 1000;
      const offset = ((segmentStart.getTime() - dayStart.getTime()) / dayMs) * 100;
      const width = ((segmentEnd.getTime() - segmentStart.getTime()) / dayMs) * 100;

      if (!map[day.key]) {
        map[day.key] = [];
      }

      map[day.key].push({
        attack_id: attack.id,
        offset,
        width,
        intensity: attack.intensity,
        roundLeft: segmentStart.getTime() === attackStart.getTime(),
        roundRight: attack.end_at ? segmentEnd.getTime() === attackEnd.getTime() : false,
        title: `${formatDateTime(attackStart)} - ${attack.end_at ? formatDateTime(attackEnd) : 'сейчас'}; интенсивность ${attack.intensity}`,
      });
    }
  }

  return map;
});

function segmentStyle(segment) {
  return {
    left: `${segment.offset}%`,
    width: `${segment.width}%`,
    background: intensityColor(segment.intensity),
    borderTopLeftRadius: segment.roundLeft ? '15px' : '0',
    borderBottomLeftRadius: segment.roundLeft ? '15px' : '0',
    borderTopRightRadius: segment.roundRight ? '15px' : '0',
    borderBottomRightRadius: segment.roundRight ? '15px' : '0',
  };
}

function intensityColor(intensity) {
  if (intensity <= 3) return 'rgba(53, 161, 107, 0.45)';
  if (intensity <= 6) return 'rgba(213, 164, 25, 0.45)';
  if (intensity <= 8) return 'rgba(214, 106, 33, 0.45)';
  return 'rgba(212, 63, 63, 0.45)';
}

function changeMonth(delta) {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + delta, 1);
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
