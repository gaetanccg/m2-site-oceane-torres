/**
 * Utilitaire centralisé pour la gestion des erreurs
 * Fournit des messages d'erreur en français compréhensibles par les utilisateurs
 */

export interface ApiError {
    status: number
    message: string
    errors?: Record<string, string[]>
    isNetworkError: boolean
    isValidationError: boolean
    isAuthError: boolean
    isServerError: boolean
}

/**
 * Messages d'erreur par défaut selon le code HTTP
 */
const HTTP_ERROR_MESSAGES: Record<number, string> = {
    400: 'Les données envoyées sont invalides.',
    401: 'Vous devez vous connecter pour accéder à cette ressource.',
    403: 'Vous n\'avez pas les droits pour effectuer cette action.',
    404: 'La ressource demandée n\'existe pas ou a été supprimée.',
    408: 'La requête a pris trop de temps. Veuillez réessayer.',
    409: 'Un conflit est survenu. Les données ont peut-être été modifiées.',
    422: 'Les données fournies ne sont pas valides.',
    429: 'Trop de requêtes. Veuillez patienter quelques instants.',
    500: 'Une erreur serveur est survenue. Veuillez réessayer plus tard.',
    502: 'Le serveur est temporairement indisponible.',
    503: 'Le service est en maintenance. Veuillez réessayer plus tard.',
    504: 'Le serveur ne répond pas. Veuillez réessayer.',
}

/**
 * Messages d'erreur pour les erreurs réseau
 */
const NETWORK_ERROR_MESSAGES: Record<string, string> = {
    'Failed to fetch': 'Impossible de contacter le serveur. Vérifiez votre connexion internet.',
    'NetworkError': 'Erreur réseau. Vérifiez votre connexion internet.',
    'AbortError': 'La requête a été annulée.',
    'Request timeout': 'La requête a expiré. Le serveur met trop de temps à répondre.',
    'TypeError': 'Impossible de contacter le serveur. Vérifiez votre connexion internet.',
}

/**
 * Messages d'erreur spécifiques par contexte
 */
export const ERROR_MESSAGES = {
    // Authentification
    auth: {
        invalidCredentials: 'Email ou mot de passe incorrect.',
        accountLocked: 'Votre compte a été temporairement bloqué. Réessayez plus tard.',
        emailNotVerified: 'Veuillez vérifier votre adresse email avant de vous connecter.',
        sessionExpired: 'Votre session a expiré. Veuillez vous reconnecter.',
        unauthorized: 'Vous devez vous connecter pour accéder à cette page.',
    },
    // Formulaires
    form: {
        invalidEmail: 'Veuillez saisir une adresse email valide.',
        passwordTooShort: 'Le mot de passe doit contenir au moins 8 caractères.',
        passwordMismatch: 'Les mots de passe ne correspondent pas.',
        requiredField: 'Ce champ est obligatoire.',
        invalidPhone: 'Veuillez saisir un numéro de téléphone valide.',
    },
    // Galeries
    gallery: {
        notFound: 'Cette galerie n\'existe pas ou a été supprimée.',
        accessDenied: 'Vous n\'avez pas accès à cette galerie.',
        invalidCode: 'Le code d\'accès est invalide ou a expiré.',
        expired: 'L\'accès à cette galerie a expiré.',
    },
    // Réservations
    booking: {
        prestationUnavailable: 'Cette prestation n\'est plus disponible.',
        slotUnavailable: 'Ce créneau n\'est plus disponible.',
        alreadyBooked: 'Vous avez déjà une réservation pour cette date.',
    },
    // Paiements
    payment: {
        failed: 'Le paiement a échoué. Veuillez réessayer.',
        cardDeclined: 'Votre carte a été refusée. Veuillez utiliser un autre moyen de paiement.',
        insufficientFunds: 'Fonds insuffisants sur votre carte.',
    },
    // Fichiers
    file: {
        tooLarge: 'Le fichier est trop volumineux. Taille maximum : 10 Mo.',
        invalidType: 'Ce type de fichier n\'est pas accepté.',
        uploadFailed: 'L\'envoi du fichier a échoué. Veuillez réessayer.',
    },
    // Génériques
    generic: {
        unknownError: 'Une erreur inattendue est survenue. Veuillez réessayer.',
        networkError: 'Impossible de contacter le serveur. Vérifiez votre connexion.',
        serverError: 'Le serveur rencontre des difficultés. Veuillez réessayer plus tard.',
        notFound: 'La page ou ressource demandée n\'existe pas.',
        forbidden: 'Vous n\'avez pas les droits pour effectuer cette action.',
    },
} as const

/**
 * Parse une erreur et retourne un objet ApiError structuré
 */
