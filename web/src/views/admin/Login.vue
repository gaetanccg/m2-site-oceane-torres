<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Administration</h1>
        <p class="mt-2 text-gray-600">Océane Torres Photographie</p>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="bg-white rounded-xl shadow-lg p-8 space-y-6">
        <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-lg">
          <p class="text-sm text-red-600">{{ error }}</p>
        </div>

        <FormField
          v-model="email"
          type="email"
          id="email"
          label="Mail"
          placeholder="admin@example.com"
          required
          :error="errors.email"
        />

        <FormField
          v-model="password"
          type="password"
          id="password"
          label="Mot de passe"
          placeholder="Votre mot de passe"
          required
          :error="errors.password"
        />

        <div class="flex items-center justify-between">
          <label class="flex items-center gap-2 text-sm text-gray-600">
            <input
              v-model="rememberMe"
              type="checkbox"
              class="w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
            />
            Se souvenir de moi
          </label>
        </div>

        <Button
          type="submit"
          :loading="isLoading"
          :disabled="isLoading"
          full-width
          size="lg"
        >
          Se connecter
        </Button>
      </form>

      <!-- Back to site -->
      <p class="mt-6 text-center text-sm text-gray-600">
        <router-link to="/" class="text-gold hover:text-gold/80">
          Retour au site
        </router-link>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import FormField from '@/components/admin/ui/FormField.vue'
import Button from '@/components/admin/ui/Button.vue'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const rememberMe = ref(false)
const isLoading = ref(false)
const error = ref('')
const errors = reactive({
  email: '',
  password: '',
})

async function handleSubmit() {
  // Reset errors
  error.value = ''
  errors.email = ''
  errors.password = ''

  // Validate
  if (!email.value) {
    errors.email = "Le mail est requis"
    return
  }
  if (!password.value) {
    errors.password = 'Le mot de passe est requis'
    return
  }

  isLoading.value = true

  try {
    const success = await authStore.login(email.value, password.value)

    if (success) {
      router.push('/admin')
    } else {
      error.value = authStore.error || 'Identifiants invalides'
    }
  } catch {
    error.value = 'Une erreur est survenue. Veuillez réessayer.'
  } finally {
    isLoading.value = false
  }
}
</script>
