import { computed, ref } from 'vue'
import api from '@/shared/lib/api'
import { validationErrors } from '@/shared/lib/apiError'
import type { Site, SiteForm } from '../types'

function blankForm(): SiteForm {
    return { name: '', url: '', driver: 'rss', adapter: null, enabled: true }
}

export function useSiteForm(onSaved: () => void | Promise<void>) {
    const dialogOpen = ref(false)
    const editingId = ref<number | null>(null)
    const form = ref<SiteForm>(blankForm())
    const errors = ref<Record<string, string[]>>({})

    const needsAdapter = computed(() => ['rss', 'api'].includes(form.value.driver))

    function openCreate() {
        editingId.value = null
        form.value = blankForm()
        errors.value = {}
        dialogOpen.value = true
    }

    function openEdit(site: Site) {
        editingId.value = site.id
        form.value = {
            name: site.name,
            url: site.url,
            driver: site.driver,
            adapter: site.config?.adapter ?? null,
            enabled: site.enabled,
        }
        errors.value = {}
        dialogOpen.value = true
    }

    async function save() {
        errors.value = {}

        const payload = {
            name: form.value.name,
            url: form.value.url,
            driver: form.value.driver,
            config: needsAdapter.value ? { adapter: form.value.adapter } : {},
            enabled: form.value.enabled,
        }

        try {
            if (editingId.value) {
                await api.put(`/api/admin/sites/${editingId.value}`, payload)
            } else {
                await api.post('/api/admin/sites', payload)
            }

            dialogOpen.value = false
            await onSaved()
        } catch (e) {
            errors.value = validationErrors(e)
        }
    }

    return { dialogOpen, editingId, form, errors, needsAdapter, openCreate, openEdit, save }
}
