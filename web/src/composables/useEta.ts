import { ref, type Ref } from 'vue'

interface DataPoint {
    time: number       // timestamp ms
    completed: number
}

const WINDOW_MS = 30_000    // sliding window: 30 seconds
const MIN_POINTS = 2        // minimum data points before showing ETA
const MIN_ELAPSED_MS = 3000 // don't show ETA before 3s of data

export interface UseEtaReturn {
    /** Formatted ETA string: "~3 min", "~1h 20min", "< 1 min", null if not enough data */
    eta: Ref<string | null>
    /** Items per second (smoothed) */
    speed: Ref<number>
    /** Feed a progress update */
    update: (completed: number, total: number) => void
    /** Reset all tracking state */
    reset: () => void
}

export function useEta(): UseEtaReturn {
    const eta = ref<string | null>(null)
    const speed = ref(0)
    let points: DataPoint[] = []

    function update(completed: number, total: number): void {
        const now = Date.now()

        points.push({ time: now, completed })

        // Trim points outside the sliding window
        const cutoff = now - WINDOW_MS
        points = points.filter(p => p.time >= cutoff)

        if (points.length < MIN_POINTS) {
            eta.value = null
            speed.value = 0
            return
        }

        const oldest = points[0]
        const newest = points[points.length - 1]
        const elapsed = newest.time - oldest.time
        const progress = newest.completed - oldest.completed

        if (elapsed < MIN_ELAPSED_MS || progress <= 0) {
            eta.value = completed > 0 ? 'Calcul...' : null
            speed.value = 0
            return
        }

        // Items per second
        const itemsPerMs = progress / elapsed
        speed.value = itemsPerMs * 1000

        const remaining = total - completed
        if (remaining <= 0) {
            eta.value = null
            return
        }

        const remainingMs = remaining / itemsPerMs
        eta.value = formatDuration(remainingMs)
    }

    function reset(): void {
        points = []
        eta.value = null
        speed.value = 0
    }

    return { eta, speed, update, reset }
}

function formatDuration(ms: number): string {
    const seconds = Math.ceil(ms / 1000)

    if (seconds < 60) return '< 1 min'

    const minutes = Math.floor(seconds / 60)
    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60

    if (hours === 0) {
        return `~${minutes} min`
    }

    if (remainingMinutes === 0) {
        return `~${hours}h`
    }

    return `~${hours}h ${remainingMinutes}min`
}
