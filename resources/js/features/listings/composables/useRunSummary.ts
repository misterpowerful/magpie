import { onUnmounted, ref } from 'vue'
import api from '@/shared/lib/api'
import { currentEcho, loadEcho } from '@/shared/lib/echo'
import type { Run, RunStatus } from '../types'

interface RunProgressPayload {
    run_id: number
    status: RunStatus
    stage_stats: Record<string, unknown>
    backlog: number
}

/**
 * @param userId a getter (not a plain value) since it's read lazily,
 * once the auth store has actually resolved the current user.
 */
export function useRunSummary(userId: () => number | null | undefined) {
    const backlog = ref(0)
    const pending = ref(false)
    const running = ref(false)
    const latestRun = ref<Run | null>(null)
    const loading = ref(false)
    let subscribedChannelName: string | null = null
    let unmounted = false

    async function load() {
        loading.value = true
        try {
            const { data } = await api.get<{ data: Run[]; backlog: number; pending: boolean; running: boolean }>('/api/runs')
            latestRun.value = data.data[0] ?? null
            backlog.value = data.backlog
            pending.value = data.pending
            running.value = data.running
        } finally {
            loading.value = false
        }
    }

    async function subscribeToLiveUpdates() {
        const id = userId()

        if (!id) {
            return
        }

        const echo = await loadEcho()

        // The client loads on demand; the dashboard may be gone by the time it arrives.
        if (!echo || unmounted) {
            return
        }

        subscribedChannelName = `runs.${id}`

        echo.private(subscribedChannelName).listen('.run.progress', (payload: RunProgressPayload) => {
            backlog.value = payload.backlog
            running.value = payload.status === 'running'

            if (latestRun.value && latestRun.value.id === payload.run_id) {
                latestRun.value = { ...latestRun.value, status: payload.status, stage_stats: payload.stage_stats }
            } else {
                load()
            }
        })
    }

    onUnmounted(() => {
        unmounted = true
        if (subscribedChannelName) {
            currentEcho()?.leave(subscribedChannelName)
        }
    })

    return { backlog, latestRun, pending, running, loading, load, subscribeToLiveUpdates }
}
