<script setup lang="ts">
import type { ChartSeries } from '@/shared/charts/types'

/**
 * The app's surface: rounded, hairline border, card background. Optional
 * title/subtitle header, and a legend for charts with two or more series
 * (a one-series chart is named by its title). Attributes and listeners
 * fall through to the root, so a card can also be a link or button.
 */
withDefaults(
    defineProps<{
        title?: string
        subtitle?: string
        legend?: ChartSeries[]
        /** Space between header and body: sm for compact stat tiles. */
        gap?: 'sm' | 'md'
    }>(),
    { title: undefined, subtitle: undefined, legend: undefined, gap: 'md' },
)
</script>

<template>
    <section
        class="flex flex-col rounded-xl border border-white/8 bg-[var(--q-dark)] p-4"
        :class="gap === 'sm' ? 'gap-1' : 'gap-3'"
    >
        <header v-if="title || $slots.header" class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
            <slot name="header">
                <div>
                    <!-- Not an <h2>: Quasar's unlayered heading styles would override these classes. -->
                    <div role="heading" aria-level="2" class="text-sm leading-5 font-medium text-zinc-200">{{ title }}</div>
                    <div v-if="subtitle" class="text-xs text-zinc-500">{{ subtitle }}</div>
                </div>
                <ul v-if="legend && legend.length > 1" class="m-0 flex list-none flex-wrap gap-x-4 gap-y-1 p-0 text-xs text-zinc-400">
                    <li v-for="s in legend" :key="s.key" class="flex flex-nowrap! items-center gap-1.5">
                        <span class="size-2.5 rounded-sm" :style="{ backgroundColor: s.color }" />
                        {{ s.label }}
                    </li>
                </ul>
            </slot>
        </header>
        <slot />
    </section>
</template>
