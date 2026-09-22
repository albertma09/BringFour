<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { api, type FilaBringRate, type Muestra } from '@/api/cliente';
import EmptySample from '@/components/EmptySample.vue';
import PctBar from '@/components/PctBar.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const { locale } = useI18n();

const cargando = ref(true);
const fallo = ref(false);
const muestra = ref<Muestra | null>(null);
const filas = ref<FilaBringRate[]>([]);
const busqueda = ref('');
const orden = ref<'n' | 'bring_pct' | 'lead_pct'>('n');

onMounted(async () => {
    try {
        const respuesta = await api.bringRates('?top=200');
        muestra.value = respuesta.muestra;
        filas.value = respuesta.especies;
    } catch {
        fallo.value = true;
    } finally {
        cargando.value = false;
    }
});

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

const visibles = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return filas.value
        .filter((fila) => texto === '' || nombre(fila).toLowerCase().includes(texto))
        .slice()
        .sort((a, b) => b[orden.value] - a[orden.value]);
});

const columnas = [
    { clave: 'n', etiqueta: 'meta.columna.n' },
    { clave: 'bring_pct', etiqueta: 'meta.columna.bring' },
    { clave: 'lead_pct', etiqueta: 'meta.columna.lead' },
] as const;
</script>

<template>
    <div>
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-bone">{{ $t('meta.titulo') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-ash">{{ $t('meta.entradilla') }}</p>
        </header>

        <p v-if="cargando" class="text-sm text-ash">{{ $t('cargando') }}…</p>
        <p v-else-if="fallo" class="text-sm text-clay">{{ $t('error') }}</p>

        <template v-else>
            <div
                v-if="muestra"
                class="mb-6 rounded-lg border border-carbon-600 bg-carbon-800 px-4 py-3 text-sm text-ash"
            >
                <p class="cifra text-bone">
                    {{ $t('meta.analizados', { completos: muestra.completos, total: muestra.total }) }}
                </p>
                <p class="mt-0.5">{{ $t('meta.descartados', { n: muestra.descartados }) }}</p>
            </div>

            <div class="mb-4 flex items-center gap-3">
                <input
                    v-model="busqueda"
                    type="search"
                    :placeholder="$t('meta.buscar')"
                    class="w-full max-w-xs rounded-md border border-carbon-600 bg-carbon-800 px-3 py-2 text-sm text-bone placeholder:text-ash-dim"
                />
            </div>

            <EmptySample v-if="visibles.length === 0" :min="muestra?.min_sample ?? 30" />

            <div v-else class="overflow-hidden rounded-lg border border-carbon-600">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-carbon-800 text-left text-xs tracking-wide text-ash uppercase">
                            <th class="px-4 py-3 font-medium">{{ $t('meta.columna.pokemon') }}</th>
                            <th
                                v-for="columna in columnas"
                                :key="columna.clave"
                                class="px-4 py-3 font-medium"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-bone"
                                    :class="orden === columna.clave ? 'text-sand' : ''"
                                    @click="orden = columna.clave"
                                >
                                    {{ $t(columna.etiqueta) }}
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="fila in visibles"
                            :key="fila.slug"
                            class="border-t border-carbon-700 transition-colors hover:bg-carbon-800/60"
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
                                        :size="40"
                                    />
                                    <span>
                                        <span class="block font-medium text-bone">{{ nombre(fila) }}</span>
                                        <span class="mt-1 flex gap-1">
                                            <TypeTag v-for="tipo in fila.types" :key="tipo" :type="tipo" />
                                        </span>
                                    </span>
                                </RouterLink>
                            </td>
                            <td class="cifra px-4 py-2 text-ash">{{ fila.n }}</td>
                            <td class="px-4 py-2"><PctBar :pct="fila.bring_pct" /></td>
                            <td class="px-4 py-2"><PctBar :pct="fila.lead_pct" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs text-ash-dim">{{ $t('fuente') }}</p>
        </template>
    </div>
</template>
