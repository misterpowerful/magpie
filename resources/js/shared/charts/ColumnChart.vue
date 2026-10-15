<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { chartChrome } from './palette'
import type { ChartSeries } from './types'

/**
 * Vertical columns, one per category, optionally stacked by series.
 * Follows the dataviz mark specs: <=24px columns, 4px rounded top,
 * square at the baseline, 2px surface gap between stacked segments,
 * hairline grid, and a per-column hover/focus tooltip listing every
 * series. No legend here — the caller renders one for >= 2 series.
 */
const props = withDefaults(
    defineProps<{
        categories: { key: string | number; label: string; detail?: string }[]
        series: ChartSeries[]
        /** values[i][series.key] for categories[i] */
        values: Record<string, number>[]
        format?: (value: number) => string
        /** Y-axis labels; defaults to `format`. */
        tickFormat?: (value: number) => string
        height?: number
    }>(),
    { format: (v: number) => String(v), tickFormat: undefined, height: 220 },
)

const PAD = { top: 12, right: 8, bottom: 28, left: 52 }
const BAR_MAX = 24
const GAP = 2
const RADIUS = 4

const root = ref<HTMLElement | null>(null)
const width = ref(600)
let observer: ResizeObserver | null = null

onMounted(() => {
    observer = new ResizeObserver(([entry]) => (width.value = entry.contentRect.width))
    if (root.value) observer.observe(root.value)
})
onBeforeUnmount(() => observer?.disconnect())

const formatTick = (value: number) => (props.tickFormat ?? props.format)(value)

const plotW = computed(() => Math.max(0, width.value - PAD.left - PAD.right))
const plotH = computed(() => props.height - PAD.top - PAD.bottom)

const totals = computed(() => props.values.map((v) => props.series.reduce((sum, s) => sum + (v[s.key] ?? 0), 0)))

/** 1/2/5-step "nice" axis max with four intervals. */
const ticks = computed(() => {
    const max = Math.max(0, ...totals.value)
    if (max === 0) return [0, 1]
    const rough = max / 4
    const magnitude = 10 ** Math.floor(Math.log10(rough))
    const step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= rough) ?? rough

    return Array.from({ length: Math.ceil(max / step) + 1 }, (_, i) => i * step)
})
const yMax = computed(() => ticks.value[ticks.value.length - 1] || 1)
const y = (value: number) => PAD.top + plotH.value - (value / yMax.value) * plotH.value

const band = computed(() => (props.categories.length ? plotW.value / props.categories.length : 0))
const barW = computed(() => Math.min(BAR_MAX, band.value * 0.6))

/** Show at most ~8 x labels so they never collide. */
const labelEvery = computed(() => Math.max(1, Math.ceil(props.categories.length / Math.max(1, Math.floor(plotW.value / 72)))))

function topRoundedRect(x: number, top: number, w: number, h: number): string {
    const r = Math.min(RADIUS, w / 2, h)
    const bottom = top + h

    return `M${x},${bottom} V${top + r} Q${x},${top} ${x + r},${top} H${x + w - r} Q${x + w},${top} ${x + w},${top + r} V${bottom} Z`
}

const columns = computed(() =>
    props.categories.map((category, i) => {
        const cx = PAD.left + band.value * i + band.value / 2
        const x = cx - barW.value / 2
        let base = 0
        const drawn = props.series.filter((s) => (props.values[i]?.[s.key] ?? 0) > 0)

        const segments = drawn.map((s, j) => {
            const value = props.values[i][s.key]
            const bottomPx = y(base)
            const topPx = y(base + value)
            base += value
            const isTop = j === drawn.length - 1
            // Each segment gives up 2px at its top as the surface gap,
            // except the top one, which carries the rounded data-end.
            const h = Math.max(0, bottomPx - topPx - (isTop ? 0 : GAP))

            return { key: s.key, color: s.color, path: isTop ? topRoundedRect(x, topPx, barW.value, h) : `M${x},${topPx + GAP} h${barW.value} v${h} h${-barW.value} Z` }
        })

        return { category, i, cx, segments }
    }),
)

const hovered = ref<number | null>(null)
const tooltip = computed(() => {
    if (hovered.value === null) return null
    const i = hovered.value
    const left = PAD.left + band.value * i + band.value / 2

    return {
        category: props.categories[i],
        rows: props.series.map((s) => ({ ...s, value: props.values[i]?.[s.key] ?? 0 })),
        total: totals.value[i],
        left: Math.min(Math.max(left, 90), width.value - 90),
    }
})
</script>

<template>
    <div ref="root" class="relative w-full min-w-0" @pointerleave="hovered = null">
        <!-- width="100%", not the measured px: the container sizes the chart, never the reverse. -->
        <svg width="100%" :height="height" class="block overflow-visible">
            <g>
                <template v-for="tick in ticks" :key="tick">
                    <line :x1="PAD.left" :x2="PAD.left + plotW" :y1="y(tick)" :y2="y(tick)" :stroke="chartChrome.grid" stroke-width="1" />
                    <text :x="PAD.left - 8" :y="y(tick)" text-anchor="end" dominant-baseline="central" class="text-[11px] tabular-nums" :fill="chartChrome.axisText">
                        {{ formatTick(tick) }}
                    </text>
                </template>
            </g>

            <g v-for="col in columns" :key="col.category.key">
                <path v-for="seg in col.segments" :key="seg.key" :d="seg.path" :fill="seg.color" :opacity="hovered === null || hovered === col.i ? 1 : 0.55" />
                <text
                    v-if="col.i % labelEvery === 0"
                    :x="col.cx"
                    :y="PAD.top + plotH + 18"
                    text-anchor="middle"
                    class="text-[11px]"
                    :fill="chartChrome.axisText"
                >
                    {{ col.category.label }}
                </text>
                <!-- Hit target: the whole band, not just the painted column. -->
                <rect
                    :x="PAD.left + band * col.i"
                    :y="PAD.top"
                    :width="band"
                    :height="plotH"
                    fill="transparent"
                    tabindex="0"
                    class="outline-none"
                    :aria-label="`${col.category.label}: ${format(totals[col.i])}`"
                    @pointerenter="hovered = col.i"
                    @focus="hovered = col.i"
                    @blur="hovered = null"
                />
            </g>
        </svg>

        <div
            v-if="tooltip"
            class="pointer-events-none absolute top-0 z-10 min-w-36 -translate-x-1/2 rounded-lg border border-white/10 bg-[#0a0f16] px-3 py-2 text-xs shadow-lg"
            :style="{ left: `${tooltip.left}px` }"
        >
            <div class="mb-1 text-zinc-400">
                {{ tooltip.category.label }}<span v-if="tooltip.category.detail" class="text-zinc-500"> · {{ tooltip.category.detail }}</span>
            </div>
            <div v-for="row in tooltip.rows" :key="row.key" class="flex flex-nowrap! items-center gap-2">
                <span class="h-0.5 w-3 rounded" :style="{ backgroundColor: row.color }" />
                <span class="font-semibold text-zinc-100 tabular-nums">{{ format(row.value) }}</span>
                <span class="text-zinc-500">{{ row.label }}</span>
            </div>
            <div v-if="tooltip.rows.length > 1" class="mt-1 border-t border-white/10 pt-1 text-zinc-400">
                Total <span class="font-semibold text-zinc-100 tabular-nums">{{ format(tooltip.total) }}</span>
            </div>
        </div>
    </div>
</template>
