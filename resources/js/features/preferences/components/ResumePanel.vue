<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Preferences } from '../types'
import RescoreBanner from './RescoreBanner.vue'

/**
 * The resume the scorer measures against: upload a new version to
 * replace it, then check (and if needed fix) the extracted text — that
 * text, not the file, is what Claude reads.
 */
const props = defineProps<{
    preferences: Preferences
    errors: Record<string, string[]>
    saving: boolean
    uploading: boolean
    rescoring: boolean
}>()

const emit = defineEmits<{ upload: [file: File]; saveText: [text: string | null]; rescore: [] }>()

const file = ref<File | null>(null)
const text = ref(props.preferences.resume_text ?? '')
watch(
    () => props.preferences.resume_text,
    (value) => (text.value = value ?? ''),
)

// Upload as soon as a file is picked, then clear the picker.
watch(file, (picked) => {
    if (picked) {
        emit('upload', picked)
        file.value = null
    }
})

const uploadedAt = () =>
    props.preferences.resume_uploaded_at
        ? new Date(props.preferences.resume_uploaded_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
        : null
</script>

<template>
    <div class="flex flex-col gap-4">
        <RescoreBanner :count="preferences.stale_scored_count" :busy="rescoring" @rescore="emit('rescore')" />

        <section class="flex flex-col gap-3 rounded-xl border border-white/8 bg-[var(--q-dark)] p-4">
            <div role="heading" aria-level="2" class="text-sm font-medium text-zinc-200">Resume file</div>

            <div v-if="preferences.has_resume_file" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <q-icon name="description" size="18px" class="text-zinc-400" />
                <span class="text-zinc-100">{{ preferences.resume_filename }}</span>
                <span class="text-xs text-zinc-500">uploaded {{ uploadedAt() }}</span>
                <a href="/api/preferences/resume" class="text-xs text-teal-300 hover:underline">Download</a>
            </div>
            <div v-else class="text-sm text-zinc-400">
                {{ preferences.resume_text ? 'No file on record — the text below was entered directly.' : 'No resume yet. Listings are being scored on preferences alone.' }}
            </div>

            <q-file
                v-model="file"
                :label="preferences.has_resume_file ? 'Replace with a new version' : 'Upload your resume'"
                hint="PDF, Word (.docx), or plain text — up to 5 MB"
                accept=".pdf,.docx,.txt,.md"
                outlined
                dense
                :loading="uploading"
                :error="!!errors.resume"
                :error-message="errors.resume?.[0]"
            >
                <template #prepend><q-icon name="upload_file" /></template>
            </q-file>
        </section>

        <section class="flex flex-col gap-3 rounded-xl border border-white/8 bg-[var(--q-dark)] p-4">
            <div>
                <div role="heading" aria-level="2" class="text-sm font-medium text-zinc-200">What Claude reads</div>
                <div class="text-xs text-zinc-500">Extracted from your file. Fix anything that came through garbled — this text is what each listing is scored against.</div>
            </div>
            <q-input
                v-model="text"
                type="textarea"
                outlined
                input-style="min-height: 320px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px"
                :error="!!errors.resume_text"
                :error-message="errors.resume_text?.[0]"
            />
            <div class="flex flex-nowrap! items-center gap-3">
                <q-btn
                    unelevated
                    no-caps
                    color="accent"
                    label="Save text"
                    :loading="saving"
                    :disable="text === (preferences.resume_text ?? '')"
                    @click="emit('saveText', text.trim() === '' ? null : text)"
                />
                <slot name="status" />
            </div>
        </section>
    </div>
</template>
