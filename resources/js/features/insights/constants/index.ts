import type { InsightsRange, InsightsTab } from '../types'

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
