/**
 * Number, money and date formatting for every screen, so the same figure
 * reads the same on the dashboard, Insights and Sites.
 */

const count = new Intl.NumberFormat()
const compact = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 })

export const formatCount = (value: number) => count.format(value)

/** Token counts get large fast: 12,345 → 12.3K from ten thousand up. */
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

/** "Oct 15", or "Oct 15, 6:00 PM" with the time. */
export function formatRunDate(iso: string, withTime = false): string {
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        ...(withTime ? { hour: 'numeric', minute: '2-digit' } : {}),
    })
}
