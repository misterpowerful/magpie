import { ref, watch, type Ref } from 'vue'
import api from '@/shared/lib/api'
import type { StatsMeta } from '@/features/stats/types'
import type { InsightsPayload, InsightsRange, InsightsResponse, InsightsTab } from '../types'

/**
 * Fetches the active tab's data whenever the tab or range changes. The
 * previous result stays in place (the view dims it) while the next one
 * loads, so switching range never flashes an empty page. A response
 * that arrives after a newer request was made is dropped.
 */
export function useInsights(tab: Ref<InsightsTab>, range: Ref<InsightsRange>) {
    const data = ref<Partial<InsightsPayload>>({})
    const meta = ref<StatsMeta | null>(null)
    const loading = ref(false)
    let latest = 0

    async function load() {
        const request = ++latest
        const current = tab.value
        loading.value = true
        try {
            const response = await api.get<InsightsResponse<InsightsPayload[typeof current]>>(`/api/insights/${current}`, {
                params: { range: range.value },
            })
            if (request !== latest) return
            data.value = { ...data.value, [current]: response.data.data }
            meta.value = response.data.meta
        } finally {
            if (request === latest) loading.value = false
        }
    }

    watch([tab, range], load, { immediate: true })

    return { data, meta, loading, reload: load }
}
