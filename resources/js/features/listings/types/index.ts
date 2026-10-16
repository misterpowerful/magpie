export type ListingState =
    | 'new'
    | 'fetched'
    | 'filtered_out'
    | 'filtered_in'
    | 'scored'
    | 'enrichment_failed'
    | 'needs_browser'

export type ListingStatus = 'new' | 'applied' | 'not_interested'

export type RemoteType = 'remote' | 'hybrid' | 'onsite'

export type Rating = 'Strong' | 'Moderate' | 'Weak'

export interface ListingScoreSummary {
    rating: Rating
    reasons: string[]
    commentary: string | null
}

export interface ListingSalarySummary {
    tiers: Array<{ low?: number; high?: number; period?: string; type?: string; label?: string }>
    snippet: string | null
    status: 'pass' | 'fail' | 'unknown'
    tiered: boolean
    parse_confidence: string
}

export interface Listing {
    id: number
    canonical_url: string
    company: string | null
    title: string
    location_text: string | null
    remote_type: RemoteType | null
    state: ListingState
    status: ListingStatus
    status_reason: string | null
    applied_at: string | null
    first_seen_at: string
    filter_reason: string | null
    filter_flags: Record<string, boolean> | null
    score: ListingScoreSummary | null
    salary: ListingSalarySummary | null
    sources: string[]
}

export type RunTrigger = 'schedule' | 'catchup' | 'manual'

export type RunStatus = 'running' | 'done' | 'paused_time' | 'paused_rate_limited' | 'failed'

export interface Run {
    id: number
    trigger: RunTrigger
    status: RunStatus
    started_at: string
    ended_at: string | null
    stage_stats: Record<string, unknown> | null
}
