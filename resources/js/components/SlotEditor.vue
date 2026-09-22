<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Alineamiento, type Especie, type Habilidad, type Movimiento, type ObjetoBuilder } from '@/api/cliente';
import SetSuggest from '@/components/SetSuggest.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import SpreadSuggest from '@/components/SpreadSuggest.vue';
import TypeTag from '@/components/TypeTag.vue';
import { restantes, SP_TOPE, SP_TOTAL, valorFinal, type Reparto } from '@/design/stats';
import { STAT_EN, STAT_ES, STATS, type Stat } from '@/design/types';

export interface Hueco {
    slug: string | null;
    sp: Reparto;
    alineamiento: string | null;
    objeto: string | null;
    habilidad: string | null;
    movimientos: string[];
}

const props = defineProps<{ hueco: Hueco; catalogo: Especie[]; alineamientos: Alineamiento[] }>();
const emit = defineEmits<{ cambiar: [Hueco]; quitar: [] }>();

const { locale } = useI18n();

const busqueda = ref('');
const ayuda = ref(false);
const opciones = ref<{ movimientos: Movimiento[]; objetos: ObjetoBuilder[]; abilities: Habilidad[] } | null>(null);
const buscarMovimiento = ref('');

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

const especie = computed(() => props.catalogo.find((e) => e.slug === props.hueco.slug) ?? null);
const etiquetaStat = computed(() => (locale.value === 'es' ? STAT_ES : STAT_EN));

const alineamiento = computed(() =>
    props.alineamientos.find((a) => a.slug === props.hueco.alineamiento) ?? null,
);

const sugerencias = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    if (texto === '') return [];

    return props.catalogo.filter((e) => nombre(e).toLowerCase().includes(texto)).slice(0, 8);
});

const movimientosFiltrados = computed(() => {
    const texto = buscarMovimiento.value.trim().toLowerCase();
    const lista = opciones.value?.movimientos ?? [];

    if (texto === '') return lista.slice(0, 40);

    return lista.filter((m) => nombre(m).toLowerCase().includes(texto)).slice(0, 40);
});

async function cargarOpciones(): Promise<void> {
    if (!props.hueco.slug) {
        opciones.value = null;

        return;
    }

    try {
        const respuesta = await api.opciones(props.hueco.slug, comunes.value);
        opciones.value = {
            movimientos: respuesta.movimientos,
            objetos: respuesta.objetos,
            abilities: respuesta.especie.abilities,
        };
    } catch {
        opciones.value = null;
    }
}

watch(() => [props.hueco.slug, comunes.value], cargarOpciones, { immediate: true, deep: true });

function actualizar(cambios: Partial<Hueco>): void {
    emit('cambiar', { ...props.hueco, ...cambios });
}

function elegirEspecie(slug: string): void {
    busqueda.value = '';
    emit('cambiar', {
        slug,
        sp: { hp: 0, atk: 0, def: 0, spa: 0, spd: 0, spe: 0 },
        alineamiento: null,
        objeto: null,
        habilidad: null,
        movimientos: [],
    });
}

function ajustarSp(stat: Stat, valor: number): void {
    const limpio = Math.max(0, Math.min(SP_TOPE, Math.round(valor)));
    const sp = { ...props.hueco.sp, [stat]: limpio };

    if (SP_TOTAL - Object.values(sp).reduce((t, v) => t + v, 0) < 0) return;

    actualizar({ sp });
}

function alternarMovimiento(slug: string): void {
    const actuales = props.hueco.movimientos;

    if (actuales.includes(slug)) {
        actualizar({ movimientos: actuales.filter((m) => m !== slug) });

        return;
    }

    if (actuales.length >= 4) return;

    actualizar({ movimientos: [...actuales, slug] });
}

const sobran = computed(() => restantes(props.hueco.sp));

