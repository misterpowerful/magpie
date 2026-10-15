import { ratingColors } from '@/shared/charts/palette'
import type { ChartSeries } from '@/shared/charts/types'
import type { InsightsRange, InsightsTab, RatingKey } from '../types'

export const insightsTabs: { value: InsightsTab; label: string }[] = [
    { value: 'cost', label: 'Cost' },
    { value: 'sources', label: 'Sources' },
    { value: 'ratings', label: 'Ratings' },
]

export const insightsRanges: { value: InsightsRange; label: string }[] = [
    { value: 'last_run', label: 'Last run' },
    { value: '7d', label: '7 days' },
    { value: '30d', label: '30 days' },
]

export const ratingSeries: (ChartSeries & { key: RatingKey })[] = [
    { key: 'Strong', label: 'Strong', color: ratingColors.Strong },
    { key: 'Moderate', label: 'Medium', color: ratingColors.Moderate },
    { key: 'Weak', label: 'Weak', color: ratingColors.Weak },
    { key: 'Unscored', label: 'Unscored', color: ratingColors.Unscored },
]

/** Just the four rating counts off a row, in the shape the charts take. */
export function ratingValues(row: Record<RatingKey, number>): Record<RatingKey, number> {
    return { Strong: row.Strong, Moderate: row.Moderate, Weak: row.Weak, Unscored: row.Unscored }
}

const count = new Intl.NumberFormat()
const compact = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 })

export const formatCount = (value: number) => count.format(value)

export const formatTokens = (value: number) => (value >= 10_000 ? compact.format(value) : count.format(value))

/** Sub-dollar costs get a third decimal so a $0.004 call isn't "$0.00". */
export function formatUsd(value: number): string {
    const digits = value !== 0 && Math.abs(value) < 1 ? 3 : 2

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(value)
}

/** Axis ticks are round numbers: two decimals is always enough. */
export const formatUsdTick = (value: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)

export function formatRunDate(iso: string, withTime = false): string {
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        ...(withTime ? { hour: 'numeric', minute: '2-digit' } : {}),
    })
}
