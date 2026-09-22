<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Analisis, type MovimientoTaller } from '@/api/cliente';
import RoleGroups from '@/components/RoleGroups.vue';
import ThreatList from '@/components/ThreatList.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = withDefaults(
    defineProps<{ slug: string; aplicable?: boolean; equipo?: string[]; anadible?: boolean }>(),
    { aplicable: false, equipo: () => [], anadible: false },
);
const emit = defineEmits<{
    aplicar: [{ habilidad: string | null; movimientos: string[] }];
    anadir: [string];
}>();

const { locale } = useI18n();

const cargando = ref(false);
const analisis = ref<Analisis | null>(null);

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        analisis.value = await api.analisis(props.slug, comunes.value);
    } catch {
        analisis.value = null;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.slug, comunes.value], cargar, { immediate: true, deep: true });

function etiquetas(movimiento: MovimientoTaller): string[] {
    const salida: string[] = [];

    if (movimiento.stab) salida.push('STAB');
    if (movimiento.habilidad) salida.push(movimiento.habilidad);
    if (movimiento.area) salida.push('área');
    if (movimiento.infalible) salida.push('100%');

    return salida;
}

function aplicar(): void {
    if (!analisis.value) return;

    emit('aplicar', {
        habilidad: analisis.value.conjunto.habilidad,
        movimientos: analisis.value.conjunto.movimientos.map((m) => m.slug),
    });
}
</script>

<template>
    <div>
        <p v-if="cargando" class="text-sm text-fog">{{ $t('cargando') }}…</p>

        <template v-else-if="analisis">
            <p class="mb-5 rounded-lg border border-amber/25 bg-amber/5 px-3 py-2 text-[11px] leading-relaxed text-fog">
                {{ $t('taller.aviso') }}
            </p>

            <section class="mb-6">
                <p class="rotulo mb-2">{{ $t('taller.lectura') }}</p>
                <p class="mb-2 text-sm text-mist">
                    {{ $t(`papel.eje.${analisis.papel.eje}`) }}
                    <span v-if="analisis.cierre" class="cifra text-amber">
                        · {{ $t('papel.cierre', { pct: analisis.cierre.pct, n: analisis.cierre.ganadas }) }}
                    </span>
                </p>
                <p class="text-sm leading-relaxed text-fog">
                    {{
                        $t(`taller.ritmo.${analisis.velocidad.ritmo}`, {
                            base: analisis.velocidad.base,
                            pct: (100 - analisis.velocidad.percentil).toFixed(0),
                            n: analisis.velocidad.muestra,
                        })
                    }}
                </p>
            </section>

            <section class="mb-6">
                <p class="rotulo mb-2">{{ $t('taller.conjunto') }}</p>

                <ul class="mb-3 space-y-1.5">
                    <li
                        v-for="movimiento in analisis.conjunto.movimientos"
                        :key="movimiento.slug"
                        class="superficie flex items-center gap-2 rounded-lg border border-line-soft px-2.5 py-1.5"
                    >
                        <TypeTag :type="movimiento.type" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-mist">{{ nombre(movimiento) }}</span>
                            <span class="block text-[10px] text-fog-dim">
                                {{ $t(`taller.motivo.${movimiento.motivo}`) }}
                                <template v-if="etiquetas(movimiento).length > 0">
                                    · {{ etiquetas(movimiento).join(' · ') }}
                                </template>
                            </span>
                        </span>
                        <span v-if="movimiento.efectiva" class="cifra shrink-0 text-xs text-amber">
                            {{ movimiento.efectiva }}
                        </span>
                    </li>
                </ul>

                <button
                    v-if="aplicable"
                    type="button"
                    class="w-full rounded-lg border border-line px-3 py-1.5 text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                    @click="aplicar"
                >
                    {{ $t('taller.usar') }}
                </button>

                <p v-if="analisis.conjunto.alternativas.length > 0" class="mt-3 text-[11px] leading-relaxed text-fog-dim">
                    {{ $t('taller.alternativas') }}
                    {{ analisis.conjunto.alternativas.map((m) => nombre(m)).join(' · ') }}
                </p>
            </section>

            <section class="mb-6">
                <p class="rotulo mb-2">{{ $t('taller.cobertura') }}</p>
                <p class="text-sm text-fog">
                    {{ $t('taller.cobertura_texto', { pct: analisis.conjunto.cobertura.pct, n: analisis.meta.especies }) }}
                </p>
                <p
                    v-if="analisis.conjunto.cobertura.resisten.length > 0"
                    class="mt-2 text-[11px] leading-relaxed text-fog-dim"
                >
                    {{ $t('taller.resisten') }}
                    {{ analisis.conjunto.cobertura.resisten.map((r) => nombre(r)).join(' · ') }}
                </p>
            </section>

            <section class="mb-6">
                <p class="rotulo mb-2">{{ $t('taller.amenazas') }}</p>
                <ThreatList :slug="slug" />
            </section>

            <section>
                <p class="rotulo mb-2">{{ $t('taller.companeros') }}</p>
                <p class="mb-4 text-xs leading-relaxed text-fog">{{ $t('taller.companeros_texto') }}</p>
                <RoleGroups :slug="slug" :equipo="equipo" :anadible="anadible" @anadir="emit('anadir', $event)" />
            </section>

        </template>
    </div>
</template>
