import type { StatsMeta } from '@/features/stats/types'
import type { RatingKey } from '@/shared/lib/ratings'

export type InsightsTab = 'cost' | 'sources' | 'ratings'

export type InsightsRange = 'last_run' | '7d' | '30d'

export interface CostRun {
    run_id: number
    started_at: string
    trigger: string
    status: string
    calls: number
    cost_usd: number
}

export interface CostBreakdown {
    label: string
    calls: number
    cost_usd: number
}

export interface CostInsights {
    totals: {
        calls: number
        failed_calls: number
        cost_usd: number
        input_tokens: number
        output_tokens: number
        cache_read_input_tokens: number
        cache_creation_input_tokens: number
    }
    runs: CostRun[]
    by_task: CostBreakdown[]
    by_model: CostBreakdown[]
}

export type SourceInsightsRow = { source: string; bucket: string; total: number } & Record<RatingKey, number>

export type RatingRun = { run_id: number; started_at: string } & Record<RatingKey, number>

export interface InsightsPayload {
    cost: CostInsights
    sources: SourceInsightsRow[]
    ratings: { runs: RatingRun[] }
}

export interface InsightsResponse<T> {
    data: T
    meta: StatsMeta
}
