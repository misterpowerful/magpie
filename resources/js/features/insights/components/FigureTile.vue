<script setup lang="ts">
/** Plain stat tile: label, value, optional note. No plot, no hover layer. */
defineProps<{ label: string; value: string; note?: string }>()
</script>

<template>
    <div class="flex flex-col gap-1 rounded-xl border border-white/8 bg-[var(--q-dark)] p-4">
        <div class="text-sm font-medium text-zinc-400">{{ label }}</div>
        <div class="text-2xl font-semibold text-zinc-100">{{ value }}</div>
        <div v-if="note" class="text-xs text-zinc-500">{{ note }}</div>
    </div>
</template>
