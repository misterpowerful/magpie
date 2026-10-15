<script setup lang="ts">
import { computed } from 'vue'
import BarList from '@/shared/charts/BarList.vue'
import ColumnChart from '@/shared/charts/ColumnChart.vue'
import { seriesColor } from '@/shared/charts/palette'
import { formatCount, formatRunDate, formatTokens, formatUsd, formatUsdTick } from '../constants'
import type { CostBreakdown, CostInsights } from '../types'
import FigureTile from './FigureTile.vue'
import InsightCard from './InsightCard.vue'

const props = defineProps<{ data: CostInsights }>()

const costSeries = [{ key: 'cost', label: 'Cost', color: seriesColor }]

/** Share of prompt input served from Claude's prompt cache. */
const cacheShare = computed(() => {
    const t = props.data.totals
    const input = t.input_tokens + t.cache_read_input_tokens + t.cache_creation_input_tokens

    return input > 0 ? Math.round((t.cache_read_input_tokens / input) * 100) : null
})

const breakdownRows = (rows: CostBreakdown[]) => rows.map((r) => ({ key: r.label, label: r.label, values: { cost: r.cost_usd } }))
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <FigureTile label="Estimated cost" :value="`≈ ${formatUsd(data.totals.cost_usd)}`" note="API-equivalent, not billed (Claude Pro)" />
            <FigureTile
                label="LLM calls"
                :value="formatCount(data.totals.calls)"
                :note="data.totals.failed_calls ? `${formatCount(data.totals.failed_calls)} failed` : 'None failed'"
            />
            <FigureTile
                label="Tokens"
                :value="`${formatTokens(data.totals.input_tokens + data.totals.cache_read_input_tokens + data.totals.cache_creation_input_tokens)} in`"
                :note="`${formatTokens(data.totals.output_tokens)} out`"
            />
            <FigureTile
                label="From prompt cache"
                :value="cacheShare === null ? '—' : `${cacheShare}%`"
                :note="`${formatTokens(data.totals.cache_read_input_tokens)} input tokens`"
            />
        </div>

        <InsightCard title="Cost per run" subtitle="API-equivalent estimate">
            <ColumnChart
                v-if="data.runs.length > 1"
                :categories="data.runs.map((r) => ({ key: r.run_id, label: formatRunDate(r.started_at), detail: `${formatCount(r.calls)} calls · ${r.trigger}` }))"
                :series="costSeries"
                :values="data.runs.map((r) => ({ cost: r.cost_usd }))"
                :format="formatUsd"
                :tick-format="formatUsdTick"
            />
            <div v-else class="py-6 text-center text-sm text-zinc-500">
                {{ data.runs.length ? 'Switch to 7 or 30 days to see the trend across runs.' : 'No runs in this range.' }}
            </div>
        </InsightCard>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <InsightCard title="By task">
                <BarList v-if="data.by_task.length" :rows="breakdownRows(data.by_task)" :series="costSeries" :format="formatUsd" />
                <div v-else class="text-sm text-zinc-500">No calls in this range.</div>
            </InsightCard>
            <InsightCard title="By model">
                <BarList v-if="data.by_model.length" :rows="breakdownRows(data.by_model)" :series="costSeries" :format="formatUsd" />
                <div v-else class="text-sm text-zinc-500">No calls in this range.</div>
            </InsightCard>
        </div>

        <InsightCard v-if="data.runs.length" title="Runs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500">
                            <th class="py-2 pr-4 font-medium">Started</th>
                            <th class="py-2 pr-4 font-medium">Trigger</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 text-right font-medium">Calls</th>
                            <th class="py-2 text-right font-medium">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="run in [...data.runs].reverse()" :key="run.run_id" class="border-t border-white/5 text-zinc-300">
                            <td class="py-2 pr-4 whitespace-nowrap">{{ formatRunDate(run.started_at, true) }}</td>
                            <td class="py-2 pr-4 text-zinc-400">{{ run.trigger }}</td>
                            <td class="py-2 pr-4 text-zinc-400">{{ run.status }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ formatCount(run.calls) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ formatUsd(run.cost_usd) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </InsightCard>
    </div>
</template>
