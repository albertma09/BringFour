<script setup lang="ts">
defineProps<{ min: number }>();
</script>

<template>
    <div class="rounded-lg border border-dashed border-carbon-500 bg-carbon-800/40 px-5 py-8 text-center">
        <p class="text-sm font-medium text-bone">{{ $t('muestra.insuficiente') }}</p>
        <p class="mt-1 text-sm text-ash">{{ $t('muestra.explicacion', { min }) }}</p>
    </div>
</template>
