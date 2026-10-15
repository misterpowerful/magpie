import type { RatingStats, StatsMeta, StatsRange } from '../types'

export const rangeLabels: Record<StatsRange, string> = {
    last_run: 'Last run',
    '7d': '7 days',
}

/**
 * Same hues as the listings table's ratingBadgeClass, so the donut's
 * legend reads as the same thing as the badges below it.
 */
export const ratingSegments: { key: keyof RatingStats; label: string; color: string }[] = [
    { key: 'Strong', label: 'Strong', color: '#4ade80' },
    { key: 'Moderate', label: 'Medium', color: '#fbbf24' },
    { key: 'Weak', label: 'Weak', color: '#a1a1aa' },
    { key: 'Unscored', label: 'Unscored', color: '#3f3f46' },
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

    return meta.range === '7d' ? 'Past 7 days' : 'Past 24 hours'
}

export const numberFormat = new Intl.NumberFormat()

export const usdFormat = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2 })
