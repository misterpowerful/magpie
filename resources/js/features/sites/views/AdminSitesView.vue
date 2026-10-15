<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/shared/lib/api'
import AppHeader from '@/shared/components/AppHeader.vue'
import { siteAdapters, siteColumns, siteDrivers } from '../constants'
import { useSiteForm } from '../composables/useSiteForm'
import type { Site } from '../types'

const sites = ref<Site[]>([])
const loading = ref(false)

async function loadSites() {
    loading.value = true
    try {
        const { data } = await api.get<{ data: Site[] }>('/api/admin/sites')
        sites.value = data.data
    } finally {
        loading.value = false
    }
}

const { dialogOpen, editingId, form, errors, needsAdapter, openCreate, openEdit, save } =
    useSiteForm(loadSites)

async function remove(site: Site) {
    await api.delete(`/api/admin/sites/${site.id}`)
    await loadSites()
}

async function runNow(site: Site) {
    await api.post(`/api/admin/sites/${site.id}/run`)
    await loadSites()
}

onMounted(loadSites)
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <AppHeader />

        <q-page-container>
            <q-page class="q-pa-md">
                <div class="q-mb-md">
                    <q-btn color="primary" no-caps unelevated label="Add site" @click="openCreate" />
                </div>

                <q-table :rows="sites" :columns="siteColumns" row-key="id" :loading="loading" flat>
                    <template #body-cell-enabled="props">
                        <q-td :props="props">{{ props.row.enabled ? 'yes' : 'no' }}</q-td>
                    </template>

                    <template #body-cell-actions="props">
                        <q-td :props="props">
                            <q-btn size="sm" flat no-caps label="Run now" @click="runNow(props.row)" />
                            <q-btn size="sm" flat no-caps label="Edit" @click="openEdit(props.row)" />
                            <q-btn size="sm" flat no-caps color="negative" label="Delete" @click="remove(props.row)" />
                        </q-td>
                    </template>
                </q-table>
            </q-page>
        </q-page-container>

        <q-dialog v-model="dialogOpen">
            <q-card style="width: 420px" class="q-pa-sm">
                <q-card-section>
                    <div class="text-h6 text-zinc-100 text-weight-semibold">{{ editingId ? 'Edit site' : 'Add site' }}</div>
                </q-card-section>

                <q-card-section class="q-gutter-md">
                    <q-input v-model="form.name" label="Name" outlined dense :error="!!errors.name" :error-message="errors.name?.[0]" />
                    <q-input v-model="form.url" label="URL" outlined dense :error="!!errors.url" :error-message="errors.url?.[0]" />
                    <q-select
                        v-model="form.driver"
                        :options="siteDrivers"
                        label="Driver"
                        outlined
                        dense
                        emit-value
                        map-options
                        :options-value="(o: string) => o"
                    />
                    <q-select
                        v-if="needsAdapter"
                        v-model="form.adapter"
                        :options="siteAdapters"
                        label="Adapter"
                        outlined
                        dense
                        emit-value
                        map-options
                        :error="!!errors['config.adapter']"
                        :error-message="errors['config.adapter']?.[0]"
                    />
                    <q-toggle v-model="form.enabled" label="Enabled" />
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Cancel" v-close-popup />
                    <q-btn color="primary" no-caps unelevated label="Save" @click="save" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </q-layout>
</template>
