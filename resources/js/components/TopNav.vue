<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ajustes, CORTES_ELO } from '@/ajustes';
import { api, type Formato } from '@/api/cliente';
import { cambiarIdioma } from '@/i18n';

const { locale } = useI18n();

const formatos = ref<Formato[]>([]);
const abierto = ref(false);

const secciones = [
    { nombre: 'meta', clave: 'nav.meta' },
    { nombre: 'matchups', clave: 'nav.matchups' },
    { nombre: 'equipos', clave: 'nav.equipos' },
    { nombre: 'constructor', clave: 'nav.constructor' },
    { nombre: 'pokedex', clave: 'nav.pokedex' },
] as const;

const cortes = computed(() => formatos.value.find((f) => f.showdown_id === ajustes.format)?.cortes ?? {});

function muestraDe(corte: number): number {
    return cortes.value[String(corte)] ?? 0;
}

onMounted(async () => {
    try {
        formatos.value = (await api.formatos()).formatos;
    } catch {
        formatos.value = [];
    }
});
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-line-soft bg-night/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-6 px-5 py-3">
            <RouterLink :to="{ name: 'meta' }" class="flex shrink-0 items-baseline gap-2">
                <span class="text-[17px] font-bold tracking-tight text-mist">{{ $t('marca') }}</span>
                <span class="hidden text-[11px] text-fog-dim lg:inline">{{ $t('lema') }}</span>
            </RouterLink>

            <nav class="hidden flex-1 items-center gap-1 md:flex">
                <RouterLink
                    v-for="seccion in secciones"
                    :key="seccion.nombre"
                    :to="{ name: seccion.nombre }"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-fog transition-colors hover:bg-surface hover:text-mist"
                    active-class="bg-surface text-mist"
                >
                    {{ $t(seccion.clave) }}
                </RouterLink>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <select
                    v-model="ajustes.format"
                    class="rounded-md border border-line bg-surface px-2 py-1.5 text-xs text-mist"
                    :aria-label="$t('ajustes.formato')"
                >
                    <option v-for="formato in formatos" :key="formato.showdown_id" :value="formato.showdown_id">
                        {{ formato.regulacion }} · {{ formato.battle_type }}
                    </option>
                </select>

                <select
                    v-model.number="ajustes.elo"
                    class="rounded-md border border-line bg-surface px-2 py-1.5 text-xs text-mist"
                    :aria-label="$t('ajustes.elo')"
                >
                    <option
                        v-for="corte in CORTES_ELO"
                        :key="corte"
                        :value="corte"
                        :disabled="muestraDe(corte) === 0"
                    >
                        {{ corte === 0 ? $t('ajustes.todos') : `${corte}+` }} ({{ muestraDe(corte) }})
                    </option>
                </select>

                <div class="flex overflow-hidden rounded-md border border-line">
                    <button
                        v-for="idioma in ['es', 'en']"
                        :key="idioma"
                        type="button"
                        class="px-2 py-1.5 text-[11px] font-semibold transition-colors"
                        :class="locale === idioma ? 'bg-raised text-mist' : 'bg-surface text-fog hover:text-mist'"
                        @click="cambiarIdioma(idioma as 'es' | 'en')"
                    >
                        {{ idioma.toUpperCase() }}
                    </button>
                </div>

                <button
                    type="button"
                    class="rounded-md border border-line bg-surface px-2 py-1.5 text-xs text-fog md:hidden"
                    @click="abierto = !abierto"
                >
                    {{ abierto ? '×' : '≡' }}
                </button>
            </div>
        </div>

        <nav v-if="abierto" class="border-t border-line-soft px-5 py-2 md:hidden">
            <RouterLink
                v-for="seccion in secciones"
                :key="seccion.nombre"
                :to="{ name: seccion.nombre }"
                class="block rounded-md px-3 py-2 text-sm text-fog hover:bg-surface hover:text-mist"
                active-class="text-mist"
                @click="abierto = false"
            >
                {{ $t(seccion.clave) }}
            </RouterLink>
        </nav>
    </header>
</template>
