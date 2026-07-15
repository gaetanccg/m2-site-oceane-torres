<template>
    <div>
        <AdminHeader title="Logs applicatifs" subtitle="Consulter et exporter laravel.log" />

        <div class="p-6 space-y-4">
            <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Niveau</label>
                    <select v-model="level" class="rounded-lg border-gray-300 text-sm focus:border-gold focus:ring-gold" @change="fetchLogs">
                        <option value="">Tous</option>
                        <option v-for="lvl in levels" :key="lvl" :value="lvl">{{ lvl }}</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Filtrer…"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-gold focus:ring-gold"
                        @keyup.enter="fetchLogs"
                    />
                </div>
                <Button :disabled="isLoading" @click="fetchLogs">
                    {{ isLoading ? 'Chargement…' : 'Rafraîchir' }}
                </Button>
                <a :href="downloadUrl" class="text-sm text-gold hover:underline self-center whitespace-nowrap">
                    Télécharger le fichier
                </a>
            </div>

            <p v-if="truncated" class="text-xs text-amber-600">
                Affichage des dernières lignes uniquement (fichier volumineux tronqué).
            </p>

            <div class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto max-h-[70vh] overflow-y-auto">
                <p v-if="!lines.length" class="text-gray-400 text-sm">Aucune ligne.</p>
                <pre v-else class="text-xs leading-relaxed whitespace-pre-wrap break-words"><span
                    v-for="(line, i) in lines"
                    :key="i"
                    :class="lineClass(line)"
                >{{ line }}
</span></pre>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Button from '@/components/admin/ui/Button.vue'
import { adminApi } from '@/services/adminApi'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']
const level = ref('')
const search = ref('')
const isLoading = ref(false)
const lines = ref<string[]>([])
const truncated = ref(false)

const downloadUrl = computed(() => adminApi.getLogsDownloadUrl())

function lineClass(line: string): string {
    if (line.includes('.ERROR:') || line.includes('.CRITICAL:') || line.includes('.EMERGENCY:')) return 'text-red-400'
    if (line.includes('.WARNING:')) return 'text-amber-300'
    if (line.includes('.INFO:') || line.includes('.NOTICE:')) return 'text-sky-300'
    return 'text-gray-300'
}

async function fetchLogs() {
    isLoading.value = true
    try {
        const response = await adminApi.getLogs(level.value, search.value, 500)
        lines.value = response.lines
        truncated.value = response.truncated
    } catch {
        toast.error('Erreur', 'Impossible de charger les logs.')
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchLogs)
</script>
