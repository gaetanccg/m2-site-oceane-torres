<template>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <!-- Header -->
    <div v-if="title || $slots.header" class="px-6 py-4 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 v-if="title" class="text-lg font-semibold text-gray-900">{{ title }}</h3>
        <slot name="header" />
      </div>
    </div>

    <!-- Search & Filters -->
    <div v-if="searchable || $slots.filters" class="px-6 py-4 border-b border-gray-200 bg-gray-50">
      <div class="flex items-center gap-4">
        <div v-if="searchable" class="relative flex-1 max-w-md">
          <input
            type="text"
            :value="searchQuery"
            @input="$emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
            placeholder="Rechercher..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
          />
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
          </svg>
        </div>
        <slot name="filters" />
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th
              v-for="column in columns"
              :key="String(column.key)"
              :class="[
                'px-6 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wider',
                column.align === 'center' && 'text-center',
                column.align === 'right' && 'text-right',
                !column.align && 'text-left',
                column.sortable && 'cursor-pointer hover:bg-gray-100'
              ]"
              :style="column.width ? { width: column.width } : undefined"
              @click="column.sortable && handleSort(String(column.key))"
            >
              <div class="flex items-center gap-1" :class="{ 'justify-center': column.align === 'center', 'justify-end': column.align === 'right' }">
                {{ column.label }}
                <svg
                  v-if="column.sortable && sortKey === column.key"
                  :class="['w-4 h-4', sortOrder === 'desc' && 'rotate-180']"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
              </div>
            </th>
            <th v-if="$slots.actions" class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="(row, index) in data"
            :key="getRowKey(row, index)"
            class="hover:bg-gray-50 transition-colors"
          >
            <td
              v-for="column in columns"
              :key="`${getRowKey(row, index)}-${String(column.key)}`"
              :class="[
                'px-6 py-4 text-sm text-gray-900',
                column.align === 'center' && 'text-center',
                column.align === 'right' && 'text-right'
              ]"
            >
              <slot
                :name="`cell-${String(column.key)}`"
                :value="getNestedValue(row, String(column.key))"
                :row="row"
              >
                {{ column.render ? column.render(getNestedValue(row, String(column.key)), row) : getNestedValue(row, String(column.key)) }}
              </slot>
            </td>
            <td v-if="$slots.actions" class="px-6 py-4 text-right">
              <slot name="actions" :row="row" />
            </td>
          </tr>
          <tr v-if="data.length === 0">
            <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-6 py-12 text-center text-gray-500">
              <slot name="empty">
                Aucune donnée disponible
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="props.totalPages > 1" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
          Affichage {{ props.from }}-{{ props.to }} sur {{ props.total }} résultats
        </p>
        <div class="flex items-center gap-2">
          <button
            :disabled="props.currentPage <= 1"
            @click="$emit('update:currentPage', props.currentPage - 1)"
            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Précédent
          </button>
          <span class="px-3 py-1.5 text-sm">
            {{ props.currentPage }} / {{ props.totalPages }}
          </span>
          <button
            :disabled="props.currentPage >= props.totalPages"
            @click="$emit('update:currentPage', props.currentPage + 1)"
            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Suivant
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts" generic="T extends Record<string, unknown>">
import { ref } from 'vue'
import type { TableColumn } from '@/types/admin'

const props = withDefaults(
  defineProps<{
    columns: TableColumn<T>[]
    data: T[]
    title?: string
    searchable?: boolean
    searchQuery?: string
    currentPage?: number
    totalPages?: number
    total?: number
    from?: number
    to?: number
  }>(),
  {
    currentPage: 1,
    totalPages: 1,
    total: 0,
    from: 0,
    to: 0,
  }
)

defineEmits<{
  'update:searchQuery': [value: string]
  'update:currentPage': [page: number]
  sort: [key: string, order: 'asc' | 'desc']
}>()

const sortKey = ref<string | null>(null)
const sortOrder = ref<'asc' | 'desc'>('asc')

function handleSort(key: string) {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
}

function getRowKey(row: T, index: number): string {
  return (row.id as string) || String(index)
}

function getNestedValue(obj: T, path: string): unknown {
  return path.split('.').reduce((acc: unknown, part) => {
    if (acc && typeof acc === 'object') {
      return (acc as Record<string, unknown>)[part]
    }
    return undefined
  }, obj)
}
</script>
