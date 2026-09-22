<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Especie } from '@/api/cliente';
import Cargando from '@/components/Cargando.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';
import { TYPE_COLORS, TYPE_ES } from '@/design/types';

const { locale } = useI18n();

const cargando = ref(true);
const especies = ref<Especie[]>([]);
const busqueda = ref('');
const tipo = ref<string>('');

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        especies.value = (await api.catalogo(comunes.value)).especies;
    } catch {
        especies.value = [];
    } finally {
        cargando.value = false;
    }
}

watch(comunes, cargar, { immediate: true, deep: true });

function nombre(fila: Especie): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

const visibles = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return especies.value.filter(
        (e) =>
            (texto === '' || nombre(e).toLowerCase().includes(texto)) &&
            (tipo.value === '' || e.types.includes(tipo.value)),
    );
});
</script>

<template>
    <div>
        <header class="mb-8">
            <p class="rotulo mb-3">{{ $t('nav.pokedex') }}</p>
            <h1 class="text-3xl font-bold tracking-tight text-mist">{{ $t('pokedex.titulo') }}</h1>
            <p class="mt-2 text-sm text-fog">{{ $t('pokedex.entradilla') }}</p>
        </header>

        <div class="mb-6 flex flex-wrap items-center gap-3">
            <input
                v-model="busqueda"
                type="search"
                :placeholder="$t('meta.buscar')"
                class="w-64 rounded-lg border border-line bg-surface px-4 py-2.5 text-sm text-mist placeholder:text-fog-dim"
            />
            <div class="flex flex-wrap gap-1">
                <button
                    type="button"
                    class="rounded px-2 py-1 text-[10px] font-semibold tracking-wide uppercase transition-colors"
                    :class="tipo === '' ? 'bg-raised text-mist' : 'text-fog hover:text-mist'"
                    @click="tipo = ''"
                >
                    {{ $t('pokedex.todos') }}
                </button>
                <button
                    v-for="(color, clave) in TYPE_COLORS"
                    :key="clave"
                    type="button"
                    class="rounded px-2 py-1 text-[10px] font-semibold tracking-wide uppercase transition-opacity"
                    :style="{
                        backgroundColor: `${color}${tipo === clave ? '40' : '1a'}`,
                        color,
                        opacity: tipo === '' || tipo === clave ? 1 : 0.45,
                    }"
                    @click="tipo = tipo === clave ? '' : clave"
                >
                    {{ locale === 'es' ? TYPE_ES[clave] : clave }}
                </button>
            </div>
        </div>

        <Cargando v-if="cargando" />

        <template v-else>
            <p class="cifra mb-4 text-xs text-fog-dim">{{ $t('pokedex.contador', { n: visibles.length }) }}</p>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                <RouterLink
                    v-for="especie in visibles"
                    :key="especie.slug"
                    :to="{ name: 'especie', params: { slug: especie.slug } }"
                    class="superficie flex items-center gap-2 rounded-lg border border-line-soft px-2 py-2 transition-colors hover:border-line hover:bg-raised"
                >
                    <SpeciesSprite
                        :sprite="especie.sprite"
                        :stone="especie.sprite_stone"
                        :name="nombre(especie)"
                        :size="40"
                    />
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-medium text-mist">{{ nombre(especie) }}</span>
                        <span class="mt-0.5 flex gap-0.5">
                            <TypeTag v-for="t in especie.types" :key="t" :type="t" />
                        </span>
                    </span>
                </RouterLink>
            </div>
        </template>
    </div>
</template>
