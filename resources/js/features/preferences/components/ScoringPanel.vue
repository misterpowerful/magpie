<script setup lang="ts">
import { ref, watch } from 'vue'
import Card from '@/shared/components/Card.vue'
import type { Preferences, PreferencesUpdate } from '../types'
import ListInput from './ListInput.vue'
import RescoreBanner from './RescoreBanner.vue'

/**
 * What the scorer judges on (free text, in your own words) and the
 * structured preferences it — and the filters before it — run against.
 */
const props = defineProps<{
    preferences: Preferences
    errors: Record<string, string[]>
    saving: boolean
    rescoring: boolean
}>()

const emit = defineEmits<{ save: [update: PreferencesUpdate]; rescore: [] }>()

function draftFrom(p: Preferences) {
    return {
        criteria: p.scoring_criteria ?? p.default_scoring_criteria,
        remote_floor: p.remote_floor,
        onsite_floor: p.onsite_floor,
        hard_min: p.hard_min,
        onsite_states: [...p.onsite_states],
        include_titles: [...p.include_titles],
        exclude_titles: [...p.exclude_titles],
        include_stack: [...p.include_stack],
        exclude_stack: [...p.exclude_stack],
    }
}

const draft = ref(draftFrom(props.preferences))
watch(
    () => props.preferences,
    (p) => (draft.value = draftFrom(p)),
)

const err = (field: string) => props.errors[field]?.[0] ?? props.errors[`${field}.0`]?.[0]

function save() {
    const criteria = draft.value.criteria.trim()
    emit('save', {
        // Unchanged default text is stored as "use the default", so a
        // future improvement to the default still reaches you.
        scoring_criteria: criteria === props.preferences.default_scoring_criteria.trim() ? null : criteria,
        remote_floor: Number(draft.value.remote_floor),
        onsite_floor: Number(draft.value.onsite_floor),
        hard_min: Number(draft.value.hard_min),
        onsite_states: draft.value.onsite_states,
        include_titles: draft.value.include_titles,
        exclude_titles: draft.value.exclude_titles,
        include_stack: draft.value.include_stack,
        exclude_stack: draft.value.exclude_stack,
    })
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <RescoreBanner :count="preferences.stale_scored_count" :busy="rescoring" @rescore="emit('rescore')" />

        <Card
            title="What Sonnet 5.5 judges on"
            :subtitle="`In your own words — sent to the scorer with every listing, along with your resume and the preferences below.${preferences.scoring_criteria === null ? ' Currently using the default.' : ''}`"
        >
            <q-input v-model="draft.criteria" type="textarea" autogrow outlined :error="!!err('scoring_criteria')" :error-message="err('scoring_criteria')" />
            <div>
                <q-btn flat dense no-caps size="sm" color="accent" label="Reset to default" @click="draft.criteria = preferences.default_scoring_criteria" />
            </div>
        </Card>

        <Card title="Salary" subtitle="Below the hard minimum is filtered out before scoring; the floors are what the scorer compares against.">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <q-input v-model.number="draft.remote_floor" type="number" label="Remote floor" prefix="$" outlined dense :error="!!err('remote_floor')" :error-message="err('remote_floor')" />
                <q-input v-model.number="draft.onsite_floor" type="number" label="On-site floor" prefix="$" outlined dense :error="!!err('onsite_floor')" :error-message="err('onsite_floor')" />
                <q-input v-model.number="draft.hard_min" type="number" label="Hard minimum" prefix="$" outlined dense :error="!!err('hard_min')" :error-message="err('hard_min')" />
            </div>
        </Card>

        <Card title="Location, titles, and stack">
            <ListInput v-model="draft.onsite_states" label="States you'd work on-site in" hint="Two-letter codes, e.g. IL" :error="err('onsite_states')" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <ListInput v-model="draft.include_titles" label="Titles to include" hint="Patterns, e.g. engineer|developer" :error="err('include_titles')" />
                <ListInput v-model="draft.exclude_titles" label="Titles to exclude" hint="Patterns, e.g. intern|director" :error="err('exclude_titles')" />
                <ListInput v-model="draft.include_stack" label="Stack you want" hint="e.g. PHP, Laravel" :error="err('include_stack')" />
                <ListInput v-model="draft.exclude_stack" label="Stack to avoid" hint="e.g. .NET" :error="err('exclude_stack')" />
            </div>
        </Card>

        <div class="flex flex-nowrap! items-center gap-3">
            <q-btn unelevated no-caps color="accent" label="Save scoring settings" :loading="saving" @click="save" />
            <slot name="status" />
        </div>
    </div>
</template>
