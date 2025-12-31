<template>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <div class="flex items-center gap-4">
        <button
          @click="previousMonth"
          class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h2 class="text-lg font-semibold text-gray-900 min-w-[180px] text-center">
          {{ monthYear }}
        </h2>
        <button
          @click="nextMonth"
          class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
      <button
        @click="goToToday"
        class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
      >
        Aujourd'hui
      </button>
    </div>

    <!-- Days header -->
    <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
      <div
        v-for="day in weekDays"
        :key="day"
        class="px-2 py-3 text-center text-xs font-semibold text-gray-600 uppercase"
      >
        {{ day }}
      </div>
    </div>

    <!-- Calendar grid -->
    <div class="grid grid-cols-7">
      <div
        v-for="(day, index) in calendarDays"
        :key="index"
        :class="[
          'min-h-[100px] p-2 border-b border-r border-gray-100',
          day.isCurrentMonth ? 'bg-white' : 'bg-gray-50',
          day.isToday && 'bg-gold/5'
        ]"
      >
        <div class="flex items-start justify-between mb-1">
          <span
            :class="[
              'text-sm font-medium',
              day.isToday && 'bg-gold text-white w-7 h-7 rounded-full flex items-center justify-center',
              !day.isToday && (day.isCurrentMonth ? 'text-gray-900' : 'text-gray-400')
            ]"
          >
            {{ day.date }}
          </span>
        </div>

        <!-- Events -->
        <div class="space-y-1">
          <div
            v-for="event in day.events.slice(0, 3)"
            :key="event.id"
            @click="$emit('eventClick', event)"
            :class="[
              'px-2 py-1 text-xs rounded truncate cursor-pointer transition-colors',
              eventClasses[event.status] || 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            ]"
            :title="`${event.title} - ${event.client}`"
          >
            {{ event.title }}
          </div>
          <div
            v-if="day.events.length > 3"
            class="px-2 py-1 text-xs text-gray-500 hover:text-gray-700 cursor-pointer"
            @click="$emit('dayClick', day.fullDate)"
          >
            +{{ day.events.length - 3 }} autres
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { CalendarEvent, ReservationStatus } from '@/types/admin'

const props = defineProps<{
  events: CalendarEvent[]
}>()

defineEmits<{
  eventClick: [event: CalendarEvent]
  dayClick: [date: string]
}>()

const currentDate = ref(new Date())

const weekDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

const monthYear = computed(() => {
  return currentDate.value.toLocaleDateString('fr-FR', {
    month: 'long',
    year: 'numeric',
  })
})

const eventClasses: Record<ReservationStatus, string> = {
  pending: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
  confirmed: 'bg-green-100 text-green-800 hover:bg-green-200',
  cancelled: 'bg-red-100 text-red-800 hover:bg-red-200',
  completed: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
}

interface CalendarDay {
  date: number
  fullDate: string
  isCurrentMonth: boolean
  isToday: boolean
  events: CalendarEvent[]
}

const calendarDays = computed((): CalendarDay[] => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()

  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  // Adjust for Monday start (0 = Monday, 6 = Sunday)
  let startDay = firstDay.getDay() - 1
  if (startDay === -1) startDay = 6

  const days: CalendarDay[] = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  // Previous month days
  const prevMonth = new Date(year, month, 0)
  for (let i = startDay - 1; i >= 0; i--) {
    const date = prevMonth.getDate() - i
    const fullDate = formatDate(new Date(year, month - 1, date))
    days.push({
      date,
      fullDate,
      isCurrentMonth: false,
      isToday: false,
      events: getEventsForDate(fullDate),
    })
  }

  // Current month days
  for (let date = 1; date <= lastDay.getDate(); date++) {
    const currentDay = new Date(year, month, date)
    currentDay.setHours(0, 0, 0, 0)
    const fullDate = formatDate(currentDay)
    days.push({
      date,
      fullDate,
      isCurrentMonth: true,
      isToday: currentDay.getTime() === today.getTime(),
      events: getEventsForDate(fullDate),
    })
  }

  // Next month days to complete the grid
  const remaining = 42 - days.length // 6 weeks * 7 days
  for (let date = 1; date <= remaining; date++) {
    const fullDate = formatDate(new Date(year, month + 1, date))
    days.push({
      date,
      fullDate,
      isCurrentMonth: false,
      isToday: false,
      events: getEventsForDate(fullDate),
    })
  }

  return days
})

function formatDate(date: Date): string {
  return date.toISOString().split('T')[0]
}

function getEventsForDate(dateStr: string): CalendarEvent[] {
  return props.events.filter((event) => event.start.startsWith(dateStr))
}

function previousMonth() {
  currentDate.value = new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() - 1,
    1
  )
}

function nextMonth() {
  currentDate.value = new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() + 1,
    1
  )
}

function goToToday() {
  currentDate.value = new Date()
}
</script>
