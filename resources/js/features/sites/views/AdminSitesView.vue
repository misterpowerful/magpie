<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/shared/lib/api'
import { siteAdapters, siteColumns, siteDrivers } from '../constants'
import { useSiteForm } from '../composables/useSiteForm'
import type { Site } from '../types'

const router = useRouter()

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
        <q-header class="bg-primary text-white">
            <q-toolbar>
                <q-toolbar-title>Magpie 2.0 — Sites</q-toolbar-title>
                <q-btn flat label="Dashboard" @click="router.push({ name: 'dashboard' })" />
            </q-toolbar>
        </q-header>

        <q-page-container>
            <q-page class="q-pa-md">
                <div class="q-mb-md">
                    <q-btn color="primary" label="Add site" @click="openCreate" />
                </div>

                <q-table :rows="sites" :columns="siteColumns" row-key="id" :loading="loading" flat bordered>
                    <template #body-cell-enabled="props">
                        <q-td :props="props">{{ props.row.enabled ? 'yes' : 'no' }}</q-td>
                    </template>

                    <template #body-cell-actions="props">
                        <q-td :props="props">
                            <q-btn size="sm" flat label="Run now" @click="runNow(props.row)" />
                            <q-btn size="sm" flat label="Edit" @click="openEdit(props.row)" />
                            <q-btn size="sm" flat color="negative" label="Delete" @click="remove(props.row)" />
                        </q-td>
                    </template>
                </q-table>
            </q-page>
        </q-page-container>

        <q-dialog v-model="dialogOpen">
            <q-card style="width: 420px" class="q-pa-sm">
                <q-card-section>
                    <div class="text-h6">{{ editingId ? 'Edit site' : 'Add site' }}</div>
                </q-card-section>

                <q-card-section class="q-gutter-md">
                    <q-input v-model="form.name" label="Name" :error="!!errors.name" :error-message="errors.name?.[0]" />
                    <q-input v-model="form.url" label="URL" :error="!!errors.url" :error-message="errors.url?.[0]" />
                    <q-select v-model="form.driver" :options="siteDrivers" label="Driver" emit-value map-options :options-value="(o: string) => o" />
                    <q-select
                        v-if="needsAdapter"
                        v-model="form.adapter"
                        :options="siteAdapters"
                        label="Adapter"
                        emit-value
                        map-options
                        :error="!!errors['config.adapter']"
                        :error-message="errors['config.adapter']?.[0]"
                    />
                    <q-toggle v-model="form.enabled" label="Enabled" />
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat label="Cancel" v-close-popup />
                    <q-btn color="primary" label="Save" @click="save" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </q-layout>
</template>
