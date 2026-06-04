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

  // Detailed order statuses
  checkout_initiated: 'bg-gray-100 text-gray-800',
  payment_in_progress: 'bg-blue-100 text-blue-800',
  payment_abandoned: 'bg-orange-100 text-orange-800',
  payment_failed: 'bg-red-100 text-red-800',
  to_ship: 'bg-amber-100 text-amber-800',
  shipped: 'bg-green-100 text-green-800',

  // Gallery types
  public: 'bg-blue-100 text-blue-800',
  private: 'bg-purple-100 text-purple-800',

  // Gift card statuses
  active: 'bg-green-100 text-green-800',
  used: 'bg-gray-100 text-gray-800',
  expired: 'bg-red-100 text-red-800',

  // Gift code (promo) statuses
  inactive: 'bg-gray-100 text-gray-800',
  scheduled: 'bg-blue-100 text-blue-800',
  used_up: 'bg-orange-100 text-orange-800',

  // Facture statuses
  draft: 'bg-gray-100 text-gray-800',
  sent: 'bg-blue-100 text-blue-800',

  // School session statuses
  uploading: 'bg-blue-100 text-blue-800',
  extracting: 'bg-blue-100 text-blue-800',
  creating_galleries: 'bg-blue-100 text-blue-800',
  processing_photos: 'bg-indigo-100 text-indigo-800',

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
  // Detailed order statuses
  checkout_initiated: 'Panier validé',
  payment_in_progress: 'Paiement en cours',
  payment_abandoned: 'Paiement abandonné',
  payment_failed: 'Paiement échoué',
  to_ship: 'À expédier',
  shipped: 'Expédié',
  // Gallery types
  public: 'Publique',
  private: 'Privée',
  active: 'Actif',
  used: 'Utilisé',
  expired: 'Expiré',
  inactive: 'Désactivé',
  scheduled: 'Programmé',
  used_up: 'Épuisé',
  draft: 'Brouillon',
  sent: 'Envoyé',
  // School session statuses
  uploading: 'Upload',
  extracting: 'Extraction',
  creating_galleries: 'Création',
  processing_photos: 'Traitement',
}
</script>
