<script setup lang="ts">
import { computed } from 'vue'
import { numberFormat, ratingSegments } from '../constants'
import type { RatingStats } from '../types'

/**
 * Plain-SVG donut (four segments doesn't justify a chart library), total
 * in the middle, legend with counts and shares alongside.
 */
const props = defineProps<{ stats: RatingStats | undefined }>()

const RADIUS = 40
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

const total = computed(() => ratingSegments.reduce((sum, { key }) => sum + (props.stats?.[key] ?? 0), 0))

const arcs = computed(() => {
    let offset = 0

    return ratingSegments.map((segment) => {
        const count = props.stats?.[segment.key] ?? 0
        const length = total.value > 0 ? (count / total.value) * CIRCUMFERENCE : 0
        const arc = { ...segment, count, length, offset }
        offset += length

        return arc
    })
})

function share(count: number): string {
    return total.value > 0 ? `${Math.round((count / total.value) * 100)}%` : '–'
}
</script>

<template>
    <div class="flex flex-nowrap! items-center gap-5">
        <svg viewBox="0 0 100 100" class="size-24 shrink-0" role="img" :aria-label="`${total} listings by rating`">
            <g transform="rotate(-90 50 50)">
                <circle cx="50" cy="50" :r="RADIUS" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="12" />
                <circle
                    v-for="arc in arcs.filter((a) => a.length > 0)"
                    :key="arc.key"
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    :stroke="arc.color"
                    stroke-width="12"
                    :stroke-dasharray="`${arc.length} ${CIRCUMFERENCE - arc.length}`"
                    :stroke-dashoffset="-arc.offset"
                />
            </g>
            <text
                x="50"
                y="50"
                class="fill-zinc-100 text-[22px] font-semibold"
                text-anchor="middle"
                dominant-baseline="central"
            >
                {{ numberFormat.format(total) }}
            </text>
        </svg>

        <ul class="m-0 flex min-w-0 flex-1 list-none flex-col gap-1 p-0 text-sm">
            <li v-for="arc in arcs" :key="arc.key" class="flex flex-nowrap! items-center gap-2">
                <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: arc.color }" />
                <span class="text-zinc-400">{{ arc.label }}</span>
                <span class="ml-auto tabular-nums text-zinc-100">{{ numberFormat.format(arc.count) }}</span>
                <span class="w-9 text-right tabular-nums text-xs text-zinc-500">{{ share(arc.count) }}</span>
            </li>
        </ul>
    </div>
</template>
