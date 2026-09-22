<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Core, type EquipoResumen, type Muestra } from '@/api/cliente';
import Cargando from '@/components/Cargando.vue';
import EmptySample from '@/components/EmptySample.vue';
import Rotulo from '@/components/Rotulo.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';

const { locale } = useI18n();

const cargando = ref(true);
const tamano = ref<2 | 3 | 4>(2);
const cores = ref<Core[]>([]);
const equipos = ref<EquipoResumen[]>([]);
const muestra = ref<Muestra | null>(null);

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargarCores(): Promise<void> {
    try {
        cores.value = (await api.cores(comunes.value, tamano.value)).cores;
    } catch {
        cores.value = [];
    }
}

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        const respuesta = await api.equipos(comunes.value);
        equipos.value = respuesta.equipos;
        muestra.value = respuesta.muestra;
        await cargarCores();
    } catch {
        equipos.value = [];
    } finally {
        cargando.value = false;
    }
}

watch(comunes, cargar, { immediate: true, deep: true });
watch(tamano, cargarCores);
</script>

<template>
    <div>
        <header class="mb-12">
            <p class="rotulo mb-3">{{ $t('nav.equipos') }}</p>
            <h1 class="max-w-3xl text-3xl leading-tight font-bold tracking-tight text-balance text-mist sm:text-4xl">
                {{ $t('equipos.titulo') }}
            </h1>
            <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-fog">{{ $t('equipos.entradilla') }}</p>
        </header>

        <Cargando v-if="cargando" />

        <template v-else>
            <section class="mb-14">
                <Rotulo :texto="$t('equipos.cores')" />

                <div class="mb-5 flex items-center gap-3">
                    <div class="flex overflow-hidden rounded-lg border border-line">
                        <button
                            v-for="opcion in [2, 3, 4]"
                            :key="opcion"
                            type="button"
                            class="px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="tamano === opcion ? 'bg-raised text-mist' : 'bg-surface text-fog hover:text-mist'"
                            @click="tamano = opcion as 2 | 3 | 4"
                        >
                            {{ opcion }}
                        </button>
                    </div>
                    <p class="text-sm text-fog">{{ $t('equipos.cores_texto', { n: tamano }) }}</p>
                </div>

                <EmptySample v-if="cores.length === 0" :min="muestra?.min_sample ?? 30" />

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(core, indice) in cores"
                        :key="indice"
                        class="superficie flex items-center justify-between gap-3 rounded-xl border border-line-soft px-4 py-3"
                    >
                        <span class="flex items-center gap-1">
                            <SpeciesSprite
                                v-for="miembro in core.miembros"
                                :key="miembro.slug"
                                :sprite="miembro.sprite"
                                :stone="miembro.sprite_stone"
                                :name="nombre(miembro)"
                                :size="40"
                            />
                        </span>
                        <span class="cifra shrink-0 text-lg font-bold text-mist">{{ core.n }}</span>
                    </div>
                </div>
            </section>

            <section>
                <Rotulo :texto="$t('equipos.compos')" :nota="`${equipos.length}`" />
                <p class="mb-5 max-w-2xl text-sm text-fog">{{ $t('equipos.compos_texto') }}</p>

                <EmptySample v-if="equipos.length === 0" :min="muestra?.min_sample ?? 30" />

                <ul v-else class="space-y-2">
                    <li v-for="equipo in equipos" :key="equipo.id">
                        <RouterLink
                            :to="{ name: 'equipo', params: { id: equipo.id } }"
                            class="superficie flex items-center justify-between gap-4 rounded-xl border border-line-soft px-4 py-3 transition-colors hover:border-line hover:bg-raised"
                        >
                            <span class="flex flex-wrap items-center gap-1">
                                <SpeciesSprite
                                    v-for="miembro in equipo.miembros"
                                    :key="miembro.slug"
                                    :sprite="miembro.sprite"
                                    :stone="miembro.sprite_stone"
                                    :name="nombre(miembro)"
                                    :size="44"
                                />
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="cifra block text-xl font-bold text-mist">{{ equipo.n }}</span>
                                <span class="block text-[11px] text-fog-dim">{{ $t('equipos.veces') }}</span>
                            </span>
                        </RouterLink>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
