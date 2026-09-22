<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Companeros } from '@/api/cliente';
import EmptySample from '@/components/EmptySample.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

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
    <div>
        <p class="mb-5 max-w-2xl text-sm leading-relaxed text-fog">{{ $t('sugerencia.companeros_texto') }}</p>

        <p v-if="cargando" class="text-sm text-fog">{{ $t('cargando') }}…</p>

        <EmptySample v-else-if="!datos || datos.companeros.length === 0" :min="datos?.muestra.min_sample ?? 30" />

        <template v-else>
            <p class="cifra mb-4 text-[11px] text-fog-dim">
                {{ $t('sugerencia.base', { n: datos.n, total: datos.equipos }) }}
            </p>

            <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <li
                    v-for="companero in datos.companeros"
                    :key="companero.slug"
                    class="superficie flex items-center gap-3 rounded-xl border border-line-soft px-3 py-2.5"
                >
                    <SpeciesSprite
                        :sprite="companero.sprite"
                        :stone="companero.sprite_stone"
                        :name="nombre(companero)"
                        :size="40"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5">
                            <span class="truncate text-sm font-medium text-mist">{{ nombre(companero) }}</span>
                            <TypeTag v-for="tipo in companero.types" :key="tipo" :type="tipo" />
                        </span>
                        <span class="cifra mt-0.5 block text-[11px] text-fog">
                            {{ $t('sugerencia.juntos', { pct: companero.juntos_pct }) }}
                        </span>
                        <span class="cifra block text-[11px] text-fog-dim">
                            {{ $t('sugerencia.afinidad', { veces: companero.veces, base: companero.general_pct }) }}
                            · N&nbsp;{{ companero.n }}
                        </span>
                    </span>
                    <button
                        v-if="huecoLibre"
                        type="button"
                        class="shrink-0 rounded-lg border border-line px-2 py-1 text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                        @click="emit('anadir', companero.slug)"
                    >
                        {{ $t('sugerencia.anadir') }}
                    </button>
                </li>
            </ul>
        </template>
    </div>
</template>
