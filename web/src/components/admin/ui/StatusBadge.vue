<template>
  <span
    :class="[
      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
      statusClasses[status] || statusClasses.default
    ]"
  >
    {{ label || statusLabels[status] || status }}
  </span>
</template>

<script setup lang="ts">
defineProps<{
  status: string
  label?: string
}>()

const statusClasses: Record<string, string> = {
  // Reservation statuses
  pending: 'bg-yellow-100 text-yellow-800',
  confirmed: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
  completed: 'bg-blue-100 text-blue-800',

  // Payment statuses
  paid: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-800',
  refunded: 'bg-gray-100 text-gray-800',

  // Gallery types
  public: 'bg-blue-100 text-blue-800',
  private: 'bg-purple-100 text-purple-800',

  // Gift card statuses
  active: 'bg-green-100 text-green-800',
  used: 'bg-gray-100 text-gray-800',
  expired: 'bg-red-100 text-red-800',

  // Facture statuses
  draft: 'bg-gray-100 text-gray-800',
  sent: 'bg-blue-100 text-blue-800',

  // Default
  default: 'bg-gray-100 text-gray-800',
}

const statusLabels: Record<string, string> = {
  pending: 'En attente',
  confirmed: 'Confirmé',
  cancelled: 'Annulé',
  completed: 'Terminé',
  paid: 'Payé',
  failed: 'Échoué',
  refunded: 'Remboursé',
  public: 'Publique',
  private: 'Privée',
  active: 'Actif',
  used: 'Utilisé',
  expired: 'Expiré',
  draft: 'Brouillon',
  sent: 'Envoyé',
}
</script>
