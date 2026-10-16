import { formatRunDate } from '@/shared/lib/format'
import type { StatsMeta, StatsRange } from '../types'

/** The ranges a dashboard tile swipes between, in swipe order. */
export const tileRanges: { value: StatsRange; label: string }[] = [
    { value: 'last_run', label: 'Last run' },
    { value: '7d', label: '7 days' },
]

/** The tile's small sub-line: when the range covers, or why it's empty. */
export function describeRange(meta: StatsMeta | undefined): string {
    if (!meta) return ''

    if (meta.range === 'last_run') {
        if (!meta.since) return 'No runs yet'

        return formatRunDate(meta.since, true)
    }

    return { '1d': 'Past 24 hours', '7d': 'Past 7 days', '30d': 'Past 30 days' }[meta.range]
}
