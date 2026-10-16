import type { QTableColumn } from 'quasar'
import { ratings } from '@/shared/lib/ratings'
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
 * The tab bar: a single-select on `status`. "Pending" (status=new) is
 * the default view; the scoring dropdown below applies on top of
 * whichever tab is active.
 */
export const statusTabs = [
    { label: 'Pending', value: 'new' },
    { label: 'Applied', value: 'applied' },
    { label: 'Not interested', value: 'not_interested' },
]

/**
 * The one multi-select dropdown: rating, OR'd together when several are
 * picked. "unscored" (lowercase) is the filter's own value for listings
 * with no score, matched by DashboardView, not a rating the API returns.
 */
export const scoringOptions = ratings.map(({ key, label }) => ({ label, value: key === 'Unscored' ? 'unscored' : key }))
