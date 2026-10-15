import { ref } from 'vue'
import api from '@/shared/lib/api'
import type { RatingStats, Ranged, SourceBucket, SourceStats, StatsMeta, StatsRange, UsageStats } from '../types'

type ByRange<T> = Partial<Record<StatsRange, Ranged<T>>>

const RANGES: StatsRange[] = ['last_run', '7d']

/**
 * Loads every dashboard tile's stats for both ranges up front, so
 * swiping a tile between "Last run" and "7 days" is instant rather than
 * a fetch per swipe. Six small requests, run in parallel.
 */
export function useDashboardStats() {
    const ratings = ref<ByRange<RatingStats>>({})
    const sources = ref<ByRange<SourceStats>>({})
    const usage = ref<ByRange<UsageStats>>({})
    const loading = ref(false)

    async function load() {
        loading.value = true
        try {
            await Promise.all(
                RANGES.flatMap((range) => [
                    api
                        .get<Ranged<RatingStats>>('/api/stats/ratings', { params: { range } })
                        .then(({ data }) => (ratings.value[range] = data)),
                    api
                        .get<{ data: SourceStats['rows']; buckets: Record<SourceBucket, number>; meta: StatsMeta }>(
                            '/api/stats/sources',
                            { params: { range } },
                        )
                        .then(({ data }) => (sources.value[range] = { data: { rows: data.data, buckets: data.buckets }, meta: data.meta })),
                    api
                        .get<Ranged<UsageStats>>('/api/stats/usage', { params: { range } })
                        .then(({ data }) => (usage.value[range] = data)),
                ]),
            )
        } finally {
            loading.value = false
        }
    }

    return { ratings, sources, usage, loading, load }
}
