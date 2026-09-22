<script setup lang="ts">
defineProps<{ min: number }>();
</script>

<template>
    <div class="rounded-xl border border-dashed border-line bg-surface/50 px-5 py-8 text-center">
        <p class="text-sm font-medium text-mist">{{ $t('muestra.insuficiente') }}</p>
        <p class="mt-1 text-sm text-fog">{{ $t('muestra.explicacion', { min }) }}</p>
    </div>
</template>
