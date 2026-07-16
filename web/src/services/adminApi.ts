/**
 * Facade admin API — ré-exporte toutes les méthodes des sous-services
 * pour rétrocompatibilité avec les imports existants.
 *
 * Les sous-services sont dans ./admin/ :
 *   dashboardApi, reservationApi, clientApi, prestationApi, galleryApi, orderApi
 */

import { dashboardApi } from './admin/dashboardApi'
import { reservationApi } from './admin/reservationApi'
import { clientApi } from './admin/clientApi'
import { prestationApi } from './admin/prestationApi'
import { galleryApi } from './admin/galleryApi'
import { giftCodeApi } from './admin/giftCodeApi'
import { orderApi } from './admin/orderApi'
import { schoolSessionApi } from './admin/schoolSessionApi'
import { privacyApi } from './admin/privacyApi'
import { logApi } from './admin/logApi'

export { AdminApiError } from './admin/baseAdmin'

export const adminApi = {
    // Auth & Dashboard
    login: dashboardApi.login.bind(dashboardApi),
    logout: dashboardApi.logout.bind(dashboardApi),
    getUser: dashboardApi.getUser.bind(dashboardApi),
    getDashboardStats: dashboardApi.getDashboardStats.bind(dashboardApi),
    getRecentActivity: dashboardApi.getRecentActivity.bind(dashboardApi),
    getNotifications: dashboardApi.getNotifications.bind(dashboardApi),
    markNotificationRead: dashboardApi.markNotificationRead.bind(dashboardApi),
    markAllNotificationsRead: dashboardApi.markAllNotificationsRead.bind(dashboardApi),

    // Reservations
    getReservations: reservationApi.getReservations.bind(reservationApi),
    getReservation: reservationApi.getReservation.bind(reservationApi),
    updateReservationStatus: reservationApi.updateReservationStatus.bind(reservationApi),
    updateReservation: reservationApi.updateReservation.bind(reservationApi),
    getCalendarEvents: reservationApi.getCalendarEvents.bind(reservationApi),
    deleteReservation: reservationApi.deleteReservation.bind(reservationApi),

    // Clients
    getClients: clientApi.getClients.bind(clientApi),
    getClient: clientApi.getClient.bind(clientApi),
    createClient: clientApi.createClient.bind(clientApi),
    updateClient: clientApi.updateClient.bind(clientApi),
    deleteClient: clientApi.deleteClient.bind(clientApi),
    getClientReservations: clientApi.getClientReservations.bind(clientApi),
    exportClientGdpr: clientApi.exportClientGdpr.bind(clientApi),

    // Prestations
    getPrestations: prestationApi.getPrestations.bind(prestationApi),
    getPrestation: prestationApi.getPrestation.bind(prestationApi),
    createPrestation: prestationApi.createPrestation.bind(prestationApi),
    updatePrestation: prestationApi.updatePrestation.bind(prestationApi),
    deletePrestation: prestationApi.deletePrestation.bind(prestationApi),
    togglePrestation: prestationApi.togglePrestation.bind(prestationApi),

    // Galleries
    getGalleries: galleryApi.getGalleries.bind(galleryApi),
    getGallery: galleryApi.getGallery.bind(galleryApi),
    createGallery: galleryApi.createGallery.bind(galleryApi),
    updateGallery: galleryApi.updateGallery.bind(galleryApi),
    deleteGallery: galleryApi.deleteGallery.bind(galleryApi),
    regenerateGalleryToken: galleryApi.regenerateGalleryToken.bind(galleryApi),
    regenerateGalleryCode: galleryApi.regenerateGalleryCode.bind(galleryApi),
    sendGalleryAccessEmail: galleryApi.sendGalleryAccessEmail.bind(galleryApi),
    sendGalleryAccessSms: galleryApi.sendGalleryAccessSms.bind(galleryApi),
    togglePhotoDownloadable: galleryApi.togglePhotoDownloadable.bind(galleryApi),
    uploadPhotos: galleryApi.uploadPhotos.bind(galleryApi),
    deletePhoto: galleryApi.deletePhoto.bind(galleryApi),

    // Event Galleries
    getEventGalleries: galleryApi.getEventGalleries.bind(galleryApi),
    getEventGallery: galleryApi.getEventGallery.bind(galleryApi),
    createEventGallery: galleryApi.createEventGallery.bind(galleryApi),
    updateEventGallery: galleryApi.updateEventGallery.bind(galleryApi),
    deleteEventGallery: galleryApi.deleteEventGallery.bind(galleryApi),
    uploadEventPhotos: galleryApi.uploadEventPhotos.bind(galleryApi),
    getEventGalleryChildren: galleryApi.getEventGalleryChildren.bind(galleryApi),
    setEventThumbnail: galleryApi.setEventThumbnail.bind(galleryApi),

    // Event Categories
    getEventCategories: galleryApi.getEventCategories.bind(galleryApi),
    createEventCategory: galleryApi.createEventCategory.bind(galleryApi),
    updateEventCategory: galleryApi.updateEventCategory.bind(galleryApi),
    deleteEventCategory: galleryApi.deleteEventCategory.bind(galleryApi),
    reorderEventCategories: galleryApi.reorderEventCategories.bind(galleryApi),

    // Gift Cards
    getGiftCards: galleryApi.getGiftCards.bind(galleryApi),
    updateGiftCard: galleryApi.updateGiftCard.bind(galleryApi),

    // Gift Codes (Codes Promo)
    getGiftCodes: giftCodeApi.getGiftCodes.bind(giftCodeApi),
    getGiftCode: giftCodeApi.getGiftCode.bind(giftCodeApi),
    createGiftCode: giftCodeApi.createGiftCode.bind(giftCodeApi),
    updateGiftCode: giftCodeApi.updateGiftCode.bind(giftCodeApi),
    toggleGiftCode: giftCodeApi.toggleGiftCode.bind(giftCodeApi),
    deleteGiftCode: giftCodeApi.deleteGiftCode.bind(giftCodeApi),
    generateGiftCode: giftCodeApi.generateCode.bind(giftCodeApi),

    // Orders
    getOrders: orderApi.getOrders.bind(orderApi),
    getOrder: orderApi.getOrder.bind(orderApi),
    deleteOrder: orderApi.deleteOrder.bind(orderApi),
    markOrderShipped: orderApi.markOrderShipped.bind(orderApi),
    getOrderDownloadLink: orderApi.getOrderDownloadLink.bind(orderApi),
    retryOrderPayment: orderApi.retryOrderPayment.bind(orderApi),

    // School Sessions
    getSchoolSessions: schoolSessionApi.getSchoolSessions.bind(schoolSessionApi),
    getSchoolSession: schoolSessionApi.getSchoolSession.bind(schoolSessionApi),
    createSchoolSession: schoolSessionApi.createSchoolSession.bind(schoolSessionApi),
    updateSchoolSession: schoolSessionApi.updateSchoolSession.bind(schoolSessionApi),
    getSchoolSessionGalleries: schoolSessionApi.getSchoolSessionGalleries.bind(schoolSessionApi),
    getSchoolSessionOrders: schoolSessionApi.getSchoolSessionOrders.bind(schoolSessionApi),
    uploadZipChunk: schoolSessionApi.uploadZipChunk.bind(schoolSessionApi),
    processSchoolSession: schoolSessionApi.processSchoolSession.bind(schoolSessionApi),
    retryFailedPhotos: schoolSessionApi.retryFailedPhotos.bind(schoolSessionApi),
    deleteSchoolSession: schoolSessionApi.deleteSchoolSession.bind(schoolSessionApi),
    sendSchoolSessionMessages: schoolSessionApi.sendSchoolSessionMessages.bind(schoolSessionApi),
    closeSchoolSession: schoolSessionApi.closeSchoolSession.bind(schoolSessionApi),
    reopenSchoolSession: schoolSessionApi.reopenSchoolSession.bind(schoolSessionApi),
    createSchoolSessionExport: schoolSessionApi.createSchoolSessionExport.bind(schoolSessionApi),
    getLatestSchoolSessionExport: schoolSessionApi.getLatestSchoolSessionExport.bind(schoolSessionApi),
    getSchoolSessionExportDownloadUrl: schoolSessionApi.getSchoolSessionExportDownloadUrl.bind(schoolSessionApi),

    // RGPD / Données personnelles
    searchPrivacy: privacyApi.search.bind(privacyApi),
    getPrivacyAudit: privacyApi.getAudit.bind(privacyApi),
    exportAllPrivacy: privacyApi.exportAll.bind(privacyApi),
    exportSubjectPrivacy: privacyApi.exportSubject.bind(privacyApi),
    erasurePreviewPrivacy: privacyApi.erasurePreview.bind(privacyApi),
    erasePrivacy: privacyApi.erase.bind(privacyApi),
    getPrivacyExport: privacyApi.getExport.bind(privacyApi),
    getPrivacyExportDownloadUrl: privacyApi.getExportDownloadUrl.bind(privacyApi),

    // Logs applicatifs
    getLogs: logApi.getLogs.bind(logApi),
    getLogsDownloadUrl: logApi.getDownloadUrl.bind(logApi),
    clearLogs: logApi.clearLogs.bind(logApi),
}

export default adminApi
