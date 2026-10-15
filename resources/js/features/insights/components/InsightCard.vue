<script setup lang="ts">
import type { ChartSeries } from '@/shared/charts/types'

/** Card shell for one chart or table: title, optional subtitle and legend. */
defineProps<{
    title: string
    subtitle?: string
    /** Shown for >= 2 series; a one-series chart is named by its title. */
    legend?: ChartSeries[]
}>()
</script>

<template>
    <section class="flex flex-col gap-4 rounded-xl border border-white/8 bg-[var(--q-dark)] p-4">
        <header class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
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
        </header>
        <slot />
    </section>
</template>
