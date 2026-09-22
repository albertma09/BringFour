<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type ConjuntoVisto } from '@/api/cliente';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ slug: string }>();
const emit = defineEmits<{
    aplicar: [{ habilidad: string | null; objeto: string | null; movimientos: string[] }];
}>();

const { locale } = useI18n();

const cargando = ref(false);
const datos = ref<ConjuntoVisto | null>(null);

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        datos.value = await api.conjunto(props.slug, comunes.value);
    } catch {
        datos.value = null;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.slug, comunes.value], cargar, { immediate: true, deep: true });

const cuatro = computed(() => datos.value?.movimientos.slice(0, 4) ?? []);

const hayAlgo = computed(
    () => cuatro.value.length > 0 || (datos.value?.habilidades.length ?? 0) > 0 || (datos.value?.objetos.length ?? 0) > 0,
);

function aplicar(): void {
    emit('aplicar', {
        habilidad: datos.value?.habilidades[0]?.slug ?? null,
        objeto: datos.value?.objetos[0]?.slug ?? null,
        movimientos: cuatro.value.map((movimiento) => movimiento.slug),
    });
}
</script>

<template>
    <div>
        <p v-if="cargando" class="text-[11px] text-fog-dim">{{ $t('cargando') }}…</p>

        <p v-else-if="!hayAlgo" class="text-[11px] leading-relaxed text-fog-dim">
            {{ $t('sugerencia.sin_conjunto', { min: datos?.muestra.min_sample ?? 30 }) }}
        </p>

        <template v-else-if="datos">
            <p class="cifra mb-3 text-[11px] text-fog-dim">
                {{ $t('sugerencia.conjunto_base', { n: datos.traidas }) }}
            </p>

            <dl class="mb-3 space-y-2">
                <div v-if="datos.habilidades.length > 0" class="flex items-baseline gap-2">
                    <dt class="w-20 shrink-0 text-[10px] tracking-wider text-fog-dim uppercase">
                        {{ $t('constructor.habilidad') }}
                    </dt>
                    <dd class="text-xs text-mist">
                        {{ nombre(datos.habilidades[0]) }}
                        <span class="cifra text-fog-dim">
                            {{ datos.habilidades[0].pct }}% · N&nbsp;{{ datos.habilidades[0].n }}
                        </span>
                    </dd>
                </div>

                <div v-if="datos.objetos.length > 0" class="flex items-baseline gap-2">
                    <dt class="w-20 shrink-0 text-[10px] tracking-wider text-fog-dim uppercase">
                        {{ $t('constructor.objeto') }}
                    </dt>
                    <dd class="text-xs text-mist">
                        {{ nombre(datos.objetos[0]) }}
                        <span class="cifra text-fog-dim">
                            {{ datos.objetos[0].pct }}% · N&nbsp;{{ datos.objetos[0].n }}
                        </span>
                    </dd>
                </div>
            </dl>

            <ul v-if="cuatro.length > 0" class="mb-3 space-y-1">
                <li v-for="movimiento in cuatro" :key="movimiento.slug" class="flex items-center gap-2 text-xs">
                    <TypeTag :type="movimiento.type" />
                    <span class="flex-1 truncate text-mist">{{ nombre(movimiento) }}</span>
                    <span class="cifra text-fog-dim">{{ movimiento.pct }}% · N&nbsp;{{ movimiento.n }}</span>
                </li>
            </ul>

            <button
                type="button"
                class="w-full rounded-lg border border-line px-3 py-1.5 text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                @click="aplicar"
            >
                {{ $t('sugerencia.usar_conjunto') }}
            </button>

            <p class="mt-2 text-[10px] leading-relaxed text-fog-dim">{{ $t('sugerencia.sesgo_objeto') }}</p>
        </template>
    </div>
</template>
