<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();

const sites = ref([]);
const loading = ref(false);
const dialog = ref(false);
const editingId = ref(null);
const form = ref(blankForm());
const errors = ref({});

const DRIVERS = ['email', 'rss', 'api', 'html', 'browser'];
const ADAPTERS = ['we_work_remotely', 'larajobs', 'remotive', 'remote_ok'];

const needsAdapter = computed(() => ['rss', 'api'].includes(form.value.driver));

const columns = [
    { name: 'name', label: 'Name', field: 'name', align: 'left' },
    { name: 'driver', label: 'Driver', field: 'driver', align: 'left' },
    { name: 'enabled', label: 'Enabled', field: 'enabled', align: 'left' },
    { name: 'last_status', label: 'Last status', field: 'last_status', align: 'left' },
    { name: 'last_run_at', label: 'Last run', field: 'last_run_at', align: 'left' },
    { name: 'actions', label: '', field: 'actions', align: 'right' },
];

function blankForm() {
    return { name: '', url: '', driver: 'rss', adapter: null, enabled: true };
}

async function loadSites() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/admin/sites');
        sites.value = data.data;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    form.value = blankForm();
    errors.value = {};
    dialog.value = true;
}

function openEdit(site) {
    editingId.value = site.id;
    form.value = {
        name: site.name,
        url: site.url,
        driver: site.driver,
        adapter: site.config?.adapter ?? null,
        enabled: site.enabled,
    };
    errors.value = {};
    dialog.value = true;
}

async function save() {
    errors.value = {};

    const payload = {
        name: form.value.name,
        url: form.value.url,
        driver: form.value.driver,
        config: needsAdapter.value ? { adapter: form.value.adapter } : {},
        enabled: form.value.enabled,
    };

    try {
        if (editingId.value) {
            await api.put(`/api/admin/sites/${editingId.value}`, payload);
        } else {
            await api.post('/api/admin/sites', payload);
        }

        dialog.value = false;
        await loadSites();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
}

async function remove(site) {
    await api.delete(`/api/admin/sites/${site.id}`);
    await loadSites();
}

async function runNow(site) {
    await api.post(`/api/admin/sites/${site.id}/run`);
    await loadSites();
}

onMounted(loadSites);
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

                <q-table :rows="sites" :columns="columns" row-key="id" :loading="loading" flat bordered>
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

        <q-dialog v-model="dialog">
            <q-card style="width: 420px" class="q-pa-sm">
                <q-card-section>
                    <div class="text-h6">{{ editingId ? 'Edit site' : 'Add site' }}</div>
                </q-card-section>

                <q-card-section class="q-gutter-md">
                    <q-input v-model="form.name" label="Name" :error="!!errors.name" :error-message="errors.name?.[0]" />
                    <q-input v-model="form.url" label="URL" :error="!!errors.url" :error-message="errors.url?.[0]" />
                    <q-select v-model="form.driver" :options="DRIVERS" label="Driver" emit-value map-options :options-value="(o) => o" />
                    <q-select
                        v-if="needsAdapter"
                        v-model="form.adapter"
                        :options="ADAPTERS"
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
