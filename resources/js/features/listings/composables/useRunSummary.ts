import { ref } from 'vue'
import api from '@/shared/lib/api'
import type { Run } from '../types'

export function useRunSummary() {
    const backlog = ref(0)
    const latestRun = ref<Run | null>(null)
    const loading = ref(false)

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

    return { backlog, latestRun, loading, load }
}
