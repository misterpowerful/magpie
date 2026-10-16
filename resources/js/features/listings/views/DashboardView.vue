<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/shared/lib/api'
import { useAuthStore } from '@/features/auth/stores/auth'
import AppHeader from '@/shared/components/AppHeader.vue'
import StatsPanels from '@/features/stats/components/StatsPanels.vue'
import { ratingByKey, type RatingKey } from '@/shared/lib/ratings'
import { listingColumns, scoringOptions, statusTabs } from '../constants'
import { useListingStatusDialog } from '../composables/useListingStatusDialog'
import { useRunSummary } from '../composables/useRunSummary'
import type { Listing } from '../types'

const auth = useAuthStore()

const listings = ref<Listing[]>([])
const loading = ref(false)

const statusTab = ref('new')
const scoringFilter = ref<string[]>([])

function matchesScoring(listing: Listing, value: string): boolean {
    return value === 'unscored' ? !listing.score : listing.score?.rating === value
}

const filteredListings = computed(() =>
    listings.value.filter((listing) => {
        if (listing.status !== statusTab.value) return false
        if (scoringFilter.value.length > 0 && !scoringFilter.value.some((value) => matchesScoring(listing, value))) return false

        return true
    }),
)

const { backlog, latestRun, load: loadRunSummary, subscribeToLiveUpdates } = useRunSummary(() => auth.user?.id)

// Changes as a run starts, finishes each stage, and ends — the stat
// tiles re-pull on each change so they track a run while it's going.
const statsRefreshKey = computed(() =>
    latestRun.value ? `${latestRun.value.id}:${latestRun.value.status}:${Object.keys(latestRun.value.stage_stats ?? {}).length}` : '',
)

async function loadListings() {
    loading.value = true
    try {
        const { data } = await api.get<{ data: Listing[] }>('/api/listings')
        listings.value = data.data
    } finally {
        loading.value = false
    }
}

function applyUpdatedListing(updated: Listing) {
    const index = listings.value.findIndex((l) => l.id === updated.id)
    if (index !== -1) {
        listings.value[index] = updated
    }
}

const {
    dialogOpen: reasonDialog,
    pendingAction,
    open: openReasonDialog,
    confirm: confirmAction,
} = useListingStatusDialog(applyUpdatedListing)

onMounted(() => {
    loadListings()
    loadRunSummary()
    subscribeToLiveUpdates()
})
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <AppHeader />

        <q-page-container>
            <q-page class="q-pa-md">
                <div class="row q-mb-md q-gutter-sm items-center">
                    <q-chip dense class="bg-white/10 text-zinc-300" icon="hourglass_empty">
                        Backlog: {{ backlog }} waiting
                    </q-chip>
                    <q-chip
                        v-if="latestRun"
                        dense
                        :class="latestRun.status === 'done' ? 'bg-green-500/15 text-green-400' : 'bg-amber-500/15 text-amber-400'"
                    >
                        Last run ({{ latestRun.trigger }}): {{ latestRun.status }}
                    </q-chip>
                </div>

                <StatsPanels :refresh-key="statsRefreshKey" class="q-mb-lg" />

                <q-tabs
                    v-model="statusTab"
                    dense
                    no-caps
                    align="left"
                    class="q-mb-md text-zinc-400"
                    active-color="white"
                    indicator-color="accent"
                >
                    <q-tab v-for="tab in statusTabs" :key="tab.value" :name="tab.value" :label="tab.label" />
                </q-tabs>

                <div class="row q-mb-md items-center">
                    <q-select
                        v-model="scoringFilter"
                        :options="scoringOptions"
                        option-value="value"
                        option-label="label"
                        emit-value
                        map-options
                        multiple
                        use-chips
                        dense
                        outlined
                        style="min-width: 240px"
                        label="Scoring"
                    />
                </div>

                <q-table :rows="filteredListings" :columns="listingColumns" row-key="id" :loading="loading" flat>
                    <template #body-cell-rating="props">
                        <q-td :props="props">
                            <span
                                v-if="props.row.score"
                                class="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-zinc-200"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :style="{ backgroundColor: ratingByKey[props.row.score.rating as RatingKey]?.color }"
                                />
                                {{ ratingByKey[props.row.score.rating as RatingKey]?.label ?? props.row.score.rating }}
                            </span>
                            <span v-else class="text-zinc-400">—</span>
                        </q-td>
                    </template>

                    <template #body-cell-salary="props">
                        <q-td :props="props">
                            <span v-if="props.row.salary?.snippet">{{ props.row.salary.snippet }}</span>
                            <span v-else class="text-zinc-400">unknown</span>
                        </q-td>
                    </template>

                    <template #body-cell-flags="props">
                        <q-td :props="props">
                            <span
                                v-for="(enabled, flag) in props.row.filter_flags"
                                v-show="enabled"
                                :key="flag"
                                class="mr-1 inline-block rounded-full bg-white/10 px-2 py-0.5 text-xs text-zinc-300"
                            >
                                {{ flag }}
                            </span>
                        </q-td>
                    </template>

                    <template #body-cell-title="props">
                        <q-td :props="props">
                            <a :href="props.row.canonical_url" target="_blank" rel="noopener" class="text-teal-400 hover:underline">{{
                                props.row.title
                            }}</a>
                            <q-tooltip v-if="props.row.score?.commentary" max-width="400px">
                                {{ props.row.score.commentary }}
                            </q-tooltip>
                        </q-td>
                    </template>

                    <template #body-cell-actions="props">
                        <q-td :props="props">
                            <q-btn
                                v-if="props.row.status !== 'applied'"
                                size="sm"
                                no-caps
                                unelevated
                                color="positive"
                                label="Applied"
                                @click="openReasonDialog(props.row, 'applied')"
                            />
                            <q-btn
                                v-if="props.row.status !== 'not_interested'"
                                size="sm"
                                no-caps
                                outline
                                color="negative"
                                label="Not interested"
                                @click="openReasonDialog(props.row, 'not_interested')"
                            />
                        </q-td>
                    </template>
                </q-table>
            </q-page>
        </q-page-container>

        <q-dialog v-model="reasonDialog">
            <q-card style="width: 360px">
                <q-card-section>
                    <div class="text-h6">
                        Mark as {{ pendingAction.status === 'applied' ? 'applied' : 'not interested' }}
                    </div>
                </q-card-section>

                <q-card-section>
                    <q-input v-model="pendingAction.reason" label="Reason (optional)" type="textarea" outlined autofocus />
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Cancel" v-close-popup />
                    <q-btn color="primary" no-caps unelevated label="Confirm" @click="confirmAction" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </q-layout>
</template>
