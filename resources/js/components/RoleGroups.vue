<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type CompaneroEstructural } from '@/api/cliente';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = withDefaults(
    defineProps<{ slug?: string | null; equipo?: string[]; anadible?: boolean; porEquipo?: boolean }>(),
    { slug: null, equipo: () => [], anadible: false, porEquipo: false },
);
const emit = defineEmits<{ anadir: [string] }>();

const { locale } = useI18n();

const cargando = ref(false);
const datos = ref<{ cubos: Record<string, CompaneroEstructural[]> } | null>(null);

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargar(): Promise<void> {
    if (props.porEquipo && props.equipo.length === 0) {
        datos.value = null;

        return;
    }

    if (!props.porEquipo && !props.slug) {
        datos.value = null;

        return;
    }

    cargando.value = true;

    try {
        datos.value = props.porEquipo
            ? await api.equipoCompaneros(comunes.value, props.equipo)
            : await api.estructurales(props.slug as string, comunes.value, props.equipo);
    } catch {
        datos.value = null;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.slug, props.equipo, props.porEquipo, comunes.value], cargar, { immediate: true, deep: true });

function razon(companero: CompaneroEstructural): string {
    return companero.razones
        .map((r) => String(r.tipos?.join(', ') ?? r.movimientos?.join(', ') ?? ''))
        .filter((texto) => texto !== '')
        .join(' · ');
}
</script>

<template>
    <div>
        <p v-if="cargando" class="text-sm text-fog">{{ $t('cargando') }}…</p>

        <template v-else-if="datos">
            <section v-for="(lista, cubo) in datos.cubos" :key="cubo" class="mb-6">
                <header class="mb-1 flex items-baseline gap-3">
                    <h3 class="text-sm font-semibold text-mist">{{ $t(`papel.cubo.${cubo}`) }}</h3>
                    <span class="h-px flex-1 bg-line-soft" aria-hidden="true" />
                </header>
                <p class="mb-3 text-[11px] leading-relaxed text-fog-dim">{{ $t(`papel.texto.${cubo}`) }}</p>

                <ul class="grid gap-2 sm:grid-cols-2">
                    <li
                        v-for="companero in lista"
                        :key="companero.slug"
                        class="superficie flex items-center gap-3 rounded-lg border border-line-soft px-3 py-2"
                    >
                        <SpeciesSprite
                            :sprite="companero.sprite"
                            :stone="companero.sprite_stone"
                            :name="nombre(companero)"
                            :size="36"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="truncate text-sm text-mist">{{ nombre(companero) }}</span>
                                <TypeTag v-for="tipo in companero.tipos" :key="tipo" :type="tipo" />
                            </span>
                            <span v-if="companero.cierre" class="cifra block text-[11px] text-amber">
                                {{ $t('papel.cierre', { pct: companero.cierre.pct, n: companero.cierre.ganadas }) }}
                            </span>
                            <span v-if="razon(companero)" class="block truncate text-[10px] text-fog-dim">
                                {{ razon(companero) }}
                            </span>
                        </span>
                        <button
                            v-if="anadible"
                            type="button"
                            class="shrink-0 rounded border border-line px-2 py-1 text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                            @click="emit('anadir', companero.slug)"
                        >
                            {{ $t('sugerencia.anadir') }}
                        </button>
                    </li>
                </ul>
            </section>

            <p class="text-[11px] leading-relaxed text-fog-dim">{{ $t('papel.cierre_aviso') }}</p>
        </template>
    </div>
</template>
