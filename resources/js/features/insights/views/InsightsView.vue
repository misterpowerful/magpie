<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppHeader from '@/shared/components/AppHeader.vue'
import { describeRange } from '@/features/stats/constants'
import CostTab from '../components/CostTab.vue'
import RatingsTab from '../components/RatingsTab.vue'
import SourcesTab from '../components/SourcesTab.vue'
import { useInsights } from '../composables/useInsights'
import { insightsRanges, insightsTabs } from '../constants'
import type { InsightsRange, InsightsTab } from '../types'

/**
 * One page, three tabs, one range control that scopes everything below
 * it. Tab and range live in the URL (?tab=cost&range=7d) so the
 * dashboard tiles can deep-link here and a reload keeps your place.
 */
const route = useRoute()
const router = useRouter()

function queryValue<T extends string>(key: string, allowed: readonly T[], fallback: T): T {
    const value = route.query[key]

    return allowed.includes(value as T) ? (value as T) : fallback
}

const tab = computed<InsightsTab>({
    get: () => queryValue('tab', insightsTabs.map((t) => t.value), 'cost'),
    set: (value) => router.replace({ query: { ...route.query, tab: value } }),
})

const range = computed<InsightsRange>({
    get: () => queryValue('range', insightsRanges.map((r) => r.value), '7d'),
    set: (value) => router.replace({ query: { ...route.query, range: value } }),
})

const { data, meta, loading } = useInsights(tab, range)
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <AppHeader />

        <q-page-container>
            <q-page class="q-pa-md">
                <div class="mx-auto flex max-w-6xl flex-col gap-4">
                    <q-tabs v-model="tab" dense no-caps align="left" class="text-zinc-400" active-color="white" indicator-color="accent">
                        <q-tab v-for="t in insightsTabs" :key="t.value" :name="t.value" :label="t.label" />
                    </q-tabs>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex flex-nowrap! gap-1 rounded-lg border border-white/8 bg-[var(--q-dark)] p-1" role="radiogroup" aria-label="Range">
                            <button
                                v-for="r in insightsRanges"
                                :key="r.value"
                                type="button"
                                role="radio"
                                :aria-checked="range === r.value"
                                class="cursor-pointer rounded-md border-0 px-3 py-1 font-[inherit] text-sm transition-colors"
                                :class="range === r.value ? 'bg-teal-500/15 text-teal-300' : 'bg-transparent text-zinc-400 hover:text-zinc-200'"
                                @click="range = r.value"
                            >
                                {{ r.label }}
                            </button>
                        </div>
                        <span class="text-xs text-zinc-500">{{ describeRange(meta ?? undefined) }}</span>
                    </div>

                    <div class="transition-opacity" :class="loading ? 'opacity-50' : ''">
                        <CostTab v-if="tab === 'cost' && data.cost" :data="data.cost" />
                        <SourcesTab v-else-if="tab === 'sources' && data.sources" :rows="data.sources" />
                        <RatingsTab v-else-if="tab === 'ratings' && data.ratings" :runs="data.ratings.runs" />
                    </div>
                </div>
            </q-page>
        </q-page-container>
    </q-layout>
</template>
