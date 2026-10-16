<script setup lang="ts">
import BarList from '@/shared/charts/BarList.vue'
import Card from '@/shared/components/Card.vue'
import { formatCount } from '@/shared/lib/format'
import { ratingCounts, ratings } from '@/shared/lib/ratings'
import type { SourceInsightsRow } from '../types'

defineProps<{ rows: SourceInsightsRow[] }>()

function strongShare(row: SourceInsightsRow): string {
    return row.total > 0 ? `${Math.round((row.Strong / row.total) * 100)}%` : '–'
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <Card title="Listings by source" subtitle="How each source's listings rated" :legend="ratings">
            <BarList
                v-if="rows.length"
                :rows="rows.map((r) => ({ key: r.source, label: r.source, values: ratingCounts(r) }))"
                :series="ratings"
                :format="formatCount"
            />
            <div v-else class="text-sm text-zinc-500">No listings in this range.</div>
        </Card>

        <Card v-if="rows.length" title="Sources">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500">
                            <th class="py-2 pr-4 font-medium">Source</th>
                            <th class="py-2 pr-4 text-right font-medium">Listings</th>
                            <th v-for="s in ratings" :key="s.key" class="py-2 pr-4 text-right font-medium">{{ s.label }}</th>
                            <th class="py-2 text-right font-medium">Strong share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.source" class="border-t border-white/5 text-zinc-300">
                            <td class="py-2 pr-4">{{ row.source }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ formatCount(row.total) }}</td>
                            <td v-for="s in ratings" :key="s.key" class="py-2 pr-4 text-right text-zinc-400 tabular-nums">
                                {{ formatCount(row[s.key]) }}
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ strongShare(row) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>
