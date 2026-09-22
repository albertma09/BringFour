<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { comunes } from '@/ajustes';
import { api, type Especie, type FilaMatchup, type Muestra } from '@/api/cliente';
import BehaviorPanel from '@/components/BehaviorPanel.vue';
import Cargando from '@/components/Cargando.vue';
import EmptySample from '@/components/EmptySample.vue';
import PctBar from '@/components/PctBar.vue';
import Rotulo from '@/components/Rotulo.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';

const { locale } = useI18n();
const route = useRoute();
const router = useRouter();

const catalogo = ref<Especie[]>([]);
const busqueda = ref('');
const elegido = ref<string>((route.query.vs as string) || 'indeedeef');
const cargando = ref(false);
const rival = ref<Especie | null>(null);
const muestra = ref<Muestra | null>(null);
const filas = ref<FilaMatchup[]>([]);

function nombre(fila: { name: string; name_es: string | null } | null): string {
    if (!fila) return '';

    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargarCatalogo(): Promise<void> {
    try {
        catalogo.value = (await api.catalogo(comunes.value)).especies;
    } catch {
        catalogo.value = [];
    }
}

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        const respuesta = await api.matchups(elegido.value, comunes.value);
        rival.value = respuesta.rival;
        muestra.value = respuesta.muestra;
        filas.value = respuesta.especies;
    } catch {
        rival.value = null;
        filas.value = [];
    } finally {
        cargando.value = false;
    }
}

function elegir(slug: string): void {
    elegido.value = slug;
    busqueda.value = '';
    router.replace({ query: { ...route.query, vs: slug } });
}

watch(comunes, cargarCatalogo, { immediate: true, deep: true });
watch([elegido, comunes], cargar, { immediate: true, deep: true });

const sugerencias = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    if (texto === '') return [];

    return catalogo.value.filter((e) => nombre(e).toLowerCase().includes(texto)).slice(0, 8);
});

const suben = computed(() => filas.value.filter((f) => f.delta > 0).slice(0, 10));
const bajan = computed(() => filas.value.filter((f) => f.delta < 0).slice(0, 10));
</script>

<template>
    <div>
        <header class="mb-10">
            <p class="rotulo mb-3">{{ $t('nav.matchups') }}</p>
            <h1 class="max-w-3xl text-3xl leading-tight font-bold tracking-tight text-balance text-mist sm:text-4xl">
                {{ $t('matchup.titulo') }}
            </h1>
            <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-fog">{{ $t('matchup.entradilla') }}</p>
        </header>

        <div class="mb-10 flex flex-wrap items-center gap-4">
            <div class="relative">
                <input
                    v-model="busqueda"
                    type="search"
                    :placeholder="$t('matchup.elegir')"
                    class="w-72 rounded-lg border border-line bg-surface px-4 py-2.5 text-sm text-mist placeholder:text-fog-dim"
                />
                <ul
                    v-if="sugerencias.length > 0"
                    class="absolute z-20 mt-1 w-72 overflow-hidden rounded-lg border border-line bg-card shadow-xl"
                >
                    <li v-for="especie in sugerencias" :key="especie.slug">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-mist hover:bg-raised"
                            @click="elegir(especie.slug)"
                        >
                            <SpeciesSprite
                                :sprite="especie.sprite"
                                :stone="especie.sprite_stone"
                                :name="nombre(especie)"
                                :size="28"
                            />
                            {{ nombre(especie) }}
                        </button>
                    </li>
                </ul>
            </div>

            <div v-if="rival" class="superficie flex items-center gap-3 rounded-xl border border-line-soft py-2 pr-5 pl-3">
                <SpeciesSprite
                    :sprite="rival.sprite"
                    :stone="rival.sprite_stone"
                    :name="nombre(rival)"
                    :size="48"
                />
                <div>
                    <p class="text-sm font-semibold text-mist">{{ nombre(rival) }}</p>
                    <p v-if="muestra" class="cifra text-[11px] text-fog-dim">
                        {{ $t('matchup.enfrentados', {
                            rival: nombre(rival),
                            enfrentados: muestra.enfrentados,
                            completos: muestra.completos,
                        }) }}
                    </p>
                </div>
            </div>
        </div>

        <Cargando v-if="cargando" />

        <template v-else>
            <EmptySample v-if="filas.length === 0" :min="muestra?.min_sample ?? 30" />

            <div v-else class="mb-14 grid gap-8 lg:grid-cols-2">
                <section>
                    <Rotulo :texto="$t('matchup.suben')" />
                    <p class="mb-4 text-sm text-fog">{{ $t('matchup.suben_texto', { rival: nombre(rival) }) }}</p>
                    <ul class="space-y-1">
                        <li
                            v-for="fila in suben"
                            :key="fila.slug"
                            class="flex items-center justify-between rounded-lg border border-line-soft bg-surface px-3 py-2"
                        >
                            <RouterLink
                                :to="{ name: 'especie', params: { slug: fila.slug } }"
                                class="text-sm font-medium text-mist hover:text-amber"
                            >
                                {{ nombre(fila) }}
                                <span v-if="fila.significativo" class="text-amber" :title="$t('matchup.significativo')"
                                    >*</span
                                >
                            </RouterLink>
                            <span class="flex items-center gap-3">
                                <span class="cifra text-[11px] text-fog-dim">
                                    {{ fila.bring_pct_sin.toFixed(0) }}% → {{ fila.bring_pct.toFixed(0) }}%
                                </span>
                                <PctBar :pct="fila.delta" tono="signo" />
                            </span>
                        </li>
                    </ul>
                </section>

                <section>
                    <Rotulo :texto="$t('matchup.bajan')" />
                    <p class="mb-4 text-sm text-fog">{{ $t('matchup.bajan_texto', { rival: nombre(rival) }) }}</p>
                    <ul class="space-y-1">
                        <li
                            v-for="fila in bajan"
                            :key="fila.slug"
                            class="flex items-center justify-between rounded-lg border border-line-soft bg-surface px-3 py-2"
                        >
                            <RouterLink
                                :to="{ name: 'especie', params: { slug: fila.slug } }"
                                class="text-sm font-medium text-mist hover:text-amber"
                            >
                                {{ nombre(fila) }}
                                <span v-if="fila.significativo" class="text-amber" :title="$t('matchup.significativo')"
                                    >*</span
                                >
                            </RouterLink>
                            <span class="flex items-center gap-3">
                                <span class="cifra text-[11px] text-fog-dim">
                                    {{ fila.bring_pct_sin.toFixed(0) }}% → {{ fila.bring_pct.toFixed(0) }}%
                                </span>
                                <PctBar :pct="fila.delta" tono="signo" />
                            </span>
                        </li>
                    </ul>
                </section>
            </div>

            <p class="mb-14 max-w-2xl text-xs text-fog-dim">
                {{ $t('matchup.descriptivo', { rival: nombre(rival) }) }}
                {{ muestra?.ocultas ? $t('matchup.ocultas', { n: muestra.ocultas }) : '' }}
            </p>

            <section>
                <Rotulo :texto="$t('conducta.titulo')" />
                <p class="mb-5 max-w-2xl text-sm text-fog">
                    {{ $t('conducta.entradilla_matchup', { rival: nombre(rival) }) }}
                </p>
                <BehaviorPanel :slug="elegido" />
            </section>
        </template>
    </div>
</template>
