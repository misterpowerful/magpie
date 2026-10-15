<script setup lang="ts">
import { onMounted, reactive, watch } from 'vue'
import { useDashboardStats } from '../composables/useDashboardStats'
import { describeRange, numberFormat, usdFormat } from '../constants'
import type { StatsRange } from '../types'
import RatingDonut from './RatingDonut.vue'
import StatTile from './StatTile.vue'

/**
 * The dashboard's row of stat tiles. Each tile keeps its own range.
 * `refreshKey` changes whenever a run makes progress (the dashboard
 * feeds it from the live run summary), which re-pulls the numbers.
 */
const props = defineProps<{ refreshKey?: string }>()

const { ratings, sources, usage, load } = useDashboardStats()

const range = reactive<Record<'ratings' | 'email' | 'feeds' | 'browser' | 'cost', StatsRange>>({
    ratings: 'last_run',
    email: 'last_run',
    feeds: 'last_run',
    browser: 'last_run',
    cost: 'last_run',
})

function topFeeds(r: StatsRange) {
    return (sources.value[r]?.data.rows ?? []).filter((row) => row.bucket === 'feeds').slice(0, 3)
}

/** Tiles open the matching Insights tab, keeping the range they show. */
function insights(tab: string, r: StatsRange) {
    return { name: 'insights', query: { tab, range: r } }
}

onMounted(load)
watch(() => props.refreshKey, load)
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <StatTile v-model:range="range.ratings" title="Listings pulled in" icon="donut_large" :to="insights('ratings', range.ratings)" class="sm:col-span-2 lg:col-span-2">
            <template #default="{ range: r }">
                <RatingDonut :stats="ratings[r]?.data" />
                <div class="mt-2 text-xs text-zinc-500">{{ describeRange(ratings[r]?.meta) }}</div>
            </template>
        </StatTile>

        <StatTile v-model:range="range.email" title="Email" icon="mail_outline" :to="insights('sources', range.email)">
            <template #default="{ range: r }">
                <div class="text-3xl font-semibold text-zinc-100">
                    {{ numberFormat.format(sources[r]?.data.buckets.email ?? 0) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500">{{ describeRange(sources[r]?.meta) }}</div>
            </template>
        </StatTile>

        <StatTile v-model:range="range.feeds" title="API / feeds / other" icon="rss_feed" :to="insights('sources', range.feeds)">
            <template #default="{ range: r }">
                <div class="text-3xl font-semibold text-zinc-100">
                    {{ numberFormat.format(sources[r]?.data.buckets.feeds ?? 0) }}
                </div>
                <ul v-if="topFeeds(r).length" class="m-0 mt-1 flex list-none flex-col p-0 text-xs">
                    <li v-for="row in topFeeds(r)" :key="row.source" class="flex flex-nowrap! justify-between gap-2">
                        <span class="truncate text-zinc-400">{{ row.source }}</span>
                        <span class="tabular-nums text-zinc-300">{{ row.count }}</span>
                    </li>
                </ul>
                <div v-else class="mt-1 text-xs text-zinc-500">{{ describeRange(sources[r]?.meta) }}</div>
            </template>
        </StatTile>

        <StatTile v-model:range="range.browser" title="Claude in Chrome" icon="language" disabled>
            <div class="text-3xl font-semibold text-zinc-600">—</div>
            <div class="mt-1 text-xs text-zinc-500">Not yet available</div>
        </StatTile>

        <StatTile v-model:range="range.cost" title="Estimated cost" icon="payments" :to="insights('cost', range.cost)">
            <template #default="{ range: r }">
                <div class="text-3xl font-semibold text-zinc-100">
                    <span class="mr-1 text-zinc-500">≈</span>{{ usdFormat.format(usage[r]?.data.cost_usd ?? 0) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500">
                    {{ numberFormat.format(usage[r]?.data.calls ?? 0) }} calls · API-equivalent
                </div>
                <div class="text-xs text-zinc-500">{{ describeRange(usage[r]?.meta) }}</div>
            </template>
        </StatTile>
    </div>
</template>
