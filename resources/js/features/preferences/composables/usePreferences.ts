import { ref } from 'vue'
import api from '@/shared/lib/api'
import { apiErrorMessage, validationErrors } from '@/shared/lib/apiError'
import type { Preferences, PreferencesUpdate } from '../types'

/**
 * Shared by the Scoring and Resume tabs so a save in one (which can
 * change how many listings are stale) is reflected in the other.
 */
export function usePreferences() {
    const preferences = ref<Preferences | null>(null)
    const loading = ref(false)
    const saving = ref(false)
    const uploading = ref(false)
    const rescoring = ref(false)
    const errors = ref<Record<string, string[]>>({})
    const message = ref<{ kind: 'ok' | 'error'; text: string } | null>(null)

    async function load() {
        loading.value = true
        try {
            const { data } = await api.get<{ data: Preferences }>('/api/preferences')
            preferences.value = data.data
        } finally {
            loading.value = false
        }
    }

    async function save(update: PreferencesUpdate, okText = 'Saved.') {
        saving.value = true
        errors.value = {}
        message.value = null
        try {
            const { data } = await api.put<{ data: Preferences }>('/api/preferences', update)
            preferences.value = data.data
            message.value = { kind: 'ok', text: okText }
            return true
        } catch (e) {
            errors.value = validationErrors(e)
            message.value = { kind: 'error', text: apiErrorMessage(e, 'Saving failed.') }
            return false
        } finally {
            saving.value = false
        }
    }

    async function uploadResume(file: File) {
        uploading.value = true
        errors.value = {}
        message.value = null
        try {
            const body = new FormData()
            body.append('resume', file)
            const { data } = await api.post<{ data: Preferences }>('/api/preferences/resume', body)
            preferences.value = data.data
            message.value = { kind: 'ok', text: `Uploaded ${file.name}. Check the extracted text below.` }
        } catch (e) {
            errors.value = validationErrors(e)
            message.value = { kind: 'error', text: errors.value.resume?.[0] ?? apiErrorMessage(e, 'Upload failed.') }
        } finally {
            uploading.value = false
        }
    }

    async function rescoreStale() {
        rescoring.value = true
        message.value = null
        try {
            const { data } = await api.post<{ data: { queued: number } }>('/api/preferences/rescore')
            message.value = { kind: 'ok', text: `${data.data.queued} listings will be re-scored on the next run.` }
            await load()
        } catch (e) {
            message.value = { kind: 'error', text: apiErrorMessage(e, 'Re-score failed.') }
        } finally {
            rescoring.value = false
        }
    }

    return { preferences, loading, saving, uploading, rescoring, errors, message, load, save, uploadResume, rescoreStale }
}
