<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type RepartoSugerido } from '@/api/cliente';
import { type Reparto } from '@/design/stats';
import { STAT_EN, STAT_ES, type Stat } from '@/design/types';

const props = defineProps<{ slug: string; alineamiento: string | null }>();
const emit = defineEmits<{ aplicar: [Reparto] }>();

const { locale } = useI18n();

const cargando = ref(false);
const datos = ref<RepartoSugerido | null>(null);

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        datos.value = await api.reparto(props.slug, comunes.value, props.alineamiento);
    } catch {
        datos.value = null;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.slug, props.alineamiento, comunes.value], cargar, { immediate: true, deep: true });

function etiqueta(stat: string): string {
    return (locale.value === 'es' ? STAT_ES : STAT_EN)[stat as Stat] ?? stat;
}

function aplicar(): void {
    if (datos.value) emit('aplicar', { ...datos.value.sp } as Reparto);
}
</script>

<template>
    <div>
        <p v-if="cargando" class="text-[11px] text-fog-dim">{{ $t('cargando') }}…</p>

        <template v-else-if="datos">
            <p class="mb-3 text-[11px] leading-relaxed text-fog">
                {{ $t(`sugerencia.perfil.${datos.papel}_${datos.ritmo}`) }}
            </p>

            <ul class="mb-3 space-y-1">
                <li v-for="razon in datos.razones" :key="razon.stat" class="flex items-baseline gap-2 text-xs">
                    <span class="cifra w-7 shrink-0 text-right font-semibold text-mist">{{ razon.sp }}</span>
                    <span class="w-16 shrink-0 text-fog">{{ etiqueta(razon.stat) }}</span>
                    <span class="flex-1 text-[11px] text-fog-dim">{{ $t(`sugerencia.motivo.${razon.clave}`) }}</span>
                </li>
            </ul>

            <button
                type="button"
                class="w-full rounded-lg border border-line px-3 py-1.5 text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                @click="aplicar"
            >
                {{ $t('sugerencia.usar_reparto') }}
            </button>

            <p class="mt-2 text-[10px] leading-relaxed text-fog-dim">{{ $t('sugerencia.regla') }}</p>
        </template>
    </div>
</template>
