<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Banda } from '@/design/bandas';
import Rotulo from '@/components/Rotulo.vue';
import SpeciesCard from '@/components/SpeciesCard.vue';

const props = defineProps<{ banda: Banda; inicial?: number }>();

const desplegada = ref(false);
const tope = computed(() => props.inicial ?? 8);
const visibles = computed(() => (desplegada.value ? props.banda.filas : props.banda.filas.slice(0, tope.value)));
const restantes = computed(() => props.banda.filas.length - tope.value);

const rango = computed(() => {
    if (props.banda.desde !== null && props.banda.hasta !== null) {
        return `${props.banda.desde.toFixed(0)}–${props.banda.hasta.toFixed(0)}%`;
    }

    if (props.banda.desde !== null) return `≥ ${props.banda.desde.toFixed(0)}%`;

    return `< ${(props.banda.hasta ?? 0).toFixed(0)}%`;
});
</script>

<template>
    <section v-if="banda.filas.length > 0" class="mb-12">
        <Rotulo :texto="$t(`meta.banda.${banda.clave}.titulo`)" :nota="rango" />

        <p class="mb-5 max-w-xl text-sm leading-relaxed text-fog">
            {{ $t(`meta.banda.${banda.clave}.texto`) }}
        </p>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <SpeciesCard v-for="fila in visibles" :key="fila.slug" :fila="fila" />
        </div>

        <button
            v-if="restantes > 0"
            type="button"
            class="mt-4 text-xs text-fog transition-colors hover:text-amber"
            @click="desplegada = !desplegada"
        >
            {{ desplegada ? $t('meta.banda.menos') : $t('meta.banda.mas', { n: restantes }) }}
        </button>
    </section>
</template>
