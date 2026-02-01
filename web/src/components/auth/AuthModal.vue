<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="close"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" />

                <!-- Modal -->
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                    <!-- Close button -->
                    <button
                        @click="close"
                        class="absolute top-4 right-4 p-2 text-gray-400 hover:text-gray-600 transition-colors z-10"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Header -->
                    <div class="px-8 pt-8 pb-4 text-center">
                        <h2 class="text-2xl font-light text-gray-900">
                            {{ activeTab === 'login' ? 'Connexion' : 'Inscription' }}
                        </h2>
                        <p class="text-sm text-gray-500 mt-2">
                            {{ activeTab === 'login' ? 'Accedez a votre espace client' : 'Creez votre compte' }}
                        </p>
                    </div>

                    <!-- Tabs -->
                    <div class="flex border-b border-gray-200 mx-8">
                        <button
                            @click="activeTab = 'login'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                                activeTab === 'login'
                                    ? 'border-gold text-gold'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Connexion
                        </button>
                        <button
                            @click="activeTab = 'register'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                                activeTab === 'register'
                                    ? 'border-gold text-gold'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Inscription
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-8">
                        <!-- Error message -->
                        <div v-if="authStore.error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                            {{ authStore.error }}
                        </div>

                        <!-- Login Form -->
                        <form v-if="activeTab === 'login'" @submit.prevent="handleLogin" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input
                                    v-model="loginForm.email"
                                    type="email"
                                    required
                                    :class="[
                                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                        authStore.fieldErrors.email ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                    ]"
                                    placeholder="votre@email.com"
                                />
                                <p v-if="authStore.fieldErrors.email" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                                <input
                                    v-model="loginForm.password"
                                    type="password"
                                    required
                                    :class="[
                                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                        authStore.fieldErrors.password ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                    ]"
                                    placeholder="********"
                                />
                                <p v-if="authStore.fieldErrors.password" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.password }}</p>
                            </div>
                            <button
                                type="submit"
                                :disabled="authStore.isLoading"
                                class="w-full py-3 bg-gold text-white rounded-lg font-medium hover:bg-gold/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <svg v-if="authStore.isLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ authStore.isLoading ? 'Connexion...' : 'Se connecter' }}
                            </button>
                        </form>

                        <!-- Register Form -->
                        <form v-else @submit.prevent="handleRegister" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Prenom</label>
                                    <input
                                        v-model="registerForm.first_name"
                                        type="text"
                                        required
                                        :class="[
                                            'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                            authStore.fieldErrors.first_name ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                        ]"
                                        placeholder="Prenom"
                                    />
                                    <p v-if="authStore.fieldErrors.first_name" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.first_name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                                    <input
                                        v-model="registerForm.last_name"
                                        type="text"
                                        required
                                        :class="[
                                            'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                            authStore.fieldErrors.last_name ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                        ]"
                                        placeholder="Nom"
                                    />
                                    <p v-if="authStore.fieldErrors.last_name" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.last_name }}</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input
                                    v-model="registerForm.email"
                                    type="email"
                                    required
                                    :class="[
                                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                        authStore.fieldErrors.email ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                    ]"
                                    placeholder="votre@email.com"
                                />
                                <p v-if="authStore.fieldErrors.email" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telephone (optionnel)</label>
                                <input
                                    v-model="registerForm.phone"
                                    type="tel"
                                    :class="[
                                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                        authStore.fieldErrors.phone ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                    ]"
                                    placeholder="06 XX XX XX XX"
                                />
                                <p v-if="authStore.fieldErrors.phone" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.phone }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                                <input
                                    v-model="registerForm.password"
                                    type="password"
                                    required
                                    minlength="8"
                                    :class="[
                                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                        authStore.fieldErrors.password ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                    ]"
                                    placeholder="Minimum 8 caracteres"
                                />
                                <p v-if="authStore.fieldErrors.password" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                                <input
                                    v-model="registerForm.password_confirmation"
                                    type="password"
                                    required
                                    :class="[
                                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                        authStore.fieldErrors.password_confirmation ? 'border-red-300 bg-red-50' : 'border-gray-300'
                                    ]"
                                    placeholder="********"
                                />
                                <p v-if="authStore.fieldErrors.password_confirmation" class="mt-1 text-sm text-red-600">{{ authStore.fieldErrors.password_confirmation }}</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <input
                                    v-model="registerForm.gdpr_consent"
                                    type="checkbox"
                                    required
                                    class="mt-1 w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
                                />
                                <label class="text-sm text-gray-600">
                                    J'accepte que mes données soient traitées conformement a la
                                    <router-link to="/mentions-legales" class="text-gold hover:underline" @click="close">
                                        politique de confidentialite
                                    </router-link>
                                </label>
                            </div>
                            <button
                                type="submit"
                                :disabled="authStore.isLoading || !registerForm.gdpr_consent"
                                class="w-full py-3 bg-gold text-white rounded-lg font-medium hover:bg-gold/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <svg v-if="authStore.isLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ authStore.isLoading ? 'Inscription...' : 'S\'inscrire' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { ERROR_MESSAGES } from '@/utils/errorHandler'

const props = defineProps<{
    modelValue: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
}>()

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

const activeTab = ref<'login' | 'register'>('login')

const loginForm = reactive({
    email: '',
    password: '',
})

const registerForm = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    gdpr_consent: false,
})

// Clear error when switching tabs
watch(activeTab, () => {
    authStore.clearError()
})

// Clear forms when modal closes
watch(() => props.modelValue, (isOpen) => {
    if (!isOpen) {
        loginForm.email = ''
        loginForm.password = ''
        registerForm.first_name = ''
        registerForm.last_name = ''
        registerForm.email = ''
        registerForm.phone = ''
        registerForm.password = ''
        registerForm.password_confirmation = ''
        registerForm.gdpr_consent = false
        authStore.clearError()
    }
})

function close() {
    emit('update:modelValue', false)
}

async function handleLogin() {
    const success = await authStore.loginClient(loginForm.email, loginForm.password)
    if (success) {
        close()
        toast.success('Connexion reussie', `Bienvenue ${authStore.user?.first_name} !`)
        const redirect = route.query.redirect as string
        if (redirect) {
            router.push(redirect)
        } else {
            // Redirect based on role
            router.push(authStore.isAdmin ? '/admin' : '/mon-compte')
        }
    }
}

async function handleRegister() {
    if (registerForm.password !== registerForm.password_confirmation) {
        authStore.error = ERROR_MESSAGES.form.passwordMismatch
        return
    }

    const success = await authStore.register({
        first_name: registerForm.first_name,
        last_name: registerForm.last_name,
        email: registerForm.email,
        phone: registerForm.phone || undefined,
        password: registerForm.password,
        password_confirmation: registerForm.password_confirmation,
    })

    if (success) {
        close()
        toast.success('Inscription reussie', 'Bienvenue ! Votre compte a ete cree.')
        router.push('/mon-compte')
    }
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95) translateY(10px);
}
</style>
