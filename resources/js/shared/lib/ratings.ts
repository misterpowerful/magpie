import { ratingColors } from '@/shared/charts/palette'

/** The scorer's ratings plus "not scored yet", as the API keys them. */
export type RatingKey = 'Strong' | 'Moderate' | 'Weak' | 'Unscored'

export interface RatingDefinition {
    key: RatingKey
    /** What people see: the scorer says "Moderate", the app says "Medium". */
    label: string
    color: string
}

/**
 * Best first. The one place a rating's label and colour are defined —
 * the dashboard donut, Insights charts, table badges and the scoring
 * filter all read from here.
 */
export const ratings: RatingDefinition[] = [
    { key: 'Strong', label: 'Strong', color: ratingColors.Strong },
    { key: 'Moderate', label: 'Medium', color: ratingColors.Moderate },
    { key: 'Weak', label: 'Weak', color: ratingColors.Weak },
    { key: 'Unscored', label: 'Unscored', color: ratingColors.Unscored },
]

export const ratingByKey = Object.fromEntries(ratings.map((r) => [r.key, r])) as Record<RatingKey, RatingDefinition>

/** Just the four rating counts off a row, in the shape the charts take. */
export function ratingCounts(row: Record<RatingKey, number>): Record<RatingKey, number> {
    return { Strong: row.Strong, Moderate: row.Moderate, Weak: row.Weak, Unscored: row.Unscored }
}
