<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ pct: number; tono?: 'neutro' | 'signo' }>(), { tono: 'neutro' });

const ancho = computed(() => Math.min(100, Math.abs(props.pct)));
const color = computed(() => {
    if (props.tono === 'neutro') return 'var(--color-amber-dim)';

    return props.pct >= 0 ? 'var(--color-moss)' : 'var(--color-clay)';
});
</script>

<template>
    <span class="flex items-center gap-2">
        <span class="cifra w-14 text-right text-sm text-mist">
            {{ tono === 'signo' && pct > 0 ? '+' : '' }}{{ pct.toFixed(1) }}%
        </span>
        <span class="h-1.5 w-full max-w-24 overflow-hidden rounded-full bg-line-soft">
            <span class="block h-full rounded-full" :style="{ width: `${ancho}%`, backgroundColor: color }" />
        </span>
    </span>
</template>
