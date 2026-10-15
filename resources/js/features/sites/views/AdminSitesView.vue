<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/shared/lib/api'
import AppHeader from '@/shared/components/AppHeader.vue'
import ResumePanel from '@/features/preferences/components/ResumePanel.vue'
import ScoringPanel from '@/features/preferences/components/ScoringPanel.vue'
import { usePreferences } from '@/features/preferences/composables/usePreferences'
import { probeVerdicts, siteAdapters, siteColumns, siteDrivers } from '../constants'
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

const {
    dialogOpen,
    editingId,
    form,
    errors,
    needsAdapter,
    probing,
    probeResult,
    checkUrl,
    applySuggestion,
    useFeedUrl,
    openCreate,
    openEdit,
    save,
} = useSiteForm(loadSites)

async function remove(site: Site) {
    await api.delete(`/api/admin/sites/${site.id}`)
    await loadSites()
}

async function runNow(site: Site) {
    await api.post(`/api/admin/sites/${site.id}/run`)
    await loadSites()
}

// Sites / Scoring / Resume, kept in the URL (?tab=) so a reload or a
// link lands on the same tab.
const route = useRoute()
const router = useRouter()
const tabs = ['sites', 'scoring', 'resume'] as const
type Tab = (typeof tabs)[number]
const tab = computed<Tab>({
    get: () => (tabs.includes(route.query.tab as Tab) ? (route.query.tab as Tab) : 'sites'),
    set: (value) => router.replace({ query: { ...route.query, tab: value } }),
})

const prefs = usePreferences()
const prefsStatus = computed(() => prefs.message.value)

// Load preferences the first time a preferences tab is opened; clear a
// stale "Saved." when switching tabs.
watch(
    tab,
    (value) => {
        prefs.message.value = null
        if (value !== 'sites' && !prefs.preferences.value) prefs.load()
    },
    { immediate: true },
)

onMounted(loadSites)
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <AppHeader />

        <q-page-container>
            <q-page class="q-pa-md">
                <q-tabs v-model="tab" dense no-caps align="left" class="q-mb-md text-zinc-400" active-color="white" indicator-color="accent">
                    <q-tab name="sites" label="Sites" />
                    <q-tab name="scoring" label="Scoring" />
                    <q-tab name="resume" label="Resume" />
                </q-tabs>

                <div v-if="tab !== 'sites'" class="max-w-3xl">
                    <div v-if="!prefs.preferences.value" class="py-8 text-center text-sm text-zinc-500">Loading…</div>
                    <ScoringPanel
                        v-else-if="tab === 'scoring'"
                        :preferences="prefs.preferences.value"
                        :errors="prefs.errors.value"
                        :saving="prefs.saving.value"
                        :rescoring="prefs.rescoring.value"
                        @save="prefs.save"
                        @rescore="prefs.rescoreStale"
                    >
                        <template #status>
                            <span v-if="prefsStatus" class="text-sm" :class="prefsStatus.kind === 'ok' ? 'text-teal-300' : 'text-red-400'">{{ prefsStatus.text }}</span>
                        </template>
                    </ScoringPanel>
                    <ResumePanel
                        v-else
                        :preferences="prefs.preferences.value"
                        :errors="prefs.errors.value"
                        :saving="prefs.saving.value"
                        :uploading="prefs.uploading.value"
                        :rescoring="prefs.rescoring.value"
                        @upload="prefs.uploadResume"
                        @save-text="(text) => prefs.save({ resume_text: text }, 'Resume text saved.')"
                        @rescore="prefs.rescoreStale"
                    >
                        <template #status>
                            <span v-if="prefsStatus" class="text-sm" :class="prefsStatus.kind === 'ok' ? 'text-teal-300' : 'text-red-400'">{{ prefsStatus.text }}</span>
                        </template>
                    </ResumePanel>
                </div>

                <template v-else>
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
                </template>
            </q-page>
        </q-page-container>

        <q-dialog v-model="dialogOpen">
            <q-card style="width: 480px; max-width: 100%" class="q-pa-sm">
                <q-card-section>
                    <div class="text-h6 text-zinc-100 text-weight-semibold">{{ editingId ? 'Edit site' : 'Add site' }}</div>
                </q-card-section>

                <q-card-section class="q-gutter-md">
                    <q-input v-model="form.name" label="Name" outlined dense :error="!!errors.name" :error-message="errors.name?.[0]" />
                    <q-input
                        v-model="form.url"
                        label="URL"
                        outlined
                        dense
                        :error="!!errors.url"
                        :error-message="errors.url?.[0]"
                        @keydown.enter.prevent="checkUrl"
                    >
                        <template #append>
                            <q-btn
                                flat
                                dense
                                no-caps
                                label="Check"
                                color="accent"
                                :loading="probing"
                                :disable="!form.url"
                                @click="checkUrl"
                            />
                        </template>
                    </q-input>

                    <!-- What the server found at the URL, and which driver that means. -->
                    <div
                        v-if="probeResult"
                        class="flex flex-nowrap! items-start gap-3 rounded-lg border border-white/10 bg-white/5 p-3 text-sm"
                        role="status"
                    >
                        <q-icon
                            :name="probeVerdicts[probeResult.verdict].icon"
                            size="20px"
                            :class="probeVerdicts[probeResult.verdict].iconClass"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-zinc-100">{{ probeVerdicts[probeResult.verdict].label }}</div>
                            <div class="text-zinc-400">{{ probeResult.reason }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <q-btn
                                    v-if="probeResult.feed_url"
                                    size="sm"
                                    outline
                                    no-caps
                                    color="accent"
                                    label="Use the feed instead"
                                    @click="useFeedUrl"
                                />
                                <q-btn
                                    v-if="
                                        editingId &&
                                        probeResult.suggested_driver &&
                                        probeResult.suggested_driver !== form.driver
                                    "
                                    size="sm"
                                    outline
                                    no-caps
                                    color="accent"
                                    :label="`Switch to ${probeResult.suggested_driver}`"
                                    @click="applySuggestion"
                                />
                            </div>
                        </div>
                    </div>

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
                    <template v-if="form.driver === 'html'">
                        <div class="text-xs text-zinc-400">
                            XPath selectors for the job list. Link, title, company and location are relative to each list
                            item. Use “Run now” after saving to test them.
                        </div>
                        <q-input v-model="form.selectors.list_item" label="List item (required)" placeholder="//div[@class='job']" outlined dense />
                        <q-input v-model="form.selectors.link" label="Link (required)" placeholder=".//a/@href" outlined dense />
                        <q-input v-model="form.selectors.title" label="Title (required)" placeholder=".//h2" outlined dense />
                        <q-input v-model="form.selectors.company" label="Company" outlined dense />
                        <q-input v-model="form.selectors.location" label="Location" outlined dense />
                    </template>

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
