<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FilaBringRate } from '@/api/cliente';
import { typeColor } from '@/design/types';
import SpeciesSprite from '@/components/SpeciesSprite.vue';

const props = defineProps<{ fila: FilaBringRate }>();
const { locale } = useI18n();

const nombre = computed(() =>
    locale.value === 'es' ? (props.fila.name_es ?? props.fila.name) : props.fila.name,
);

const canto = computed(() => typeColor(props.fila.types[0] ?? ''));
const secundario = computed(() => (props.fila.types[1] ? typeColor(props.fila.types[1]) : null));
</script>

<template>
    <RouterLink
        :to="{ name: 'especie', params: { slug: fila.slug } }"
        class="superficie group relative block overflow-hidden rounded-xl border border-line-soft pt-4 pr-4 pb-3 pl-5 transition-colors hover:border-line hover:bg-raised"
    >
        <span
            class="absolute inset-y-0 left-0 w-[3px]"
            :style="{
                background: secundario ? `linear-gradient(to bottom, ${canto} 55%, ${secundario})` : canto,
            }"
            aria-hidden="true"
        />
        <span
            class="pointer-events-none absolute -top-10 -right-10 h-28 w-28 rounded-full opacity-[0.08] blur-2xl transition-opacity group-hover:opacity-[0.16]"
            :style="{ backgroundColor: canto }"
            aria-hidden="true"
        />

        <span class="flex items-start justify-between gap-2">
            <span>
                <span class="cifra block text-3xl leading-none font-bold tracking-tight text-mist">
                    {{ fila.bring_pct.toFixed(1) }}<span class="text-base font-normal text-fog-dim">%</span>
                </span>
                <span class="mt-1 block text-[10px] tracking-wider text-fog-dim uppercase">
                    {{ $t('meta.tarjeta.trae') }}
                </span>
            </span>
            <SpeciesSprite
                :sprite="fila.sprite"
                :stone="fila.sprite_stone"
                :name="nombre"
                :size="56"
                class="-mt-1 -mr-1"
            />
        </span>

        <span class="mt-2 block truncate text-sm font-semibold text-mist">{{ nombre }}</span>

        <span class="mt-1 flex items-baseline gap-2 text-[11px] text-fog-dim">
            <span class="cifra">{{ $t('meta.tarjeta.lleva', { pct: fila.usage_pct.toFixed(1) }) }}</span>
            <span aria-hidden="true">·</span>
            <span class="cifra">{{ $t('muestra.n', { n: fila.n }) }}</span>
        </span>
    </RouterLink>
</template>
