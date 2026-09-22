<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type FilaBringRate, type Muestra } from '@/api/cliente';
import { extremosDestacados, repartirEnBandas } from '@/design/bandas';
import BandSection from '@/components/BandSection.vue';
import Cargando from '@/components/Cargando.vue';
import EmptySample from '@/components/EmptySample.vue';
import PctBar from '@/components/PctBar.vue';
import Rotulo from '@/components/Rotulo.vue';
import SpeciesCard from '@/components/SpeciesCard.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const { locale } = useI18n();

const cargando = ref(true);
const fallo = ref(false);
const muestra = ref<Muestra | null>(null);
const filas = ref<FilaBringRate[]>([]);
const busqueda = ref('');
const tablaAbierta = ref(false);
const orden = ref<'llevado' | 'bring_pct' | 'lead_pct'>('llevado');

async function cargar(): Promise<void> {
    cargando.value = true;
    fallo.value = false;

    try {
        const respuesta = await api.bringRates(comunes.value);
        muestra.value = respuesta.muestra;
        filas.value = respuesta.especies;
    } catch {
        fallo.value = true;
    } finally {
        cargando.value = false;
    }
}

watch(comunes, cargar, { immediate: true });

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

const reparto = computed(() => repartirEnBandas(filas.value));
const destacados = computed(() => extremosDestacados(filas.value));

const coincidencias = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    if (texto === '') return [];

    return filas.value.filter((fila) => nombre(fila).toLowerCase().includes(texto)).slice(0, 12);
});

const ordenadas = computed(() => [...filas.value].sort((a, b) => b[orden.value] - a[orden.value]));

const columnas = [
    { clave: 'llevado', etiqueta: 'meta.columna.lleva' },
    { clave: 'bring_pct', etiqueta: 'meta.columna.trae' },
    { clave: 'lead_pct', etiqueta: 'meta.columna.lead' },
] as const;
</script>

<template>
    <div>
        <Cargando v-if="cargando" />
        <p v-else-if="fallo" class="text-sm text-clay">{{ $t('error') }}</p>

        <template v-else>
            <header class="mb-14 border-b border-line-soft pb-10">
                <p class="cifra rotulo mb-4">
                    {{ $t('meta.cabecera', { completos: muestra?.completos, total: muestra?.total }) }}
                </p>

                <h1
                    class="max-w-3xl text-4xl leading-[1.08] font-bold tracking-tight text-balance text-mist sm:text-[3.25rem]"
                >
                    {{ $t('meta.titulo') }}
                </h1>

                <div v-if="destacados" class="mt-8 flex flex-wrap gap-3">
                    <div
                        v-for="(fila, indice) in destacados"
                        :key="fila.slug"
                        class="superficie flex items-center gap-4 rounded-xl border border-line-soft py-3 pr-6 pl-4"
                    >
                        <SpeciesSprite
                            :sprite="fila.sprite"
                            :stone="fila.sprite_stone"
                            :name="nombre(fila)"
                            :size="56"
                        />
                        <div>
                            <p class="cifra text-2xl leading-none font-bold text-mist">
                                {{ fila.bring_pct.toFixed(1) }}%
                            </p>
                            <p class="mt-1 text-sm font-medium text-mist">{{ nombre(fila) }}</p>
                            <p class="text-[11px] text-fog-dim">
                                {{ $t(indice === 0 ? 'meta.destacado.mas' : 'meta.destacado.menos') }}
                            </p>
                        </div>
                    </div>
                </div>

                <p class="mt-8 max-w-2xl text-[15px] leading-relaxed text-fog">{{ $t('meta.lectura') }}</p>
            </header>

            <div class="mb-12">
                <input
                    v-model="busqueda"
                    type="search"
                    :placeholder="$t('meta.buscar')"
                    class="w-full max-w-sm rounded-lg border border-line bg-surface px-4 py-2.5 text-sm text-mist transition-colors placeholder:text-fog-dim hover:border-raised"
                />

                <div v-if="busqueda.trim() !== ''" class="mt-4">
                    <EmptySample v-if="coincidencias.length === 0" :min="muestra?.min_sample ?? 30" />
                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <SpeciesCard v-for="fila in coincidencias" :key="fila.slug" :fila="fila" />
                    </div>
                </div>
            </div>

            <template v-if="busqueda.trim() === ''">
                <BandSection v-for="banda in reparto.bandas" :key="banda.clave" :banda="banda" />

                <section>
                    <Rotulo :texto="$t('meta.tabla.titulo')" :nota="`${filas.length}`" />

                    <button
                        type="button"
                        class="text-sm text-fog transition-colors hover:text-amber"
                        @click="tablaAbierta = !tablaAbierta"
                    >
                        {{ tablaAbierta ? $t('meta.tabla.cerrar') : $t('meta.tabla.abrir', { n: filas.length }) }}
                    </button>

                    <div v-if="tablaAbierta" class="mt-5 overflow-x-auto rounded-xl border border-line-soft">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-surface text-left">
                                    <th class="rotulo px-4 py-3">{{ $t('meta.columna.pokemon') }}</th>
                                    <th v-for="columna in columnas" :key="columna.clave" class="px-4 py-3">
                                        <button
                                            type="button"
                                            class="rotulo hover:text-mist"
                                            :class="orden === columna.clave ? 'text-amber' : ''"
                                            @click="orden = columna.clave"
                                        >
                                            {{ $t(columna.etiqueta) }}
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="fila in ordenadas"
                                    :key="fila.slug"
                                    class="border-t border-line-soft hover:bg-surface/60"
                                >
                                    <td class="px-4 py-2">
                                        <RouterLink
                                            :to="{ name: 'especie', params: { slug: fila.slug } }"
                                            class="flex items-center gap-3"
                                        >
                                            <SpeciesSprite
                                                :sprite="fila.sprite"
                                                :stone="fila.sprite_stone"
                                                :name="nombre(fila)"
                                                :size="36"
                                            />
                                            <span>
                                                <span class="block font-medium text-mist">{{ nombre(fila) }}</span>
                                                <span class="mt-1 flex gap-1">
                                                    <TypeTag v-for="tipo in fila.types" :key="tipo" :type="tipo" />
                                                </span>
                                            </span>
                                        </RouterLink>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="cifra text-mist">{{ fila.usage_pct.toFixed(1) }}%</span>
                                        <span class="cifra ml-2 text-[11px] text-fog-dim">{{ fila.llevado }}</span>
                                    </td>
                                    <td class="px-4 py-2"><PctBar :pct="fila.bring_pct" /></td>
                                    <td class="px-4 py-2"><PctBar :pct="fila.lead_pct" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>

            <footer class="mt-12 space-y-1 border-t border-line-soft pt-6 text-xs text-fog-dim">
                <p>{{ $t('meta.descartados', { n: muestra?.descartados }) }}</p>
                <p>{{ $t('meta.cortes', { alto: reparto.corteAlto.toFixed(0), bajo: reparto.corteBajo.toFixed(0) }) }}</p>
            </footer>
        </template>
    </div>
</template>
