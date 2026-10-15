<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/features/auth/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const navLinks = [
    { label: 'Dashboard', name: 'dashboard' },
    { label: 'Insights', name: 'insights' },
    { label: 'Sites', name: 'admin-sites' },
]

async function logout() {
    await auth.logout()
    window.location.href = '/login'
}
</script>

<template>
    <q-header class="bg-[#111822] text-zinc-100 border-b border-white/10 shadow-none">
        <q-toolbar class="q-px-md">
            <q-toolbar-title class="text-weight-semibold text-zinc-100">Magpie 2.0</q-toolbar-title>

            <q-btn
                v-for="link in navLinks"
                :key="link.name"
                flat
                no-caps
                :label="link.label"
                class="q-mr-xs"
                :class="route.name === link.name ? 'text-white text-weight-medium' : 'text-zinc-400'"
                @click="router.push({ name: link.name })"
            />

            <q-btn flat no-caps label="Log out" class="text-zinc-400" @click="logout" />
        </q-toolbar>
    </q-header>
</template>
