<script setup lang="ts">
/**
 * Shown when pending listings were scored against older criteria or an
 * older resume. Re-scoring costs usage, so it's never automatic.
 */
defineProps<{ count: number; busy: boolean }>()
defineEmits<{ rescore: [] }>()
</script>

<template>
    <div
        v-if="count > 0"
        class="flex flex-nowrap! items-start gap-3 rounded-lg border border-amber-400/30 bg-amber-400/5 p-3 text-sm"
        role="status"
    >
        <q-icon name="update" size="20px" class="text-amber-400" />
        <div class="min-w-0 flex-1">
            <div class="font-medium text-zinc-100">{{ count }} pending {{ count === 1 ? 'listing was' : 'listings were' }} scored against older criteria or an older resume</div>
            <div class="text-zinc-400">Re-scoring queues them for the next run. It uses Claude usage, one call per listing.</div>
        </div>
        <q-btn outline no-caps color="accent" label="Re-score" :loading="busy" @click="$emit('rescore')" />
    </div>
</template>
