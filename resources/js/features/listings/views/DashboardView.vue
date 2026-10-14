<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/shared/lib/api'
import { useAuthStore } from '@/features/auth/stores/auth'
import { listingColumns } from '../constants'
import { useListingStatusDialog } from '../composables/useListingStatusDialog'
import { useRunSummary } from '../composables/useRunSummary'
import type { Listing } from '../types'

const auth = useAuthStore()
const router = useRouter()

const listings = ref<Listing[]>([])
const loading = ref(false)

const { backlog, latestRun, load: loadRunSummary } = useRunSummary()

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

async function logout() {
    await auth.logout()
    window.location.href = '/login'
}

onMounted(() => {
    loadListings()
    loadRunSummary()
})
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <q-header class="bg-primary text-white">
            <q-toolbar>
                <q-toolbar-title>Magpie 2.0</q-toolbar-title>
                <q-btn flat label="Sites" @click="router.push({ name: 'admin-sites' })" />
                <q-btn flat label="Log out" @click="logout" />
            </q-toolbar>
        </q-header>

        <q-page-container>
            <q-page class="q-pa-md">
                <div class="row q-mb-md q-gutter-md items-center">
                    <q-chip color="info" text-color="white" icon="hourglass_empty">
                        Backlog: {{ backlog }} waiting
                    </q-chip>
                    <q-chip v-if="latestRun" :color="latestRun.status === 'done' ? 'positive' : 'warning'" text-color="white">
                        Last run ({{ latestRun.trigger }}): {{ latestRun.status }}
                    </q-chip>
                </div>

                <q-table
                    :rows="listings"
                    :columns="listingColumns"
                    row-key="id"
                    :loading="loading"
                    flat
                    bordered
                >
                    <template #body-cell-title="props">
                        <q-td :props="props">
                            <a :href="props.row.canonical_url" target="_blank" rel="noopener">{{ props.row.title }}</a>
                        </q-td>
                    </template>

                    <template #body-cell-actions="props">
                        <q-td :props="props">
                            <q-btn
                                v-if="props.row.status !== 'applied'"
                                size="sm"
                                color="positive"
                                label="Applied"
                                @click="openReasonDialog(props.row, 'applied')"
                            />
                            <q-btn
                                v-if="props.row.status !== 'not_interested'"
                                size="sm"
                                color="negative"
                                flat
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
                    <q-input v-model="pendingAction.reason" label="Reason (optional)" type="textarea" autofocus />
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat label="Cancel" v-close-popup />
                    <q-btn color="primary" label="Confirm" @click="confirmAction" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </q-layout>
</template>
