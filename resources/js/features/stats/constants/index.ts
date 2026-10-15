import { ratingColors } from '@/shared/charts/palette'
import type { RatingStats, StatsMeta, StatsRange } from '../types'

export const rangeLabels: Record<StatsRange, string> = {
    last_run: 'Last run',
    '7d': '7 days',
}

/** Same ordinal teal ramp as the Insights charts (see shared/charts/palette). */
export const ratingSegments: { key: keyof RatingStats; label: string; color: string }[] = [
    { key: 'Strong', label: 'Strong', color: ratingColors.Strong },
    { key: 'Moderate', label: 'Medium', color: ratingColors.Moderate },
    { key: 'Weak', label: 'Weak', color: ratingColors.Weak },
    { key: 'Unscored', label: 'Unscored', color: ratingColors.Unscored },
]

/** The tile's small sub-line: when the range covers, or why it's empty. */
export function describeRange(meta: StatsMeta | undefined): string {
    if (!meta) return ''

    if (meta.range === 'last_run') {
        if (!meta.since) return 'No runs yet'

        return new Date(meta.since).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        })
    }

    return { '1d': 'Past 24 hours', '7d': 'Past 7 days', '30d': 'Past 30 days' }[meta.range]
}

export const numberFormat = new Intl.NumberFormat()

export const usdFormat = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2 })
