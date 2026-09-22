<script setup lang="ts">
import { ajustes, sinCorte } from '@/ajustes';

defineProps<{ min: number }>();
</script>

<template>
    <div class="rounded-xl border border-dashed border-line bg-surface/50 px-5 py-8 text-center">
        <p class="text-sm font-medium text-mist">{{ $t('muestra.insuficiente') }}</p>
        <p class="mt-1 text-sm text-fog">{{ $t('muestra.explicacion', { min }) }}</p>

        <template v-if="ajustes.elo > 0">
            <p class="mt-3 text-sm text-fog">{{ $t('muestra.por_el_corte', { elo: ajustes.elo }) }}</p>
            <button
                type="button"
                class="mt-3 rounded-lg border border-line px-3 py-1.5 text-xs text-fog transition-colors hover:border-amber hover:text-amber"
                @click="sinCorte"
            >
                {{ $t('muestra.quitar_corte') }}
            </button>
        </template>
    </div>
</template>
