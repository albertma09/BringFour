<script setup lang="ts">
import { ref, watch } from 'vue';
import { comunes } from '@/ajustes';
import { api, type AnalisisEquipo } from '@/api/cliente';
import FieldPanel from '@/components/FieldPanel.vue';
import SwapHint from '@/components/SwapHint.vue';
import TeamGaps from '@/components/TeamGaps.vue';

const props = defineProps<{ equipo: string[] }>();
const emit = defineEmits<{ sustituir: [{ sale: string; entra: string }] }>();

const cargando = ref(false);
const datos = ref<AnalisisEquipo | null>(null);

async function cargar(): Promise<void> {
    if (props.equipo.length === 0) {
        datos.value = null;

        return;
    }

    cargando.value = true;

    try {
        datos.value = await api.equipoAnalisis(comunes.value, props.equipo);
    } catch {
        datos.value = null;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.equipo, comunes.value], cargar, { immediate: true, deep: true });

defineExpose({ recargar: cargar });
</script>

<template>
    <div>
        <p v-if="cargando" class="text-sm text-fog">{{ $t('cargando') }}…</p>

        <template v-else-if="datos">
            <p class="mb-5 max-w-3xl text-sm leading-relaxed text-fog">
                {{
                    $t(`equipo_analisis.ritmo.${datos.velocidad.perfil}`, {
                        pct: (100 - datos.velocidad.percentil).toFixed(0),
                    })
                }}
                <template v-if="datos.velocidad.sin_control"> {{ $t('equipo_analisis.sin_control') }}</template>
                <template v-if="datos.reparto.sesgado">
                    {{ $t(`equipo_analisis.sesgo.${datos.reparto.sesgado}`, {
                        f: datos.reparto.fisicos,
                        e: datos.reparto.especiales,
                    }) }}
                </template>
            </p>

            <p v-if="datos.papeles.faltan.length > 0" class="mb-6 text-sm text-fog">
                {{ $t('equipo_analisis.faltan') }}
                <span class="text-mist">
                    {{ datos.papeles.faltan.map((p) => $t(`papel.cubo.${p}`)).join(' · ') }}
                </span>
            </p>

            <FieldPanel :campo="datos.campo" :vistos="datos.campos_vistos" class="mb-8" />

            <TeamGaps :analisis="datos" class="mb-8" />

            <SwapHint :cambio="datos.cambio" @sustituir="emit('sustituir', $event)" />
        </template>
    </div>
</template>
