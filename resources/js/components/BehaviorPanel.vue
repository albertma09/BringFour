<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type AccionConducta, type ContextoConducta, type Muestra } from '@/api/cliente';
import EmptySample from '@/components/EmptySample.vue';
import PctBar from '@/components/PctBar.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ slug: string }>();
const { locale } = useI18n();

const contextos = ref<ContextoConducta[]>([]);
const elegido = ref<{ rival: string; fase: string } | null>(null);
const resultado = ref<{ muestra: Muestra; acciones: AccionConducta[] } | null>(null);

async function cargar(): Promise<void> {
    elegido.value = null;
    resultado.value = null;

    try {
        contextos.value = (await api.contextos(props.slug, comunes.value)).contextos;
    } catch {
        contextos.value = [];
    }

    if (contextos.value.length > 0) {
        await ver(contextos.value[0]);
    }
}

async function ver(contexto: ContextoConducta): Promise<void> {
    elegido.value = { rival: contexto.rival, fase: contexto.fase };
    resultado.value = await api.conducta(props.slug, contexto.rival, contexto.fase, comunes.value);
}

watch(() => [props.slug, comunes.value], cargar, { immediate: true, deep: true });

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

function accion(a: AccionConducta): string {
    if (a.tipo === 'switch') return '';

    return locale.value === 'es' ? (a.move_name_es ?? a.move_name ?? '') : (a.move_name ?? '');
}
</script>

<template>
    <div>
        <EmptySample v-if="contextos.length === 0" :min="30" />

        <div v-else class="grid gap-5 lg:grid-cols-[17rem_1fr]">
            <ul class="max-h-[26rem] space-y-1 overflow-y-auto pr-1">
                <li v-for="contexto in contextos" :key="`${contexto.rival}-${contexto.fase}`">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition-colors"
                        :class="
                            elegido?.rival === contexto.rival && elegido?.fase === contexto.fase
                                ? 'border-amber-dim bg-raised text-mist'
                                : 'border-line-soft bg-surface text-fog hover:border-line hover:text-mist'
                        "
                        @click="ver(contexto)"
                    >
                        <span>
                            {{ nombre({ name: contexto.rival_name, name_es: contexto.rival_name_es }) }}
                            <span class="block text-[11px] text-fog-dim">{{ $t(`conducta.${contexto.fase}`) }}</span>
                        </span>
                        <span class="cifra text-[11px] text-fog">{{ contexto.n }}</span>
                    </button>
                </li>
            </ul>

            <div v-if="resultado" class="overflow-hidden rounded-xl border border-line-soft">
                <p class="cifra border-b border-line-soft bg-surface px-4 py-3 text-sm text-fog">
                    {{ resultado.muestra.n }} {{ $t('conducta.decisiones') }}
                </p>
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="rotulo px-4 py-2">{{ $t('conducta.columna.accion') }}</th>
                            <th class="rotulo px-4 py-2">{{ $t('meta.columna.n') }}</th>
                            <th class="rotulo px-4 py-2">%</th>
                            <th class="rotulo hidden px-4 py-2 sm:table-cell">
                                {{ $t('conducta.columna.intervalo') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in resultado.acciones" :key="a.action_key" class="border-t border-line-soft">
                            <td class="px-4 py-2">
                                <span v-if="a.tipo === 'switch'" class="text-fog italic">
                                    {{ $t('conducta.cambiar') }}
                                </span>
                                <span v-else class="flex items-center gap-2">
                                    <TypeTag v-if="a.move_type" :type="a.move_type" />
                                    <span class="text-mist">{{ accion(a) }}</span>
                                </span>
                            </td>
                            <td class="cifra px-4 py-2 text-fog">{{ a.n }}</td>
                            <td class="px-4 py-2"><PctBar :pct="a.pct" /></td>
                            <td class="cifra hidden px-4 py-2 text-xs text-fog-dim sm:table-cell">
                                {{ a.intervalo[0].toFixed(1) }} – {{ a.intervalo[1].toFixed(1) }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="border-t border-line-soft px-4 py-3 text-xs text-fog-dim">
                    {{ $t('conducta.solo_elegido') }}
                </p>
            </div>
        </div>
    </div>
</template>
