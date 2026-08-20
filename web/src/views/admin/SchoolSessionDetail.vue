<template>
    <div>
        <AdminHeader :title="session?.title ?? 'Chargement...'" :subtitle="subtitleText">
            <template #actions>
                <Button variant="secondary" @click="goBack">Retour</Button>
            </template>
        </AdminHeader>

        <div v-if="isLoading" class="p-6 flex items-center justify-center min-h-[400px]">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-8 w-8 text-gold" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-500">Chargement...</span>
            </div>
        </div>

        <div v-else-if="!session" class="p-6 text-center">
            <p class="text-gray-500">Session introuvable</p>
            <Button class="mt-4" @click="goBack">Retour</Button>
        </div>

        <div v-else class="p-6 space-y-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Statut</p>
                    <StatusBadge :status="session.status" />
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Galeries</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ session.total_galleries }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Photos</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ session.total_photos }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Date shooting</p>
                    <p class="text-sm font-medium text-gray-900">{{ session.event_date ? formatDate(session.event_date) : '-' }}</p>
                </div>
            </div>

            <!-- Processing Progress -->
            <div v-if="isProcessing" class="bg-blue-50 border border-blue-200 rounded-xl p-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">{{ processingPhaseLabel }}</span>
                    <span v-if="session.batch_progress" class="text-sm text-blue-600 font-medium">
                        {{ session.batch_progress.completed + session.batch_progress.failed }} / {{ session.batch_progress.total }}
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-500 rounded-full h-2 transition-all duration-500" :style="{ width: processingProgress + '%' }"></div>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <p v-if="processingEta.eta.value" class="text-xs text-gray-600">
                        Temps restant : {{ processingEta.eta.value }}
                    </p>
                    <p v-if="session.batch_progress?.failed" class="text-xs text-red-500">
                        {{ session.batch_progress.failed }} erreur(s)
                    </p>
                </div>
            </div>

            <!-- Error -->
            <div v-if="session.status === 'failed'" class="bg-red-50 border border-red-200 rounded-xl p-5">
                <p class="text-sm font-medium text-red-800">Le traitement a echoue</p>
                <p v-if="session.error_message" class="text-sm text-red-600 mt-1">{{ session.error_message }}</p>
            </div>

            <!-- Failed photos retry -->
            <div v-if="failedPhotosCount > 0 && !isProcessing" class="bg-orange-50 border border-orange-200 rounded-xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-orange-800">
                            {{ failedPhotosCount }} photo(s) en echec
                        </p>
                        <p class="text-xs text-orange-600 mt-1">
                            Certaines photos n'ont pas pu être traitées. Relancez le traitement pour réessayer.
                        </p>
                    </div>
                    <Button :loading="isRetrying" variant="secondary" @click="retryFailedPhotos">
                        Relancer les photos
                    </Button>
                </div>
            </div>

            <!-- Galleries -->
            <div v-if="galleries.length > 0" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-sm font-semibold text-gray-700">Galeries ({{ galleries.length }})</h3>
                    <button
                        @click="exportGalleriesCsv"
                        class="text-sm text-gold hover:text-gold/80 font-medium flex items-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exporter CSV
                    </button>
                </div>

                <div class="px-5 py-3 border-b border-gray-200">
                    <input
                        v-model="gallerySearch"
                        type="text"
                        placeholder="Rechercher un enfant ou une classe..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold"
                    />
                </div>

                <div>
                    <div v-for="group in groupedGalleries" :key="group.className" class="border-b border-gray-200 last:border-b-0">
                        <button
                            v-if="group.className !== '__all__'"
                            @click="toggleClass(group.className)"
                            class="w-full flex items-center justify-between px-5 py-3 bg-gray-50 hover:bg-gray-100 text-left"
                        >
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-500 transition-transform" :class="{ 'rotate-90': !collapsedClasses[group.className] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <span class="font-semibold text-sm text-gray-700">{{ group.className }}</span>
                            </div>
                            <span class="text-xs text-gray-500">{{ group.galleries.length }} eleve(s)</span>
                        </button>

                        <div v-show="group.className === '__all__' || !collapsedClasses[group.className]" class="divide-y divide-gray-100">
                            <div
                                v-for="gallery in group.galleries"
                                :key="gallery.id"
                                class="flex items-center justify-between px-5 py-3 hover:bg-gray-50"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 text-sm truncate">{{ gallery.title }}</p>
                                    <p class="text-xs text-gray-500">{{ gallery.photos_count }} photos</p>
                                </div>
                                <div class="flex items-center gap-2 ml-3">
                                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-600">
                                        {{ gallery.share_code }}
                                    </span>
                                    <button
                                        @click="openPhotosManager(gallery)"
                                        class="p-1.5 text-gray-400 hover:text-gold rounded"
                                        title="Modifier les photos"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="copyGalleryLink(gallery)"
                                        class="p-1.5 text-gray-400 hover:text-gold rounded"
                                        title="Copier le lien"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders -->
            <div v-if="session.status === 'completed'" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <button
                    @click="ordersCollapsed = !ordersCollapsed"
                    class="w-full flex items-center justify-between px-5 py-3 border-b border-gray-200 bg-gray-50 hover:bg-gray-100 text-left"
                    :class="{ 'border-b-0': ordersCollapsed }"
                >
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 transition-transform" :class="{ 'rotate-90': !ordersCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <h3 class="text-sm font-semibold text-gray-700">
                            Commandes
                            <span v-if="orders.length > 0" class="text-gray-500 font-normal">({{ orders.length }})</span>
                        </h3>
                    </div>
                    <span v-if="ordersLoading" class="text-xs text-gray-400">Chargement...</span>
                    <span v-else-if="orders.length > 0" class="text-xs text-gray-500">
                        {{ formatPrice(ordersTotal) }} au total
                    </span>
                </button>

                <div v-show="!ordersCollapsed">
                    <div v-if="orders.length > 0" class="px-5 py-3 border-b border-gray-200">
                        <input
                            v-model="orderSearch"
                            type="text"
                            placeholder="Rechercher par client (nom ou email)..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold"
                        />
                    </div>

                    <div v-if="!ordersLoading && orders.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        Aucune commande pour cette session.
                    </div>
                    <div v-else-if="filteredOrders.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        Aucune commande ne correspond a la recherche.
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-2 text-left font-medium">Commande</th>
                                <th class="px-5 py-2 text-left font-medium">Client</th>
                                <th class="px-5 py-2 text-left font-medium">Photos</th>
                                <th class="px-5 py-2 text-right font-medium">Total</th>
                                <th class="px-5 py-2 text-left font-medium">Date</th>
                                <th class="px-5 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="order in filteredOrders" :key="order.id" class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs text-gray-700">{{ order.order_number }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-gray-900">{{ order.customer_name || '—' }}</p>
                                    <p class="text-xs text-gray-500 truncate max-w-[180px]">{{ order.customer_email }}</p>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ totalPhotos(order) }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ formatPrice(order.total) }}</td>
                                <td class="px-5 py-3 text-gray-500 text-xs">
                                    {{ order.paid_at ? formatDate(order.paid_at) : formatDate(order.created_at) }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button
                                        @click="openOrderDetail(order)"
                                        class="text-gold hover:text-gold/80 text-xs font-medium"
                                    >
                                        Voir
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SMS Template -->
            <div v-if="session.status === 'completed'" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-medium text-gray-900">Modèle de SMS d'accès</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Utilisé pour tous les envois SMS de cette session. Laissez vide pour le message par défaut.
                        </p>
                    </div>
                    <Button
                        v-if="smsTemplateDirty"
                        :loading="isSavingSmsTemplate"
                        @click="saveSmsTemplate"
                    >
                        Enregistrer
                    </Button>
                </div>
                <SmsTemplateField v-model="smsTemplateInput" />
            </div>

            <!-- Closure banner -->
            <div v-if="session.closed_at" class="bg-orange-50 border border-orange-200 rounded-xl p-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div class="flex-1">
                    <p class="font-medium text-orange-900">Session cloturée</p>
                    <p class="text-sm text-orange-800 mt-0.5">Les parents ne peuvent plus commander de photos. Cloturée le {{ formatDate(session.closed_at) }}.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-4">
                <p class="text-xs text-gray-400">Crée le {{ formatDate(session.created_at) }}</p>
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <Button
                        v-if="session.status === 'completed' && !session.closed_at"
                        variant="secondary"
                        @click="confirmClose"
                    >
                        Cloturer
                    </Button>
                    <Button
                        v-if="session.closed_at"
                        variant="secondary"
                        @click="reopen"
                    >
                        Rouvrir
                    </Button>
                    <Button v-if="session.status === 'completed'" variant="secondary" @click="openExportModal">
                        Exporter les commandes
                    </Button>
                    <Button v-if="session.status === 'completed'" @click="openMessagesModal">
                        Envoyer les liens
                    </Button>
                    <Button
                        v-if="session.status === 'completed' || session.status === 'failed'"
                        variant="danger"
                        @click="confirmDelete"
                    >
                        Supprimer
                    </Button>
                </div>
            </div>
        </div>

        <!-- Send Messages Modal -->
        <Modal v-model="showMessagesModal" title="Envoyer les liens d'acces" size="xl">
            <div class="space-y-4">
                <!-- Channel toggle -->
                <div class="flex gap-2 p-1 bg-gray-100 rounded-lg">
                    <button
                        type="button"
                        @click="setChannel('email')"
                        :class="[
                            'flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors',
                            channel === 'email' ? 'bg-white text-gold shadow-sm' : 'text-gray-600 hover:text-gray-900'
                        ]"
                    >
                        Email
                    </button>
                    <button
                        type="button"
                        @click="setChannel('sms')"
                        :class="[
                            'flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors',
                            channel === 'sms' ? 'bg-white text-gold shadow-sm' : 'text-gray-600 hover:text-gray-900'
                        ]"
                    >
                        SMS
                    </button>
                </div>

                <!-- CSV Import -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700">Importer un CSV</label>
                        <span class="text-xs text-gray-400">Format : nom;email;telephone</span>
                    </div>
                    <label class="cursor-pointer block">
                        <div class="border border-dashed border-gray-300 rounded-lg px-4 py-2 text-center text-sm text-gray-500 hover:border-gold hover:text-gold transition-colors">
                            {{ csvFileName || 'Choisir un fichier CSV' }}
                        </div>
                        <input type="file" accept=".csv,.txt" class="hidden" @change="onImportCsv" />
                    </label>
                    <p class="text-xs text-gray-400 mt-2">
                        Une seule colonne suffit pour le canal choisi (email pour Email, telephone pour SMS).
                    </p>
                </div>

                <!-- Manual entry -->
                <div class="flex gap-2">
                    <select
                        v-model="manualForm.gallery_id"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold"
                    >
                        <option value="">Choisir une galerie...</option>
                        <option v-for="g in unmatchedGalleries" :key="g.id" :value="g.id">
                            {{ g.class_name ? `[${g.class_name}] ` : '' }}{{ g.title }}
                        </option>
                    </select>
                    <input
                        v-if="channel === 'email'"
                        v-model="manualForm.email"
                        type="email"
                        placeholder="Email parent"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold"
                    />
                    <input
                        v-else
                        v-model="manualForm.phone"
                        type="tel"
                        placeholder="06 12 34 56 78"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gold focus:border-gold"
                    />
                    <Button size="sm" :disabled="!canAddManual" @click="addManualContact">
                        Ajouter
                    </Button>
                </div>

                <!-- Contact list -->
                <div v-if="contacts.length > 0">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">{{ contacts.length }} contact(s)</span>
                        <button @click="contacts = []" class="text-xs text-red-500 hover:text-red-700">Tout vider</button>
                    </div>
                    <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                        <div
                            v-for="(contact, idx) in contacts"
                            :key="idx"
                            class="flex items-center justify-between px-3 py-2 hover:bg-gray-50"
                        >
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ contact.recipient_name }}</p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ channel === 'email' ? (contact.email || '—') : (contact.phone || '—') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 ml-2">
                                <span v-if="isContactValid(contact)" class="text-xs text-green-600">Prêt</span>
                                <span v-else class="text-xs text-red-500">{{ contactInvalidReason(contact) }}</span>
                                <button @click="contacts.splice(idx, 1)" class="text-gray-400 hover:text-red-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="contacts.length > 0 && invalidContactsCount > 0" class="text-sm text-orange-600">
                    {{ invalidContactsCount }} contact(s) invalide(s) — ils ne recevront rien.
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showMessagesModal = false">Annuler</Button>
                <Button :loading="isSending" :disabled="validContacts.length === 0" @click="sendMessages">
                    Envoyer {{ validContacts.length }} {{ channel === 'sms' ? 'SMS' : 'email(s)' }}
                </Button>
            </template>
        </Modal>

        <!-- Export ZIP Modal -->
        <Modal v-model="showExportModal" title="Exporter les commandes" size="lg">
            <div class="space-y-4">
                <p class="text-sm text-gray-600">
                    Télécharge un ZIP contenant les photos achetées, organisées par classe puis par enfant, avec un fichier <code class="bg-gray-100 px-1 rounded text-xs">_index.csv</code> récapitulatif.
                </p>

                <!-- Toggle digital -->
                <label v-if="!exportInProgress" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100">
                    <input v-model="exportIncludeDigital" type="checkbox" class="w-4 h-4 text-gold rounded focus:ring-gold" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-700">Inclure aussi les fichiers numériques</p>
                        <p class="text-xs text-gray-500">Par defaut, seules les commandes papier sont exportées.</p>
                    </div>
                </label>

                <!-- Progress -->
                <div v-if="exportInProgress" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">{{ exportPhaseLabel }}</span>
                        <span v-if="latestExport && latestExport.total_items > 0" class="text-sm text-blue-600 font-medium">
                            {{ latestExport.processed_items }} / {{ latestExport.total_items }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 rounded-full h-2 transition-all duration-500" :style="{ width: exportProgressPercent + '%' }"></div>
                    </div>
                </div>

                <!-- Failed -->
                <div v-if="latestExport?.status === 'failed'" class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-sm font-medium text-red-800">Echec de l'export</p>
                    <p v-if="latestExport.error_message" class="text-sm text-red-600 mt-1">{{ latestExport.error_message }}</p>
                </div>

                <!-- Completed -->
                <div v-if="latestExport?.status === 'completed'" class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-sm font-medium text-green-800">Export prêt</p>
                    <p class="text-xs text-green-700 mt-1">
                        {{ latestExport.total_items }} fichiers · {{ formatFileSize(latestExport.file_size_bytes ?? 0) }}
                    </p>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showExportModal = false">Fermer</Button>
                <Button
                    v-if="latestExport?.status === 'completed' && !exportRegenerate"
                    @click="downloadExport"
                >
                    Télécharger le ZIP
                </Button>
                <Button
                    v-if="latestExport?.status === 'completed' && !exportRegenerate"
                    variant="secondary"
                    @click="exportRegenerate = true"
                >
                    Regenerer
                </Button>
                <Button
                    v-if="!exportInProgress && (latestExport?.status !== 'completed' || exportRegenerate)"
                    :loading="isCreatingExport"
                    @click="startExport"
                >
                    {{ latestExport?.status === 'failed' ? 'Réessayer' : 'Generer le ZIP' }}
                </Button>
            </template>
        </Modal>

        <!-- Order Detail Modal -->
        <Modal v-model="showOrderModal" :title="selectedOrder ? `Commande ${selectedOrder.order_number}` : ''" size="lg">
            <div v-if="selectedOrder" class="space-y-5">
                <!-- Header -->
                <div class="flex items-center justify-between bg-gray-50 rounded-xl p-4">
                    <div class="text-sm text-gray-600">
                        <p v-if="selectedOrder.paid_at">Payée le {{ formatDate(selectedOrder.paid_at) }}</p>
                        <p v-else>Créée le {{ formatDate(selectedOrder.created_at) }}</p>
                    </div>
                    <p class="text-2xl font-bold text-gold">{{ formatPrice(selectedOrder.total) }}</p>
                </div>

                <!-- Customer -->
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Client</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 text-xs">Nom</p>
                            <p class="text-gray-900">{{ selectedOrder.customer_name || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Email</p>
                            <p class="text-gray-900 break-all">{{ selectedOrder.customer_email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Photos commandées</h4>
                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-200">
                        <div
                            v-for="item in selectedOrder.items"
                            :key="item.id"
                            class="flex items-center gap-4 p-3"
                        >
                            <div class="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                <img
                                    v-if="item.thumbnail_url || item.display_url"
                                    :src="item.thumbnail_url || item.display_url"
                                    :alt="item.photo_title || 'Photo'"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ item.photo_title || 'Photo' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ item.gallery_title }}</p>
                                <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                    {{ item.product_type_label }}
                                </span>
                            </div>
                            <div class="text-right">
                                <p v-if="item.quantity > 1" class="text-xs text-gray-500">× {{ item.quantity }}</p>
                                <p class="text-sm font-medium text-gray-900">{{ formatPrice(item.price) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showOrderModal = false">Fermer</Button>
            </template>
        </Modal>

        <PhotosManager
            v-model="showPhotosModal"
            :gallery-id="photosManagerGallery?.id || null"
            :gallery-title="photosManagerGallery?.title || 'Photos'"
            @photos-changed="fetchGalleries"
        />
    </div>
</template>

<script setup lang="ts">
import {ref, computed, onMounted, onUnmounted, watch} from 'vue'
import {useRoute, useRouter} from 'vue-router'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import SmsTemplateField from '@/components/admin/SmsTemplateField.vue'
import PhotosManager from '@/components/admin/PhotosManager.vue'
import {adminApi} from '@/services/adminApi'
import {AdminApiError} from '@/services/admin/baseAdmin'
import {useToast} from '@/composables/useToast'
import {useConfirm} from '@/composables/useConfirm'
import {useEta} from '@/composables/useEta'
import {formatPrice} from '@/utils/format'
import type {AdminOrder, SchoolSession, SchoolSessionExport, SchoolSessionGallery} from '@/types/admin'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const {confirm} = useConfirm()
const processingEta = useEta()

const sessionId = computed(() => route.params.id as string)

const session = ref<SchoolSession | null>(null)
const galleries = ref<SchoolSessionGallery[]>([])
const orders = ref<AdminOrder[]>([])
const ordersLoading = ref(false)
const ordersCollapsed = ref(false)
const orderSearch = ref('')
const showOrderModal = ref(false)
const selectedOrder = ref<AdminOrder | null>(null)
const isLoading = ref(true)

const ordersTotal = computed(() =>
    orders.value.reduce((sum, o) => sum + Number(o.total ?? 0), 0)
)

const filteredOrders = computed(() => {
    if (!orderSearch.value.trim()) return orders.value
    const q = orderSearch.value.toLowerCase()
    return orders.value.filter(o =>
        (o.customer_name?.toLowerCase().includes(q) ?? false)
        || o.customer_email.toLowerCase().includes(q)
    )
})

function openOrderDetail(order: AdminOrder) {
    selectedOrder.value = order
    showOrderModal.value = true
}

function totalPhotos(order: AdminOrder): number {
    return order.items.reduce((sum, item) => sum + (item.quantity ?? 1), 0)
}

const gallerySearch = ref('')
const collapsedClasses = ref<Record<string, boolean>>({})
const showPhotosModal = ref(false)
const photosManagerGallery = ref<SchoolSessionGallery | null>(null)

function openPhotosManager(gallery: SchoolSessionGallery) {
    photosManagerGallery.value = gallery
    showPhotosModal.value = true
}

let pollInterval: ReturnType<typeof setInterval> | null = null

// SMS template editor — local buffer so user can edit without saving immediately
const smsTemplateInput = ref('')
const isSavingSmsTemplate = ref(false)
const smsTemplateDirty = computed(() => (smsTemplateInput.value ?? '') !== (session.value?.sms_template ?? ''))

async function saveSmsTemplate() {
    if (!session.value) return
    isSavingSmsTemplate.value = true
    try {
        const value = smsTemplateInput.value.trim()
        const {data} = await adminApi.updateSchoolSession(session.value.id, {
            sms_template: value === '' ? null : value,
        })
        session.value = {...session.value, ...data}
        smsTemplateInput.value = data.sms_template ?? ''
        toast.success('Modèle SMS enregistré')
    } catch (e) {
        const msg = e instanceof AdminApiError ? e.message : 'Impossible d\'enregistrer le modèle SMS'
        toast.error('Erreur', msg)
    } finally {
        isSavingSmsTemplate.value = false
    }
}

const subtitleText = computed(() => {
    if (!session.value) return ''
    if (session.value.event_date) return `Shooting du ${formatDate(session.value.event_date)}`
    return ''
})

const isProcessing = computed(() => {
    const s = session.value?.status
    return s === 'uploading' || s === 'extracting' || s === 'creating_galleries' || s === 'processing_photos'
})

const processingPhaseLabel = computed(() => {
    switch (session.value?.status) {
        case 'uploading':
            return 'En attente du traitement...'
        case 'extracting':
            return 'Extraction du ZIP...'
        case 'creating_galleries':
            return 'Creation des galeries...'
        case 'processing_photos':
            return 'Traitement des photos...'
        default:
            return ''
    }
})

const failedPhotosCount = computed(() => session.value?.batch_progress?.failed ?? 0)
const isRetrying = ref(false)

const processingProgress = computed(() => {
    const bp = session.value?.batch_progress
    if (!bp || !bp.found) return 0
    return bp.progress
})

const filteredGalleries = computed(() => {
    if (!gallerySearch.value) return galleries.value
    const q = gallerySearch.value.toLowerCase()
    return galleries.value.filter(g =>
        g.title.toLowerCase().includes(q) || (g.class_name?.toLowerCase().includes(q) ?? false)
    )
})

const groupedGalleries = computed(() => {
    const list = filteredGalleries.value
    const hasClasses = list.some(g => g.class_name)
    if (!hasClasses) return [{className: '__all__', galleries: list}]

    const groups = new Map<string, SchoolSessionGallery[]>()
    for (const gallery of list) {
        const key = gallery.class_name ?? 'Sans classe'
        if (!groups.has(key)) groups.set(key, [])
        groups.get(key)!.push(gallery)
    }
    return Array.from(groups.entries())
        .sort(([a], [b]) => a.localeCompare(b, 'fr'))
        .map(([className, gs]) => ({className, galleries: gs}))
})

function toggleClass(className: string) {
    collapsedClasses.value[className] = !collapsedClasses.value[className]
}

async function fetchSession() {
    try {
        const {data} = await adminApi.getSchoolSession(sessionId.value)
        session.value = data

        // Only seed the editor on initial load or when not dirty — never clobber unsaved edits
        if (!smsTemplateDirty.value) {
            smsTemplateInput.value = data.sms_template ?? ''
        }

        if (data.batch_progress?.found) {
            processingEta.update(
                data.batch_progress.completed + data.batch_progress.failed,
                data.batch_progress.total,
            )
        }
    } catch {
        toast.error('Erreur', 'Impossible de charger la session')
    }
}

async function fetchGalleries() {
    try {
        const {data} = await adminApi.getSchoolSessionGalleries(sessionId.value)
        galleries.value = data
    } catch { /* ignore */
    }
}

async function fetchOrders() {
    ordersLoading.value = true
    try {
        const {orders: list} = await adminApi.getSchoolSessionOrders(sessionId.value)
        orders.value = list
    } catch { /* ignore */
    } finally {
        ordersLoading.value = false
    }
}

function startPolling() {
    stopPolling()
    pollInterval = setInterval(async () => {
        const previousGalleryCount = galleries.value.length
        await fetchSession()

        if (session.value?.status === 'processing_photos' && previousGalleryCount === 0) {
            await fetchGalleries()
        }

        if (session.value?.status === 'completed' || session.value?.status === 'failed') {
            stopPolling()
            if (session.value.status === 'completed') {
                toast.success('Termine', `${session.value.total_galleries} galeries creées`)
                await fetchGalleries()
            }
        }
    }, 3000)
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval)
        pollInterval = null
    }
}

async function retryFailedPhotos() {
    if (!session.value || isRetrying.value) return
    isRetrying.value = true
    try {
        const result = await adminApi.retryFailedPhotos(session.value.id)
        toast.success('Relance', result.message)
        await fetchSession()
        if (!pollInterval && isProcessing.value) {
            startPolling()
        }
    } catch {
        toast.error('Erreur', 'Impossible de relancer les photos en echec')
    } finally {
        isRetrying.value = false
    }
}

function goBack() {
    router.push({name: 'admin-school-sessions'})
}

async function confirmClose() {
    if (!session.value) return
    const confirmed = await confirm({
        title: 'Cloturer ce shooting ?',
        message: 'Les parents ne pourront plus commander de photos sur les galeries de cette session. Vous pourrez la rouvrir a tout moment.',
        variant: 'default',
    })
    if (!confirmed) return

    try {
        const {data} = await adminApi.closeSchoolSession(session.value.id)
        session.value = data
        toast.success('Session cloturée')
    } catch {
        toast.error('Erreur', 'Impossible de cloturer la session')
    }
}

async function reopen() {
    if (!session.value) return
    try {
        const {data} = await adminApi.reopenSchoolSession(session.value.id)
        session.value = data
        toast.success('Session rouverte')
    } catch {
        toast.error('Erreur', 'Impossible de rouvrir la session')
    }
}

async function confirmDelete() {
    if (!session.value) return
    const confirmed = await confirm({
        title: 'Supprimer ce shooting ?',
        message: `"${session.value.title}" et toutes ses galeries (${session.value.total_galleries}) seront definitivement supprimes.`,
        variant: 'danger',
    })
    if (!confirmed) return

    try {
        await adminApi.deleteSchoolSession(session.value.id)
        toast.success('Supprime')
        router.push({name: 'admin-school-sessions'})
    } catch {
        toast.error('Erreur', 'Impossible de supprimer le shooting')
    }
}

function copyGalleryLink(gallery: SchoolSessionGallery) {
    const url = `${window.location.origin}/gallery/${gallery.share_code}`
    navigator.clipboard.writeText(url).then(() => {
        toast.success('Lien copie')
    }).catch(() => {
        toast.error('Erreur', 'Impossible de copier le lien')
    })
}

function exportGalleriesCsv() {
    if (!galleries.value.length || !session.value) return
    const baseUrl = window.location.origin
    const rows = [
        ['Classe', 'Nom', 'Code', 'Lien', 'Photos'].join(';'),
        ...galleries.value.map(g =>
            [g.class_name ?? '', g.title, g.share_code, `${baseUrl}/gallery/${g.share_code}`, g.photos_count].join(';')
        ),
    ]
    const blob = new Blob([rows.join('\n')], {type: 'text/csv;charset=utf-8;'})
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = `${session.value.title.replace(/\s+/g, '_')}_liens.csv`
    link.click()
    URL.revokeObjectURL(link.href)
}

// ==================== MESSAGES MODAL (email or SMS) ====================
type Channel = 'email' | 'sms'

interface MessageContact {
    gallery_id: string
    recipient_name: string
    email?: string
    phone?: string
}

const showMessagesModal = ref(false)
const isSending = ref(false)
const csvFileName = ref('')
const channel = ref<Channel>('email')
const contacts = ref<MessageContact[]>([])
const manualForm = ref({gallery_id: '', email: '', phone: ''})

function isContactValid(contact: MessageContact): boolean {
    if (!contact.gallery_id) return false
    if (channel.value === 'email') {
        return !!contact.email && contact.email.includes('@')
    }
    return !!contact.phone && /\d/.test(contact.phone)
}

function contactInvalidReason(contact: MessageContact): string {
    if (!contact.gallery_id) return 'Non matche'
    if (channel.value === 'email' && !contact.email) return 'Pas d\'email'
    if (channel.value === 'sms' && !contact.phone) return 'Pas de tel'
    return 'Invalide'
}

const validContacts = computed(() => contacts.value.filter(isContactValid))
const invalidContactsCount = computed(() => contacts.value.length - validContacts.value.length)
const unmatchedGalleries = computed(() => {
    const usedIds = new Set(contacts.value.map(c => c.gallery_id).filter(Boolean))
    return galleries.value.filter(g => !usedIds.has(g.id))
})

const canAddManual = computed(() => {
    if (!manualForm.value.gallery_id) return false
    return channel.value === 'email' ? !!manualForm.value.email : !!manualForm.value.phone
})

function openMessagesModal() {
    contacts.value = []
    csvFileName.value = ''
    manualForm.value = {gallery_id: '', email: '', phone: ''}
    showMessagesModal.value = true
}

function setChannel(c: Channel) {
    channel.value = c
}

function onImportCsv(e: Event) {
    const input = e.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return

    csvFileName.value = file.name
    const reader = new FileReader()
    reader.onload = () => {
        const text = (reader.result as string).replace(/^\uFEFF/, '')
        const lines = text.split(/\r?\n/).filter(l => l.trim())
        const start = /^nom/i.test(lines[0]) ? 1 : 0

        for (let i = start; i < lines.length; i++) {
            const parts = lines[i].split(/[;,]/).map(s => s.trim())
            if (parts.length < 2) continue

            const name = parts[0]
            const email = parts[1] || ''
            const phone = parts[2] || ''
            if (!name) continue

            const gallery = galleries.value.find(g => g.title.toLowerCase() === name.toLowerCase())
            contacts.value.push({
                gallery_id: gallery?.id ?? '',
                recipient_name: name,
                email: email || undefined,
                phone: phone || undefined,
            })
        }
    }
    reader.readAsText(file, 'UTF-8')
    input.value = ''
}

function addManualContact() {
    const gallery = galleries.value.find(g => g.id === manualForm.value.gallery_id)
    if (!gallery) return

    contacts.value.push({
        gallery_id: gallery.id,
        recipient_name: gallery.title,
        email: manualForm.value.email || undefined,
        phone: manualForm.value.phone || undefined,
    })
    manualForm.value = {gallery_id: '', email: '', phone: ''}
}

async function sendMessages() {
    if (!session.value || validContacts.value.length === 0) return
    isSending.value = true
    try {
        const payload = validContacts.value.map(c => ({
            gallery_id: c.gallery_id,
            recipient_name: c.recipient_name,
            email: c.email,
            phone: c.phone,
        }))
        const result = await adminApi.sendSchoolSessionMessages(
            session.value.id,
            channel.value,
            payload,
        )
        toast.success('Envoye', result.message)
        if (result.errors.length > 0) {
            toast.warning('Avertissement', `${result.errors.length} erreur(s)`)
        }
        showMessagesModal.value = false
    } catch {
        toast.error('Erreur', 'Echec de l\'envoi')
    } finally {
        isSending.value = false
    }
}

// ==================== EXPORT MODAL ====================
const showExportModal = ref(false)
const isCreatingExport = ref(false)
const exportIncludeDigital = ref(false)
const exportRegenerate = ref(false)
const latestExport = ref<SchoolSessionExport | null>(null)
let exportPollInterval: ReturnType<typeof setInterval> | null = null

const exportInProgress = computed(() => {
    const s = latestExport.value?.status
    return s === 'pending' || s === 'processing'
})

const exportPhaseLabel = computed(() => {
    switch (latestExport.value?.status) {
        case 'pending':
            return 'En attente du worker...'
        case 'processing':
            return 'Generation du ZIP en cours...'
        default:
            return ''
    }
})

const exportProgressPercent = computed(() => {
    const e = latestExport.value
    if (!e || e.total_items === 0) return 0
    return Math.min(100, Math.round((e.processed_items / e.total_items) * 100))
})

async function openExportModal() {
    showExportModal.value = true
    exportRegenerate.value = false
    exportIncludeDigital.value = false
    await fetchLatestExport()
    if (exportInProgress.value) {
        startExportPolling()
    }
}

async function fetchLatestExport() {
    if (!session.value) return
    try {
        const {data} = await adminApi.getLatestSchoolSessionExport(session.value.id)
        latestExport.value = data
    } catch { /* ignore */
    }
}

async function startExport() {
    if (!session.value) return
    isCreatingExport.value = true
    try {
        const {data} = await adminApi.createSchoolSessionExport(
            session.value.id,
            exportIncludeDigital.value,
        )
        latestExport.value = data
        exportRegenerate.value = false
        startExportPolling()
    } catch (e: unknown) {
        const msg = e instanceof AdminApiError
            ? e.message
            : "Une erreur s'est produite, veuillez réessayer plus tard. Si l'erreur persiste, n'hésitez pas à me contacter."
        toast.error('Erreur', msg)
    } finally {
        isCreatingExport.value = false
    }
}

function startExportPolling() {
    stopExportPolling()
    exportPollInterval = setInterval(async () => {
        await fetchLatestExport()
        if (latestExport.value?.status === 'completed' || latestExport.value?.status === 'failed') {
            stopExportPolling()
            if (latestExport.value.status === 'completed') {
                toast.success('Export prêt', 'Le ZIP est disponible au téléchargement.')
            }
        }
    }, 3000)
}

function stopExportPolling() {
    if (exportPollInterval) {
        clearInterval(exportPollInterval)
        exportPollInterval = null
    }
}

async function downloadExport() {
    if (!latestExport.value) return
    const url = adminApi.getSchoolSessionExportDownloadUrl(latestExport.value.id)
    const token = localStorage.getItem('auth_token')

    try {
        const res = await fetch(url, {headers: token ? {'Authorization': `Bearer ${token}`} : {}})

        if (!res.ok) {
            // Le backend a marqué l'export comme `failed` si le fichier n'est plus disponible
            // (410 Gone, cf. SchoolSessionController::downloadExport). On rafraîchit l'état
            // pour que le bouton "Réessayer" apparaisse naturellement.
            if (res.status === 410) {
                await fetchLatestExport()
                toast.error("Fichier expiré", "Le ZIP n'est plus disponible. Cliquez sur « Réessayer » pour le régénérer.")
            } else {
                toast.error('Erreur', 'Impossible de télécharger le ZIP')
            }
            return
        }

        const blob = await res.blob()
        const blobUrl = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = blobUrl
        a.download = session.value ? `${session.value.title.replace(/\s+/g, '_')}_commandes.zip` : 'export.zip'
        a.click()
        URL.revokeObjectURL(blobUrl)
    } catch {
        toast.error('Erreur', 'Impossible de télécharger le ZIP')
    }
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' Ko'
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' Mo'
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' Go'
}

// ==================== UTILS ====================
function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

// ==================== LIFECYCLE ====================
watch(isProcessing, (active) => {
    if (active && !pollInterval) {
        startPolling()
    } else if (!active && pollInterval) {
        stopPolling()
    }
})

onMounted(async () => {
    await fetchSession()
    if (session.value) {
        if (session.value.status === 'completed' || session.value.status === 'processing_photos') {
            await fetchGalleries()
        }
        if (session.value.status === 'completed') {
            fetchOrders()
        }
        if (isProcessing.value) {
            startPolling()
        }
    }
    isLoading.value = false
})

onUnmounted(() => {
    stopPolling()
    stopExportPolling()
})
</script>