function aplicarConjunto(conjunto: { habilidad: string | null; objeto: string | null; movimientos: string[] }): void {
    const disponibles = (opciones.value?.abilities ?? []).map((h) => h.slug);
    const habilidad = conjunto.habilidad && disponibles.includes(conjunto.habilidad) ? conjunto.habilidad : props.hueco.habilidad;

    actualizar({
        habilidad,
        objeto: conjunto.objeto ?? props.hueco.objeto,
        movimientos: conjunto.movimientos.slice(0, 4),
    });
}

function aplicarReparto(sp: Reparto): void {
    actualizar({ sp });
}
</script>

<template>
    <div class="superficie rounded-xl border border-line-soft p-4">
        <template v-if="!especie">
            <p class="rotulo mb-3">{{ $t('constructor.hueco_vacio') }}</p>
            <input
                v-model="busqueda"
                type="search"
                :placeholder="$t('constructor.elegir')"
                class="w-full rounded-lg border border-line bg-night px-3 py-2 text-sm text-mist placeholder:text-fog-dim"
            />
            <ul v-if="sugerencias.length > 0" class="mt-2 space-y-1">
                <li v-for="opcion in sugerencias" :key="opcion.slug">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm text-mist hover:bg-raised"
                        @click="elegirEspecie(opcion.slug)"
                    >
                        <SpeciesSprite
                            :sprite="opcion.sprite"
                            :stone="opcion.sprite_stone"
                            :name="nombre(opcion)"
                            :size="28"
                        />
                        {{ nombre(opcion) }}
                    </button>
                </li>
            </ul>
        </template>

        <template v-else>
            <div class="mb-4 flex items-start gap-3">
                <SpeciesSprite
                    :sprite="especie.sprite"
                    :stone="especie.sprite_stone"
                    :name="nombre(especie)"
                    :size="56"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-mist">{{ nombre(especie) }}</p>
                    <div class="mt-1 flex gap-1">
                        <TypeTag v-for="tipo in especie.types" :key="tipo" :type="tipo" />
                    </div>
                </div>
                <button
                    type="button"
                    class="text-xs text-fog-dim hover:text-clay"
                    :title="$t('constructor.quitar')"
                    @click="emit('quitar')"
                >
                    ×
                </button>
            </div>

            <div class="mb-4 grid gap-2 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-[10px] tracking-wider text-fog-dim uppercase">
                        {{ $t('constructor.habilidad') }}
                    </span>
                    <select
                        :value="hueco.habilidad ?? ''"
                        class="w-full rounded-lg border border-line bg-night px-2 py-1.5 text-xs text-mist"
                        @change="actualizar({ habilidad: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">—</option>
                        <option v-for="h in opciones?.abilities ?? []" :key="h.slug" :value="h.slug">
                            {{ nombre(h) }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-[10px] tracking-wider text-fog-dim uppercase">
                        {{ $t('constructor.objeto') }}
                    </span>
                    <select
                        :value="hueco.objeto ?? ''"
                        class="w-full rounded-lg border border-line bg-night px-2 py-1.5 text-xs text-mist"
                        @change="actualizar({ objeto: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">—</option>
                        <option v-for="o in opciones?.objetos ?? []" :key="o.slug" :value="o.slug">
                            {{ nombre(o) }}{{ o.mega ? ' ★' : '' }}
                        </option>
                    </select>
                </label>
            </div>

            <label class="mb-4 block">
                <span class="mb-1 block text-[10px] tracking-wider text-fog-dim uppercase">
                    {{ $t('constructor.alineamiento') }}
                </span>
                <select
                    :value="hueco.alineamiento ?? ''"
                    class="w-full rounded-lg border border-line bg-night px-2 py-1.5 text-xs text-mist"
                    @change="actualizar({ alineamiento: ($event.target as HTMLSelectElement).value || null })"
                >
                    <option value="">—</option>
                    <option v-for="a in alineamientos" :key="a.slug" :value="a.slug">
                        {{ a.name }}{{ a.neutral ? '' : ` (+${a.plus} −${a.minus})` }}
                    </option>
                </select>
            </label>

            <div class="mb-4">
                <div class="mb-2 flex items-baseline justify-between">
                    <span class="text-[10px] tracking-wider text-fog-dim uppercase">
                        {{ $t('constructor.sp') }}
                    </span>
                    <span class="cifra text-xs" :class="sobran === 0 ? 'text-moss' : 'text-fog'">
                        {{ $t('constructor.restantes', { n: sobran }) }}
                    </span>
                </div>

                <div v-for="stat in STATS" :key="stat" class="mb-1.5 flex items-center gap-2">
                    <span class="w-16 text-[11px] text-fog">{{ etiquetaStat[stat] }}</span>
                    <input
                        type="range"
                        min="0"
                        :max="SP_TOPE"
                        :value="hueco.sp[stat]"
                        class="h-1 flex-1 accent-amber"
                        @input="ajustarSp(stat, Number(($event.target as HTMLInputElement).value))"
                    />
                    <span class="cifra w-6 text-right text-[11px] text-fog">{{ hueco.sp[stat] }}</span>
                    <span class="cifra w-10 text-right text-xs font-semibold text-mist">
                        {{
                            valorFinal(
                                stat,
                                especie.base_stats?.[stat] ?? 0,
                                hueco.sp[stat],
                                alineamiento?.plus ?? null,
                                alineamiento?.minus ?? null,
                            )
                        }}
                    </span>
                </div>
            </div>

            <div class="mb-4">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg border border-line-soft px-3 py-2 text-left text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                    :aria-expanded="ayuda"
                    @click="ayuda = !ayuda"
                >
                    <span>{{ $t('sugerencia.no_lo_tengo_claro') }}</span>
                    <span aria-hidden="true">{{ ayuda ? '−' : '+' }}</span>
                </button>

                <div v-if="ayuda" class="mt-3 space-y-4 rounded-lg border border-line-soft bg-night/40 p-3">
                    <section>
                        <p class="rotulo mb-2">{{ $t('sugerencia.lo_que_se_ve') }}</p>
                        <SetSuggest :slug="especie.slug" @aplicar="aplicarConjunto" />
                    </section>

                    <section class="border-t border-line-soft pt-3">
                        <p class="rotulo mb-2">{{ $t('sugerencia.reparto_por_reglas') }}</p>
                        <SpreadSuggest
                            :slug="especie.slug"
                            :alineamiento="hueco.alineamiento"
                            @aplicar="aplicarReparto"
                        />
                    </section>
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-baseline justify-between">
                    <span class="text-[10px] tracking-wider text-fog-dim uppercase">
                        {{ $t('constructor.movimientos') }}
                    </span>
                    <span class="cifra text-xs text-fog">{{ hueco.movimientos.length }}/4</span>
                </div>

                <input
                    v-model="buscarMovimiento"
                    type="search"
                    :placeholder="$t('constructor.buscar_movimiento')"
                    class="mb-2 w-full rounded-lg border border-line bg-night px-2 py-1.5 text-xs text-mist placeholder:text-fog-dim"
                />

                <div class="max-h-40 overflow-y-auto pr-1">
                    <button
                        v-for="movimiento in movimientosFiltrados"
                        :key="movimiento.slug"
                        type="button"
                        class="mb-1 flex w-full items-center gap-2 rounded px-2 py-1 text-left text-xs transition-colors"
                        :class="
                            hueco.movimientos.includes(movimiento.slug)
                                ? 'bg-raised text-mist'
                                : 'text-fog hover:bg-surface hover:text-mist'
                        "
                        @click="alternarMovimiento(movimiento.slug)"
                    >
                        <TypeTag :type="movimiento.type" />
                        <span class="flex-1 truncate">{{ nombre(movimiento) }}</span>
                        <span v-if="movimiento.power" class="cifra text-fog-dim">{{ movimiento.power }}</span>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
