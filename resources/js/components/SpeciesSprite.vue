<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{ sprite: string | null; stone?: string | null; name: string; size?: number }>(),
    { stone: null, size: 48 },
);

type Offset = { x: number; y: number };

const offsets = ref<Record<string, Offset>>({});

onMounted(async () => {
    if (!props.stone) return;

    try {
        const respuesta = await fetch('/sprites/items/icons.json');
        if (respuesta.ok) offsets.value = await respuesta.json();
    } catch {
        // sin los offsets se ensena solo la forma base
    }
});

const ruta = computed(() => (props.sprite ? `/sprites/pokemon/${props.sprite}` : null));
const offset = computed(() => (props.stone ? offsets.value[props.stone] : undefined));
</script>

<template>
    <span class="relative inline-block shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
        <img
            v-if="ruta"
            :src="ruta"
            :alt="name"
            class="sprite h-full w-full object-contain"
            loading="lazy"
            decoding="async"
        />
        <span
            v-else
            class="flex h-full w-full items-center justify-center rounded bg-surface text-[10px] text-fog-dim"
            aria-hidden="true"
            >?</span
        >
        <span
            v-if="offset"
            class="absolute -right-0.5 -bottom-0.5 block rounded-full bg-night ring-1 ring-line"
            :style="{
                width: '20px',
                height: '20px',
                backgroundImage: 'url(/sprites/items/itemicons-sheet.png)',
                backgroundPosition: `${offset.x + 2}px ${offset.y + 2}px`,
            }"
            :title="stone ?? undefined"
        />
    </span>
</template>
