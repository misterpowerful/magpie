/**
 * Chart colours, validated with the dataviz palette checker against the
 * dark card surface (#111822).
 *
 * Ratings are ordinal (Strong > Medium > Weak), so they're one teal hue
 * stepping in lightness: brightest = best. Unscored is off the scale, so
 * it's a neutral grey rather than a fourth teal.
 */
export const ratingColors = {
    Strong: '#5eead4',
    Moderate: '#14b8a6',
    Weak: '#0f766e',
    Unscored: '#52525b',
} as const

/** Single-series marks (cost bars etc.) — the brand teal. */
export const seriesColor = '#14b8a6'

export const chartChrome = {
    grid: 'rgba(255, 255, 255, 0.06)',
    axisText: '#71717a',
    surface: '#111822',
}
