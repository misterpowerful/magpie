import { onUnmounted, ref } from 'vue'
import api from '@/shared/lib/api'
import { getEcho } from '@/shared/lib/echo'
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
    const latestRun = ref<Run | null>(null)
    const loading = ref(false)
    let subscribedChannelName: string | null = null

    async function load() {
        loading.value = true
        try {
            const { data } = await api.get<{ data: Run[]; backlog: number }>('/api/runs')
            latestRun.value = data.data[0] ?? null
            backlog.value = data.backlog
        } finally {
            loading.value = false
        }
    }

    function subscribeToLiveUpdates() {
        const id = userId()
        const echo = getEcho()

        if (!echo || !id) {
            return
        }

        subscribedChannelName = `runs.${id}`

        echo.private(subscribedChannelName).listen('.run.progress', (payload: RunProgressPayload) => {
            backlog.value = payload.backlog

            if (latestRun.value && latestRun.value.id === payload.run_id) {
                latestRun.value = { ...latestRun.value, status: payload.status, stage_stats: payload.stage_stats }
            } else {
                load()
            }
        })
    }

    onUnmounted(() => {
        if (subscribedChannelName) {
            getEcho()?.leave(subscribedChannelName)
        }
    })

    return { backlog, latestRun, loading, load, subscribeToLiveUpdates }
}
