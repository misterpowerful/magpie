<script setup lang="ts">
import { computed } from 'vue'
import type { ChartSeries } from './types'

/**
 * Horizontal bars, one row per item: label, bar (optionally stacked by
 * series, 2px surface gap between segments, rounded data-end), value at
 * the tip. Each row is also its own readout on hover/focus via title,
 * and the value is always printed, so nothing hides behind hover.
 */
const props = withDefaults(
    defineProps<{
        rows: { key: string; label: string; values: Record<string, number> }[]
        series: ChartSeries[]
        format?: (value: number) => string
    }>(),
    { format: (v: number) => String(v) },
)

const totals = computed(() => props.rows.map((row) => props.series.reduce((sum, s) => sum + (row.values[s.key] ?? 0), 0)))
const max = computed(() => Math.max(0, ...totals.value) || 1)

function describe(i: number): string {
    const row = props.rows[i]
    const parts = props.series.filter((s) => props.series.length === 1 || (row.values[s.key] ?? 0) > 0).map((s) => `${s.label}: ${props.format(row.values[s.key] ?? 0)}`)

    return `${row.label} — ${parts.join(', ')}`
}
</script>

<template>
    <ul class="m-0 flex list-none flex-col gap-2 p-0">
        <li v-for="(row, i) in rows" :key="row.key" class="grid grid-cols-[minmax(0,9rem)_1fr] items-center gap-3 text-sm" :title="describe(i)">
            <span class="truncate text-zinc-400">{{ row.label }}</span>
            <!-- flex-nowrap! (important): Quasar's unlayered .flex sets flex-wrap: wrap, which beats a plain utility. -->
            <div class="flex flex-nowrap! items-center gap-2">
                <div class="flex h-4 flex-nowrap! gap-[2px]" :style="{ width: `${(totals[i] / max) * 85}%` }">
                    <div
                        v-for="s in series.filter((s) => (row.values[s.key] ?? 0) > 0)"
                        :key="s.key"
                        class="h-full last:rounded-r"
                        :style="{ flexGrow: row.values[s.key], flexBasis: 0, backgroundColor: s.color, minWidth: '2px' }"
                    />
                </div>
                <span class="shrink-0 text-zinc-300 tabular-nums">{{ format(totals[i]) }}</span>
            </div>
        </li>
    </ul>
</template>
