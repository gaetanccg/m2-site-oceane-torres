import {describe, it, expect, vi, beforeEach, afterEach} from 'vitest'
import {defineComponent, ref, h} from 'vue'
import {mount} from '@vue/test-utils'
import {useDebouncedSearch} from './useDebouncedSearch'

function monter(source: ReturnType<typeof ref<string>>, callback: () => void, delay?: number) {
    return mount(defineComponent({
        setup() {
            useDebouncedSearch(source as never, callback, delay)

            return () => h('div')
        },
    }))
}

describe('useDebouncedSearch', () => {
    beforeEach(() => vi.useFakeTimers())
    afterEach(() => vi.useRealTimers())

    it('ne déclenche qu’une fois après la dernière frappe', async () => {
        const source = ref('')
        const callback = vi.fn()
        monter(source, callback)

        source.value = 'a'
        await vi.advanceTimersByTimeAsync(100)
        source.value = 'ab'
        await vi.advanceTimersByTimeAsync(100)
        source.value = 'abc'

        expect(callback).not.toHaveBeenCalled()

        await vi.advanceTimersByTimeAsync(300)

        expect(callback).toHaveBeenCalledOnce()
    })

    // Le defaut d'origine : quitter l'ecran pendant la temporisation laissait partir une
    // requete sur un composant detruit.
    it('libère le minuteur au démontage', async () => {
        const source = ref('')
        const callback = vi.fn()
        const wrapper = monter(source, callback)

        source.value = 'recherche'
        await vi.advanceTimersByTimeAsync(50)
        wrapper.unmount()
        await vi.advanceTimersByTimeAsync(1000)

        expect(callback).not.toHaveBeenCalled()
    })

    it('respecte le délai fourni', async () => {
        const source = ref('')
        const callback = vi.fn()
        monter(source, callback, 350)

        source.value = 'x'
        await vi.advanceTimersByTimeAsync(300)
        expect(callback).not.toHaveBeenCalled()

        await vi.advanceTimersByTimeAsync(50)
        expect(callback).toHaveBeenCalledOnce()
    })
})
