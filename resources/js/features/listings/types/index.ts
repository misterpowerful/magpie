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
}
