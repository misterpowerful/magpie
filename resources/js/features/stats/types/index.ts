/** The two views each tile swipes between. The API also accepts 1d. */
export type StatsRange = 'last_run' | '7d'

export interface StatsMeta {
    range: StatsRange | '1d' | '30d'
    since: string | null
    until: string | null
    run_id: number | null
}

export interface RatingStats {
    Strong: number
    Moderate: number
    Weak: number
    Unscored: number
}

export type SourceBucket = 'email' | 'feeds' | 'browser'

export interface SourceStats {
    rows: { source: string; bucket: SourceBucket; count: number }[]
    buckets: Record<SourceBucket, number>
}

export interface UsageStats {
    calls: number
    cost_usd: number
    input_tokens: number
    output_tokens: number
}

export interface Ranged<T> {
    data: T
    meta: StatsMeta
}
