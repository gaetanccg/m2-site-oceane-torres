import { ref, readonly } from 'vue'

export type ConfirmVariant = 'default' | 'danger'

export interface ConfirmOptions {
    title?: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
    variant?: ConfirmVariant
}

export interface ConfirmState {
    visible: boolean
    title: string
    message: string
    confirmLabel: string
    cancelLabel: string
    variant: ConfirmVariant
}

const state = ref<ConfirmState>({
    visible: false,
    title: '',
    message: '',
    confirmLabel: 'Confirmer',
    cancelLabel: 'Annuler',
    variant: 'default',
})

let resolvePromise: ((value: boolean) => void) | null = null

export function useConfirm() {
    function confirm(optionsOrMessage: string | ConfirmOptions): Promise<boolean> {
        // If a dialog is already open, resolve it as false
        if (resolvePromise) {
            resolvePromise(false)
            resolvePromise = null
        }

        const options: ConfirmOptions =
            typeof optionsOrMessage === 'string'
                ? { message: optionsOrMessage }
                : optionsOrMessage

        state.value = {
            visible: true,
            title: options.title ?? '',
            message: options.message,
            confirmLabel: options.confirmLabel ?? (options.variant === 'danger' ? 'Supprimer' : 'Confirmer'),
            cancelLabel: options.cancelLabel ?? 'Annuler',
            variant: options.variant ?? 'default',
        }

        return new Promise<boolean>((resolve) => {
            resolvePromise = resolve
        })
    }

    function resolve(value: boolean) {
        state.value.visible = false
        if (resolvePromise) {
            resolvePromise(value)
            resolvePromise = null
        }
    }

    return {
        state: readonly(state),
        confirm,
        resolve,
    }
}
