<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const listings = ref([]);
const loading = ref(false);
const reasonDialog = ref(false);
const pendingAction = ref({ listing: null, status: null, reason: '' });

const columns = [
    { name: 'company', label: 'Company', field: 'company', align: 'left' },
    { name: 'title', label: 'Title', field: 'title', align: 'left' },
    { name: 'location_text', label: 'Location', field: 'location_text', align: 'left' },
    { name: 'remote_type', label: 'Remote', field: 'remote_type', align: 'left' },
    { name: 'state', label: 'Pipeline state', field: 'state', align: 'left' },
    { name: 'status', label: 'Status', field: 'status', align: 'left' },
    { name: 'actions', label: '', field: 'actions', align: 'right' },
];

async function loadListings() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/listings');
        listings.value = data.data;
    } finally {
        loading.value = false;
    }
}

function openReasonDialog(listing, status) {
    pendingAction.value = { listing, status, reason: '' };
    reasonDialog.value = true;
}

async function confirmAction() {
    const { listing, status, reason } = pendingAction.value;

    const { data } = await api.patch(`/api/listings/${listing.id}/status`, {
        status,
        reason: reason || null,
    });

    const index = listings.value.findIndex((l) => l.id === listing.id);
    if (index !== -1) {
        listings.value[index] = data.data;
    }

    reasonDialog.value = false;
}

async function logout() {
    await auth.logout();
    window.location.href = '/login';
}

onMounted(loadListings);
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <q-header class="bg-primary text-white">
            <q-toolbar>
                <q-toolbar-title>Magpie 2.0</q-toolbar-title>
                <q-btn flat label="Log out" @click="logout" />
            </q-toolbar>
        </q-header>

        <q-page-container>
            <q-page class="q-pa-md">
                <q-table
                    :rows="listings"
                    :columns="columns"
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
