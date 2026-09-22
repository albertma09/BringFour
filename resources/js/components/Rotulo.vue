<script setup lang="ts">
defineProps<{ texto: string; nota?: string }>();
</script>

<template>
    <header class="mb-4 flex items-baseline gap-4">
        <h2 class="rotulo">{{ texto }}</h2>
        <span class="h-px flex-1 bg-line-soft" aria-hidden="true" />
        <span v-if="nota" class="cifra text-[11px] text-fog-dim">{{ nota }}</span>
    </header>
</template>
