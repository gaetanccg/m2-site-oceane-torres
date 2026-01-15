<template>
    <footer class="bg-black text-white py-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
                <!-- Brand -->
                <div>
                    <div class="flex items-center space-x-4">
                        <img src="/logo-oceane-white.png" class="h-20 w-auto object-contain" alt="Logo Océane" />
                        <div>
                            <h3 class="text-2xl font-light tracking-wider mb-1">OCÉANE TORRES</h3>
                            <p class="text-gray-400 font-light text-sm">
                                Photographe professionnelle
                            </p>
                            <p class="text-gray-400 font-light text-sm">
                                {{ CONTACT_INFO.email }}
                            </p>
                            <p class="text-gray-400 font-light text-sm">
                                {{ CONTACT_INFO.phone }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div>
                    <h4 class="text-sm uppercase tracking-widest mb-4 text-gold">Navigation</h4>
                    <ul class="grid grid-cols-2 gap-y-2">
                        <li v-for="link in NAV_LINKS" :key="link.path">
                            <router-link
                                :to="link.path"
                                class="text-gray-400 hover:text-gold transition-colors text-sm font-light"
                            >
                                {{ link.name }}
                            </router-link>
                        </li>
                    </ul>
                </div>

                <!-- Social -->
                <div>
                    <h4 class="text-sm uppercase tracking-widest mb-4 text-gold">Suivez-moi</h4>
                    <SocialLinks variant="dark" size="md" />
                </div>
            </div>

            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-400 font-light">
                <p>&copy; {{ currentYear }} Océane Torres Photographie. Tous droits réservés.</p>
                <router-link to="/mentions-legales" class="hover:text-gold transition-colors mt-4 md:mt-0">
                    Mentions légales
                </router-link>
            </div>
        </div>
    </footer>
</template>

<script setup lang="ts">
import {NAV_LINKS, CONTACT_INFO} from '@/config/constants'
import SocialLinks from './SocialLinks.vue'

const currentYear = new Date().getFullYear()
</script>