export function parseApiError(error: unknown, response?: Response): ApiError {
    // Erreur réseau (pas de réponse du serveur)
    if (error instanceof TypeError || (error instanceof Error && error.message === 'Failed to fetch')) {
        return {
            status: 0,
            message: NETWORK_ERROR_MESSAGES['Failed to fetch'],
            isNetworkError: true,
            isValidationError: false,
            isAuthError: false,
            isServerError: false,
        }
    }

    // Timeout
    if (error instanceof Error && (error.name === 'AbortError' || error.message === 'Request timeout')) {
        return {
            status: 408,
            message: NETWORK_ERROR_MESSAGES['Request timeout'],
            isNetworkError: true,
            isValidationError: false,
            isAuthError: false,
            isServerError: false,
        }
    }

    // Erreur avec réponse HTTP
    if (response) {
        const status = response.status
        return {
            status,
            message: HTTP_ERROR_MESSAGES[status] || ERROR_MESSAGES.generic.unknownError,
            isNetworkError: false,
            isValidationError: status === 422,
            isAuthError: status === 401,
            isServerError: status >= 500,
        }
    }

    // Erreur générique
    const message = error instanceof Error ? error.message : ERROR_MESSAGES.generic.unknownError
    return {
        status: 0,
        message: translateErrorMessage(message),
        isNetworkError: false,
        isValidationError: false,
        isAuthError: false,
        isServerError: false,
    }
}

/**
 * Traduit les messages d'erreur anglais courants en français
 */
function translateErrorMessage(message: string): string {
    const translations: Record<string, string> = {
        'The given data was invalid.': 'Les données fournies ne sont pas valides.',
        'These credentials do not match our records.': 'Ces identifiants ne correspondent à aucun compte.',
        'The email has already been taken.': 'Cette adresse email est déjà utilisée.',
        'The password must be at least 8 characters.': 'Le mot de passe doit contenir au moins 8 caractères.',
        'The password confirmation does not match.': 'Les mots de passe ne correspondent pas.',
        'The email field is required.': 'L\'adresse email est obligatoire.',
        'The password field is required.': 'Le mot de passe est obligatoire.',
        'The name field is required.': 'Le nom est obligatoire.',
        'Unauthenticated.': 'Vous devez vous connecter.',
        'Unauthorized.': 'Vous n\'avez pas les droits nécessaires.',
        'This action is unauthorized.': 'Cette action n\'est pas autorisée.',
        'Too Many Requests': 'Trop de tentatives. Veuillez patienter.',
        'Server Error': 'Erreur serveur. Veuillez réessayer plus tard.',
        'Not Found': 'Ressource non trouvée.',
    }

    return translations[message] || message
}

/**
 * Extrait le message d'erreur d'une réponse JSON de l'API
 */
export async function extractErrorFromResponse(response: Response): Promise<ApiError> {
    const status = response.status

    try {
        const data = await response.json()

        // Format Laravel validation errors
        if (data.errors && typeof data.errors === 'object') {
            const firstError = Object.values(data.errors).flat()[0] as string
            return {
                status,
                message: translateErrorMessage(data.message || firstError || HTTP_ERROR_MESSAGES[status]),
                errors: data.errors,
                isNetworkError: false,
                isValidationError: status === 422,
                isAuthError: status === 401,
                isServerError: status >= 500,
            }
        }

        // Simple message
        return {
            status,
            message: translateErrorMessage(data.message || HTTP_ERROR_MESSAGES[status] || ERROR_MESSAGES.generic.unknownError),
            isNetworkError: false,
            isValidationError: status === 422,
            isAuthError: status === 401,
            isServerError: status >= 500,
        }
    } catch {
        // Impossible de parser le JSON
        return {
            status,
            message: HTTP_ERROR_MESSAGES[status] || ERROR_MESSAGES.generic.unknownError,
            isNetworkError: false,
            isValidationError: status === 422,
            isAuthError: status === 401,
            isServerError: status >= 500,
        }
    }
}

/**
 * Formate les erreurs de validation pour l'affichage par champ
 */
export function formatValidationErrors(errors: Record<string, string[]>): Record<string, string> {
    const formatted: Record<string, string> = {}

    for (const [field, messages] of Object.entries(errors)) {
        formatted[field] = translateErrorMessage(messages[0])
    }

    return formatted
}

/**
 * Retourne un message d'erreur approprié selon le contexte
 */
export function getErrorMessage(error: ApiError, context?: keyof typeof ERROR_MESSAGES): string {
    // Si on a un message spécifique du serveur, l'utiliser
    if (error.message && error.message !== ERROR_MESSAGES.generic.unknownError) {
        return error.message
    }

    // Sinon, utiliser un message par défaut selon le contexte
    if (context && ERROR_MESSAGES[context]) {
        if (error.isAuthError && 'unauthorized' in ERROR_MESSAGES[context]) {
            return (ERROR_MESSAGES[context] as Record<string, string>)['unauthorized']
        }
    }

    return error.message
}
