<script setup lang="ts">
import { computed } from 'vue'
import BarList from '@/shared/charts/BarList.vue'
import ColumnChart from '@/shared/charts/ColumnChart.vue'
import Card from '@/shared/components/Card.vue'
import { formatCount, formatRunDate } from '@/shared/lib/format'
import { ratingCounts, ratings } from '@/shared/lib/ratings'
import type { RatingRun } from '../types'

const props = defineProps<{ runs: RatingRun[] }>()

/** With a single run there's no trend: show its mix as plain bars instead. */
const single = computed(() => (props.runs.length === 1 ? props.runs[0] : null))

const total = (run: RatingRun) => ratings.reduce((sum, s) => sum + run[s.key], 0)
</script>

<template>
    <div class="flex flex-col gap-3">
        <Card
            :title="single ? 'Rating mix' : 'Rating mix per run'"
            :subtitle="single ? formatRunDate(single.started_at, true) : 'Listings each run pulled in'"
            :legend="single ? undefined : ratings"
        >
            <BarList
                v-if="single"
                :rows="ratings.map((s) => ({ key: s.key, label: s.label, values: { [s.key]: single![s.key] } }))"
                :series="ratings"
                :format="formatCount"
            />
            <ColumnChart
                v-else-if="runs.length"
                :categories="runs.map((r) => ({ key: r.run_id, label: formatRunDate(r.started_at) }))"
                :series="ratings"
                :values="runs.map(ratingCounts)"
                :format="formatCount"
            />
            <div v-else class="py-6 text-center text-sm text-zinc-500">No runs in this range.</div>
        </Card>

        <Card v-if="runs.length > 1" title="Runs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500">
                            <th class="py-2 pr-4 font-medium">Started</th>
                            <th class="py-2 pr-4 text-right font-medium">Listings</th>
                            <th v-for="s in ratings" :key="s.key" class="py-2 pr-4 text-right font-medium last:pr-0">{{ s.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="run in [...runs].reverse()" :key="run.run_id" class="border-t border-white/5 text-zinc-300">
                            <td class="py-2 pr-4 whitespace-nowrap">{{ formatRunDate(run.started_at, true) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ formatCount(total(run)) }}</td>
                            <td v-for="s in ratings" :key="s.key" class="py-2 pr-4 text-right text-zinc-400 tabular-nums last:pr-0">
                                {{ formatCount(run[s.key]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>
