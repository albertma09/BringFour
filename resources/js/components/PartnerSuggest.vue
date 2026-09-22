<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Companeros } from '@/api/cliente';
import EmptySample from '@/components/EmptySample.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';

const props = defineProps<{ elegidos: string[]; huecoLibre: boolean }>();
const emit = defineEmits<{ anadir: [string] }>();

const { locale } = useI18n();

const cargando = ref(false);
const datos = ref<Companeros | null>(null);

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargar(): Promise<void> {
    if (props.elegidos.length === 0) {
        datos.value = null;

        return;
    }

    cargando.value = true;

    try {
        datos.value = await api.companeros(comunes.value, props.elegidos);
    } catch {
        datos.value = null;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.elegidos, comunes.value], cargar, { immediate: true, deep: true });
</script>

<template>
    <section class="mb-6 rounded-xl border border-line-soft bg-surface/60 p-4">
        <header class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <h2 class="rotulo">{{ $t('sugerencia.companeros') }}</h2>
            <p class="text-xs text-fog-dim">{{ $t('sugerencia.companeros_corto') }}</p>
            <span v-if="datos" class="cifra ml-auto text-[11px] text-fog-dim">
                {{ $t('sugerencia.base', { n: datos.n, total: datos.equipos }) }}
            </span>
        </header>

        <p v-if="cargando" class="text-sm text-fog">{{ $t('cargando') }}…</p>

        <EmptySample v-else-if="!datos || datos.companeros.length === 0" :min="datos?.muestra.min_sample ?? 30" />

        <ul v-else class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
            <li
                v-for="companero in datos.companeros"
                :key="companero.slug"
                class="flex w-36 shrink-0 flex-col items-center gap-1 rounded-lg border border-line-soft bg-card px-2 py-2.5 text-center"
            >
                <SpeciesSprite
                    :sprite="companero.sprite"
                    :stone="companero.sprite_stone"
                    :name="nombre(companero)"
                    :size="44"
                />
                <span class="w-full truncate text-xs font-medium text-mist">{{ nombre(companero) }}</span>
                <span class="cifra text-[11px] text-amber">
                    {{ $t('sugerencia.afinidad_corta', { veces: companero.veces }) }}
                </span>
                <span class="cifra text-[10px] text-fog-dim">
                    {{ $t('sugerencia.juntos', { pct: companero.juntos_pct }) }} · N&nbsp;{{ companero.n }}
                </span>
                <button
                    v-if="huecoLibre"
                    type="button"
                    class="mt-1 w-full rounded border border-line px-2 py-1 text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                    @click="emit('anadir', companero.slug)"
                >
                    {{ $t('sugerencia.anadir') }}
                </button>
            </li>
        </ul>

        <p v-if="datos && datos.companeros.length > 0" class="mt-3 text-[11px] leading-relaxed text-fog-dim">
            {{ $t('sugerencia.companeros_texto') }}
        </p>
    </section>
</template>
