<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@/shared/lib/apiError'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
    error.value = ''
    loading.value = true

    try {
        await auth.login(email.value, password.value)
        router.push({ name: 'dashboard' })
    } catch (e) {
        error.value = apiErrorMessage(e, 'Login failed.')
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <q-layout view="hHh lpR fFf">
        <q-page-container>
            <q-page class="flex flex-center">
                <q-card style="width: 360px" class="q-pa-md">
                    <q-card-section>
                        <div class="text-h6">Magpie 2.0</div>
                    </q-card-section>

                    <q-card-section>
                        <q-form @submit.prevent="submit" class="q-gutter-md">
                            <q-input v-model="email" type="email" label="Email" autofocus />
                            <q-input v-model="password" type="password" label="Password" />
                            <div v-if="error" class="text-negative text-caption">{{ error }}</div>
                            <q-btn type="submit" color="primary" label="Log in" :loading="loading" class="full-width" />
                        </q-form>
                    </q-card-section>
                </q-card>
            </q-page>
        </q-page-container>
    </q-layout>
</template>
