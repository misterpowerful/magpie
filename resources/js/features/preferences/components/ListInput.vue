<script setup lang="ts">
/** A list of short strings as chips: type, press Enter to add; × to remove. */
defineProps<{ label: string; hint?: string; error?: string }>()
const model = defineModel<string[]>({ required: true })
</script>

<template>
    <q-select
        v-model="model"
        :label="label"
        :hint="hint"
        :error="!!error"
        :error-message="error"
        outlined
        dense
        multiple
        use-chips
        use-input
        hide-dropdown-icon
        new-value-mode="add-unique"
        input-debounce="0"
    />
</template>
