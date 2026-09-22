<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type FichaEspecie, type FilaMatchup, type Muestra } from '@/api/cliente';
import BehaviorPanel from '@/components/BehaviorPanel.vue';
import NoDataWorkshop from '@/components/NoDataWorkshop.vue';
import Cargando from '@/components/Cargando.vue';
import EmptySample from '@/components/EmptySample.vue';
import PctBar from '@/components/PctBar.vue';
import Rotulo from '@/components/Rotulo.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';
import { STAT_EN, STAT_ES, STATS, typeColor } from '@/design/types';

const props = defineProps<{ slug: string }>();
const { locale } = useI18n();

const cargando = ref(true);
const fallo = ref(false);
const especie = ref<FichaEspecie | null>(null);
const muestra = ref<Muestra | null>(null);
const matchups = ref<FilaMatchup[]>([]);
const pestana = ref<'datos' | 'matchups' | 'conducta' | 'taller'>('datos');

function nombre(fila: { name: string; name_es: string | null } | null): string {
    if (!fila) return '';

    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargar(): Promise<void> {
    cargando.value = true;
    fallo.value = false;
    pestana.value = 'datos';

    try {
        const [ficha, matchup] = await Promise.all([
            api.especie(props.slug, comunes.value),
            api.matchups(props.slug, comunes.value),
        ]);

        especie.value = ficha.especie;
        muestra.value = matchup.muestra;
        matchups.value = matchup.especies;
    } catch {
        fallo.value = true;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.slug, comunes.value], cargar, { immediate: true, deep: true });

const nombreEspecie = computed(() => nombre(especie.value));
const canto = computed(() => typeColor(especie.value?.types[0] ?? ''));
const etiquetaStat = computed(() => (locale.value === 'es' ? STAT_ES : STAT_EN));

const pestanas = [
    { clave: 'datos', etiqueta: 'ficha.pestana.datos' },
    { clave: 'matchups', etiqueta: 'ficha.pestana.matchups' },
    { clave: 'conducta', etiqueta: 'ficha.pestana.conducta' },
    { clave: 'taller', etiqueta: 'ficha.pestana.taller' },
] as const;
</script>

<template>
    <div>
        <Cargando v-if="cargando" />
        <p v-else-if="fallo || !especie" class="text-sm text-clay">{{ $t('error') }}</p>

        <template v-else>
            <header
                class="superficie relative mb-8 overflow-hidden rounded-2xl border border-line-soft px-6 py-6"
            >
                <span
                    class="pointer-events-none absolute -top-16 -right-10 h-48 w-48 rounded-full opacity-[0.1] blur-3xl"
                    :style="{ backgroundColor: canto }"
                    aria-hidden="true"
                />
                <div class="flex flex-wrap items-center gap-6">
                    <SpeciesSprite
                        :sprite="especie.sprite"
                        :stone="especie.sprite_stone"
                        :name="nombreEspecie"
                        :size="104"
                    />
                    <div>
                        <p v-if="especie.national_dex" class="cifra text-[11px] text-fog-dim">
                            #{{ String(especie.national_dex).padStart(4, '0') }}
                        </p>
                        <h1 class="text-3xl font-bold tracking-tight text-mist">{{ nombreEspecie }}</h1>
                        <div class="mt-2 flex gap-1">
                            <TypeTag v-for="tipo in especie.types" :key="tipo" :type="tipo" />
                        </div>
                        <p v-if="!especie.legal" class="mt-2 text-sm text-clay">{{ $t('ficha.ilegal') }}</p>
                        <p v-if="especie.megapiedra" class="mt-2 text-sm text-fog">
                            {{ $t('ficha.megapiedra') }}: {{ nombre(especie.megapiedra) }}
                        </p>
                    </div>
                </div>
            </header>

            <nav class="mb-8 flex gap-1 border-b border-line-soft">
                <button
                    v-for="p in pestanas"
                    :key="p.clave"
                    type="button"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                    :class="
                        pestana === p.clave
                            ? 'border-amber text-mist'
                            : 'border-transparent text-fog hover:text-mist'
                    "
                    @click="pestana = p.clave"
                >
                    {{ $t(p.etiqueta) }}
                </button>
            </nav>

            <section v-if="pestana === 'datos'" class="grid gap-10 sm:grid-cols-2">
                <div>
                    <Rotulo :texto="$t('ficha.estadisticas')" />
                    <dl class="space-y-2">
                        <div v-for="stat in STATS" :key="stat" class="flex items-center gap-3">
                            <dt class="w-20 text-xs text-fog">{{ etiquetaStat[stat] }}</dt>
                            <dd class="cifra w-8 text-right text-sm font-semibold text-mist">
                                {{ especie.base_stats?.[stat] ?? '—' }}
                            </dd>
                            <dd class="h-2 w-full max-w-48 overflow-hidden rounded-full bg-line-soft">
                                <span
                                    class="block h-full rounded-full"
                                    :style="{
                                        width: `${Math.min(100, ((especie.base_stats?.[stat] ?? 0) / 180) * 100)}%`,
                                        backgroundColor: canto,
                                    }"
                                />
                            </dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <Rotulo :texto="$t('ficha.habilidades')" />
                    <ul class="space-y-1 text-sm text-mist">
                        <li v-for="habilidad in especie.abilities" :key="habilidad.slug">{{ nombre(habilidad) }}</li>
                    </ul>
                </div>
            </section>

            <section v-else-if="pestana === 'matchups'">
                <p v-if="muestra" class="cifra mb-5 text-sm text-fog">
                    {{
                        $t('matchup.enfrentados', {
                            rival: nombreEspecie,
                            enfrentados: muestra.enfrentados,
                            completos: muestra.completos,
                        })
                    }}
                </p>

                <EmptySample v-if="matchups.length === 0" :min="muestra?.min_sample ?? 30" />

                <div v-else class="overflow-x-auto rounded-xl border border-line-soft">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-surface text-left">
                                <th class="rotulo px-4 py-3">{{ $t('meta.columna.pokemon') }}</th>
                                <th class="rotulo px-4 py-3">{{ $t('meta.columna.n') }}</th>
                                <th class="rotulo px-4 py-3">
                                    {{ $t('matchup.columna.con', { rival: nombreEspecie }) }}
                                </th>
                                <th class="rotulo px-4 py-3">{{ $t('matchup.columna.sin') }}</th>
                                <th class="rotulo px-4 py-3">{{ $t('matchup.columna.delta') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="fila in matchups"
                                :key="fila.slug"
                                class="border-t border-line-soft hover:bg-surface/60"
                            >
                                <td class="px-4 py-2">
                                    <RouterLink
                                        :to="{ name: 'especie', params: { slug: fila.slug } }"
                                        class="font-medium text-mist hover:text-amber"
                                    >
                                        {{ nombre(fila) }}
                                    </RouterLink>
                                </td>
                                <td class="cifra px-4 py-2 text-fog">{{ fila.n }}</td>
                                <td class="cifra px-4 py-2 text-mist">{{ fila.bring_pct.toFixed(1) }}%</td>
                                <td class="cifra px-4 py-2 text-fog">{{ fila.bring_pct_sin.toFixed(1) }}%</td>
                                <td class="px-4 py-2">
                                    <span class="flex items-center gap-2">
                                        <PctBar :pct="fila.delta" tono="signo" />
                                        <span
                                            v-if="fila.significativo"
                                            class="text-amber"
                                            :title="$t('matchup.significativo')"
                                            >*</span
                                        >
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 max-w-2xl text-xs text-fog-dim">
                    {{ $t('matchup.descriptivo', { rival: nombreEspecie }) }}
                </p>
            </section>

            <section v-else-if="pestana === 'conducta'">
                <p class="mb-5 max-w-2xl text-sm text-fog">
                    {{ $t('conducta.entradilla_ficha', { especie: nombreEspecie }) }}
                </p>
                <BehaviorPanel :slug="slug" />
            </section>

            <section v-else>
                <p class="mb-5 max-w-2xl text-sm leading-relaxed text-fog">{{ $t('taller.entradilla') }}</p>
                <NoDataWorkshop :slug="slug" />
            </section>
        </template>
    </div>
</template>
