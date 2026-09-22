<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    api,
    type AccionConducta,
    type ContextoConducta,
    type Especie,
    type FilaMatchup,
    type Muestra,
} from '@/api/cliente';
import EmptySample from '@/components/EmptySample.vue';
import PctBar from '@/components/PctBar.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ slug: string }>();
const { locale } = useI18n();

const cargando = ref(true);
const fallo = ref(false);
const especie = ref<(Especie & { abilities: string[]; legal: boolean; megapiedra: { slug: string; name: string; name_es: string | null } | null }) | null>(null);
const matchupMuestra = ref<Muestra | null>(null);
const matchups = ref<FilaMatchup[]>([]);
const contextos = ref<ContextoConducta[]>([]);
const rivalElegido = ref<string | null>(null);
const faseElegida = ref<'apertura' | 'medio'>('medio');
const conducta = ref<{ muestra: Muestra; acciones: AccionConducta[] } | null>(null);

const STATS = ['hp', 'atk', 'def', 'spa', 'spd', 'spe'] as const;

function nombre(fila: { name: string; name_es: string | null } | null): string {
    if (!fila) return '';

    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargar(): Promise<void> {
    cargando.value = true;
    fallo.value = false;
    conducta.value = null;
    rivalElegido.value = null;

    try {
        const [ficha, matchup, ctx] = await Promise.all([
            api.especie(props.slug),
            api.matchups(props.slug),
            api.contextos(props.slug),
        ]);

        especie.value = ficha.especie;
        matchupMuestra.value = matchup.muestra;
        matchups.value = matchup.especies;
        contextos.value = ctx.contextos;
    } catch {
        fallo.value = true;
    } finally {
        cargando.value = false;
    }
}

async function verConducta(rival: string, fase: 'apertura' | 'medio'): Promise<void> {
    rivalElegido.value = rival;
    faseElegida.value = fase;
    conducta.value = await api.conducta(props.slug, rival, fase);
}

onMounted(cargar);
watch(() => props.slug, cargar);

const nombreEspecie = computed(() => nombre(especie.value));

function nombreAccion(accion: AccionConducta): string {
    if (accion.tipo === 'switch') return '';

    return locale.value === 'es' ? (accion.move_name_es ?? accion.move_name ?? '') : (accion.move_name ?? '');
}
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'meta' }" class="mb-6 inline-block text-sm text-ash hover:text-sand">
            ← {{ $t('ficha.volver') }}
        </RouterLink>

        <p v-if="cargando" class="text-sm text-ash">{{ $t('cargando') }}…</p>
        <p v-else-if="fallo" class="text-sm text-clay">{{ $t('error') }}</p>

        <template v-else-if="especie">
            <header class="mb-8 flex items-start gap-5">
                <SpeciesSprite
                    :sprite="especie.sprite"
                    :stone="especie.sprite_stone"
                    :name="nombreEspecie"
                    :size="96"
                />
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-bone">{{ nombreEspecie }}</h1>
                    <div class="mt-2 flex gap-1">
                        <TypeTag v-for="tipo in especie.types" :key="tipo" :type="tipo" />
                    </div>
                    <p v-if="!especie.legal" class="mt-2 text-sm text-clay">{{ $t('ficha.ilegal') }}</p>
                    <p v-if="especie.megapiedra" class="mt-2 text-sm text-ash">
                        {{ $t('ficha.megapiedra') }}: {{ nombre(especie.megapiedra) }}
                    </p>
                </div>
            </header>

            <section class="mb-10 grid gap-8 sm:grid-cols-2">
                <div>
                    <h2 class="mb-3 text-xs font-medium tracking-wide text-ash uppercase">
                        {{ $t('ficha.estadisticas') }}
                    </h2>
                    <dl class="space-y-1.5">
                        <div v-for="stat in STATS" :key="stat" class="flex items-center gap-3">
                            <dt class="w-10 text-xs tracking-wide text-ash uppercase">{{ stat }}</dt>
                            <dd class="cifra w-8 text-right text-sm text-bone">
                                {{ especie.base_stats?.[stat] ?? '—' }}
                            </dd>
                            <dd class="h-1.5 w-full max-w-40 overflow-hidden rounded-full bg-carbon-600">
                                <span
                                    class="block h-full rounded-full bg-sand-dim"
                                    :style="{ width: `${Math.min(100, ((especie.base_stats?.[stat] ?? 0) / 200) * 100)}%` }"
                                />
                            </dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <h2 class="mb-3 text-xs font-medium tracking-wide text-ash uppercase">
                        {{ $t('ficha.habilidades') }}
                    </h2>
                    <ul class="space-y-1 text-sm text-bone">
                        <li v-for="habilidad in especie.abilities" :key="habilidad">{{ habilidad }}</li>
                    </ul>
                </div>
            </section>

            <section class="mb-10">
                <h2 class="text-lg font-semibold text-bone">{{ $t('matchup.titulo') }}</h2>
                <p class="mt-1 text-sm text-ash">{{ $t('matchup.entradilla') }}</p>
                <p v-if="matchupMuestra" class="cifra mt-2 text-sm text-ash">
                    {{
                        $t('matchup.enfrentados', {
                            rival: nombreEspecie,
                            enfrentados: matchupMuestra.enfrentados,
                            completos: matchupMuestra.completos,
                        })
                    }}
                </p>

                <EmptySample
                    v-if="matchups.length === 0"
                    class="mt-4"
                    :min="matchupMuestra?.min_sample ?? 30"
                />

                <div v-else class="mt-4 overflow-hidden rounded-lg border border-carbon-600">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-carbon-800 text-left text-xs tracking-wide text-ash uppercase">
                                <th class="px-4 py-3 font-medium">{{ $t('meta.columna.pokemon') }}</th>
                                <th class="px-4 py-3 font-medium">{{ $t('meta.columna.n') }}</th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('matchup.columna.con', { rival: nombreEspecie }) }}
                                </th>
                                <th class="px-4 py-3 font-medium">{{ $t('matchup.columna.sin') }}</th>
                                <th class="px-4 py-3 font-medium">{{ $t('matchup.columna.delta') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="fila in matchups"
                                :key="fila.slug"
                                class="border-t border-carbon-700 hover:bg-carbon-800/60"
                            >
                                <td class="px-4 py-2">
                                    <RouterLink
                                        :to="{ name: 'especie', params: { slug: fila.slug } }"
                                        class="font-medium text-bone hover:text-sand"
                                    >
                                        {{ nombre(fila) }}
                                    </RouterLink>
                                </td>
                                <td class="cifra px-4 py-2 text-ash">{{ fila.n }}</td>
                                <td class="cifra px-4 py-2 text-bone">{{ fila.bring_pct.toFixed(1) }}%</td>
                                <td class="cifra px-4 py-2 text-ash">{{ fila.bring_pct_sin.toFixed(1) }}%</td>
                                <td class="px-4 py-2">
                                    <span class="flex items-center gap-2">
                                        <PctBar :pct="fila.delta" tono="signo" />
                                        <span
                                            v-if="fila.significativo"
                                            class="text-sand"
                                            :title="$t('matchup.significativo')"
                                            >*</span
                                        >
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="matchupMuestra?.ocultas" class="mt-3 text-xs text-ash-dim">
                    {{ $t('matchup.ocultas', { n: matchupMuestra.ocultas }) }}
                </p>
                <p class="mt-1 text-xs text-ash-dim">
                    {{ $t('matchup.descriptivo', { rival: nombreEspecie }) }}
                </p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-bone">{{ $t('conducta.titulo') }}</h2>
                <p class="mt-1 text-sm text-ash">{{ $t('conducta.entradilla') }}</p>

                <EmptySample v-if="contextos.length === 0" class="mt-4" :min="30" />

                <div v-else class="mt-4 grid gap-6 lg:grid-cols-[18rem_1fr]">
                    <ul class="space-y-1">
                        <li v-for="contexto in contextos" :key="`${contexto.rival}-${contexto.fase}`">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-md border px-3 py-2 text-left text-sm transition-colors"
                                :class="
                                    rivalElegido === contexto.rival && faseElegida === contexto.fase
                                        ? 'border-sand-dim bg-carbon-700 text-bone'
                                        : 'border-carbon-600 bg-carbon-800 text-ash hover:border-carbon-500 hover:text-bone'
                                "
                                @click="verConducta(contexto.rival, contexto.fase as 'apertura' | 'medio')"
                            >
                                <span>
                                    {{ nombre({ name: contexto.rival_name, name_es: contexto.rival_name_es }) }}
                                    <span class="block text-xs text-ash-dim">
                                        {{ $t(`conducta.${contexto.fase}`) }}
                                    </span>
                                </span>
                                <span class="cifra text-xs text-ash">{{ contexto.n }}</span>
                            </button>
                        </li>
                    </ul>

                    <div v-if="conducta" class="overflow-hidden rounded-lg border border-carbon-600">
                        <p class="cifra border-b border-carbon-700 bg-carbon-800 px-4 py-3 text-sm text-ash">
                            {{ conducta.muestra.n }} {{ $t('conducta.decisiones') }}
                        </p>
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="text-left text-xs tracking-wide text-ash uppercase">
                                    <th class="px-4 py-2 font-medium">{{ $t('conducta.columna.accion') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ $t('meta.columna.n') }}</th>
                                    <th class="px-4 py-2 font-medium">%</th>
                                    <th class="px-4 py-2 font-medium">{{ $t('conducta.columna.intervalo') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="accion in conducta.acciones"
                                    :key="accion.action_key"
                                    class="border-t border-carbon-700"
                                >
                                    <td class="px-4 py-2">
                                        <span v-if="accion.tipo === 'switch'" class="text-ash italic">
                                            {{ $t('conducta.cambiar') }}
                                        </span>
                                        <span v-else class="flex items-center gap-2">
                                            <TypeTag v-if="accion.move_type" :type="accion.move_type" />
                                            <span class="text-bone">{{ nombreAccion(accion) }}</span>
                                        </span>
                                    </td>
                                    <td class="cifra px-4 py-2 text-ash">{{ accion.n }}</td>
                                    <td class="px-4 py-2"><PctBar :pct="accion.pct" /></td>
                                    <td class="cifra px-4 py-2 text-xs text-ash-dim">
                                        {{ accion.intervalo[0].toFixed(1) }} – {{ accion.intervalo[1].toFixed(1) }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="border-t border-carbon-700 px-4 py-3 text-xs text-ash-dim">
                            {{ $t('conducta.solo_elegido') }}
                        </p>
                    </div>
                </div>
            </section>

            <p class="mt-10 text-xs text-ash-dim">{{ $t('fuente') }}</p>
        </template>
    </div>
</template>
