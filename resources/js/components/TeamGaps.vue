<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { type AnalisisEquipo } from '@/api/cliente';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ analisis: AnalisisEquipo }>();

const { locale } = useI18n();

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

function golpe(amenaza: { movimiento: string | null; movimiento_es: string | null }): string {
    return (locale.value === 'es' ? amenaza.movimiento_es : amenaza.movimiento) ?? amenaza.movimiento ?? '';
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h3 class="mb-1 text-sm font-semibold text-mist">{{ $t('equipo_analisis.compartidas') }}</h3>
            <p class="mb-3 text-[11px] leading-relaxed text-fog-dim">{{ $t('equipo_analisis.compartidas_texto') }}</p>

            <p v-if="props.analisis.compartidas.length === 0" class="text-sm text-fog">
                {{ $t('equipo_analisis.sin_compartidas') }}
            </p>

            <ul v-else class="space-y-1.5">
                <li
                    v-for="fila in props.analisis.compartidas"
                    :key="fila.tipo"
                    class="superficie flex items-center gap-3 rounded-lg border border-line-soft px-3 py-2"
                >
                    <TypeTag :type="fila.tipo" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs text-mist">{{ fila.miembros.join(' · ') }}</span>
                        <span class="cifra block text-[10px] text-fog-dim">
                            {{ $t('equipo_analisis.peso_tipo', { pct: fila.peso }) }}
                            <template v-if="fila.resisten === 0"> · {{ $t('equipo_analisis.nadie_resiste') }}</template>
                        </span>
                    </span>
                    <span
                        class="cifra shrink-0 rounded px-1.5 py-0.5 text-[11px]"
                        :class="fila.resisten === 0 ? 'bg-clay/15 text-clay' : 'bg-line-soft text-fog'"
                    >
                        ×{{ fila.cuantos }}
                    </span>
                </li>
            </ul>

            <p v-if="props.analisis.sin_resistir.length > 0" class="mt-4 text-[11px] leading-relaxed text-fog-dim">
                {{ $t('equipo_analisis.sin_resistir') }}
                <span class="text-fog">
                    {{ props.analisis.sin_resistir.map((f) => `${f.tipo} (${f.peso}%)`).join(' · ') }}
                </span>
            </p>
        </section>

        <section>
            <h3 class="mb-1 text-sm font-semibold text-mist">{{ $t('equipo_analisis.amenazas') }}</h3>
            <p class="mb-3 text-[11px] leading-relaxed text-fog-dim">{{ $t('equipo_analisis.amenazas_texto') }}</p>

            <p v-if="props.analisis.amenazas.length === 0" class="text-sm text-fog">
                {{ $t('equipo_analisis.sin_amenazas') }}
            </p>

            <ul v-else class="space-y-1.5">
                <li
                    v-for="amenaza in props.analisis.amenazas"
                    :key="amenaza.slug"
                    class="superficie flex items-center gap-3 rounded-lg border border-line-soft px-3 py-2"
                >
                    <SpeciesSprite
                        :sprite="amenaza.sprite"
                        :stone="amenaza.sprite_stone"
                        :name="nombre(amenaza)"
                        :size="32"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm text-mist">{{ nombre(amenaza) }}</span>
                        <span class="cifra block text-[10px] text-fog-dim">
                            {{ golpe(amenaza) }} · {{ $t('equipo_analisis.toca', { que: amenaza.toca.join(', ') }) }}
                        </span>
                    </span>
                    <span class="cifra shrink-0 rounded bg-clay/15 px-1.5 py-0.5 text-[11px] text-clay">
                        ×{{ amenaza.toca.length }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
