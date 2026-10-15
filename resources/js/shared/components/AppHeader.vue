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

            <!-- Quasar's own breakpoint classes (gt-xs / xs), not Tailwind's
                 hidden/sm:flex: q-btn's unlayered display rule would beat those. -->
            <q-btn
                v-for="link in navLinks"
                :key="link.name"
                flat
                no-caps
                :label="link.label"
                class="gt-xs q-mr-xs"
                :class="route.name === link.name ? 'text-white text-weight-medium' : 'text-zinc-400'"
                @click="router.push({ name: link.name })"
            />

            <q-btn flat no-caps label="Log out" class="gt-xs text-zinc-400" @click="logout" />

            <q-btn flat round dense icon="menu" class="xs text-zinc-300" aria-label="Menu">
                <q-menu anchor="bottom right" self="top right" class="bg-[#111822] text-zinc-100 border border-white/10">
                    <q-list dense style="min-width: 180px">
                        <q-item
                            v-for="link in navLinks"
                            :key="link.name"
                            v-close-popup
                            clickable
                            :active="route.name === link.name"
                            active-class="text-accent"
                            @click="router.push({ name: link.name })"
                        >
                            <q-item-section>{{ link.label }}</q-item-section>
                        </q-item>
                        <q-separator dark />
                        <q-item v-close-popup clickable class="text-zinc-400" @click="logout">
                            <q-item-section>Log out</q-item-section>
                        </q-item>
                    </q-list>
                </q-menu>
            </q-btn>
        </q-toolbar>
    </q-header>
</template>
