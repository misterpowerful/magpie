import type { QTableColumn } from 'quasar'
import { ratingColors } from '@/shared/charts/palette'
import type { Listing } from '../types'

export const listingColumns: QTableColumn<Listing>[] = [
    { name: 'rating', label: 'Rating', field: (row) => row.score?.rating ?? '', align: 'left', sortable: true },
    { name: 'company', label: 'Company', field: 'company', align: 'left' },
    { name: 'title', label: 'Title', field: 'title', align: 'left' },
    { name: 'salary', label: 'Salary', field: (row) => row.salary?.snippet ?? '', align: 'left' },
    { name: 'remote_type', label: 'Remote', field: 'remote_type', align: 'left' },
    {
        name: 'first_seen_at',
        label: 'Date added',
        field: 'first_seen_at',
        align: 'left',
        sortable: true,
        format: (val: string) => new Date(val).toLocaleDateString(),
    },
    { name: 'flags', label: 'Flags', field: (row) => row.filter_flags ?? {}, align: 'left' },
    { name: 'status', label: 'Status', field: 'status', align: 'left' },
    { name: 'actions', label: '', field: 'id', align: 'right' },
]

/**
 * Rating badge: a dot in the rating's shade of the Insights teal ramp
 * beside a neutral label — the same dot-and-label as the chart legends.
 * The colour sits on the dot, not the text: the dark "Weak" teal is
 * too low-contrast to read as text on the dark surface.
 */
export const ratingBadge: Record<string, { label: string; color: string }> = {
    Strong: { label: 'Strong', color: ratingColors.Strong },
    Moderate: { label: 'Medium', color: ratingColors.Moderate },
    Weak: { label: 'Weak', color: ratingColors.Weak },
}

/**
 * The tab bar: a single-select on `status`. "Pending" (status=new) is
 * the default view; the scoring dropdown below applies on top of
 * whichever tab is active.
 */
export const statusTabs = [
    { label: 'Pending', value: 'new' },
    { label: 'Applied', value: 'applied' },
    { label: 'Not interested', value: 'not_interested' },
]

/** The one multi-select dropdown: rating, OR'd together when several are picked. */
export const scoringOptions = [
    { label: 'Strong', value: 'Strong' },
    { label: 'Medium', value: 'Moderate' },
    { label: 'Weak', value: 'Weak' },
    { label: 'Unscored', value: 'unscored' },
]
