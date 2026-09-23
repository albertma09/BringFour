<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { type OfensivaEquipo } from '@/api/cliente';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ ofensiva: OfensivaEquipo }>();

const { locale } = useI18n();

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}
</script>

<template>
    <section>
        <h3 class="mb-1 text-sm font-semibold text-mist">{{ $t('ofensiva.titulo') }}</h3>
        <p class="mb-4 text-[11px] leading-relaxed text-fog-dim">{{ $t('ofensiva.texto') }}</p>

        <p class="mb-4 text-sm leading-relaxed" :class="props.ofensiva.pobre ? 'text-clay' : 'text-fog'">
            {{ $t('ofensiva.pct', { pct: props.ofensiva.pct, n: props.ofensiva.meta.especies }) }}
        </p>

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <p class="rotulo mb-2">{{ $t('ofensiva.intocables') }}</p>

                <p v-if="props.ofensiva.intocables.length === 0" class="text-sm text-fog">
                    {{ $t('ofensiva.sin_intocables') }}
                </p>

                <ul v-else class="space-y-1.5">
                    <li
                        v-for="rival in props.ofensiva.intocables"
                        :key="rival.slug"
                        class="superficie flex items-center gap-3 rounded-lg border border-line-soft px-3 py-2"
                    >
                        <SpeciesSprite
                            :sprite="rival.sprite"
                            :stone="rival.sprite_stone"
                            :name="nombre(rival)"
                            :size="32"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="truncate text-sm text-mist">{{ nombre(rival) }}</span>
                                <TypeTag v-for="tipo in rival.tipos" :key="tipo" :type="tipo" />
                            </span>
                            <span v-if="rival.inmune" class="cifra block text-[10px] text-clay">
                                {{ $t('ofensiva.inmune') }}
                            </span>
                        </span>
                        <span class="cifra shrink-0 rounded bg-line-soft px-1.5 py-0.5 text-[11px] text-fog">
                            ×{{ rival.x }}
                        </span>
                    </li>
                </ul>
            </div>

            <div>
                <p class="rotulo mb-2">{{ $t('ofensiva.falta') }}</p>

                <p v-if="props.ofensiva.mejor_anadido.length === 0" class="mb-4 text-sm text-fog">
                    {{ $t('ofensiva.sin_falta') }}
                </p>

                <ul v-else class="mb-5 space-y-1.5">
                    <li
                        v-for="anadido in props.ofensiva.mejor_anadido"
                        :key="anadido.tipo"
                        class="flex items-center gap-3"
                    >
                        <TypeTag :type="anadido.tipo" />
                        <span class="cifra text-[11px] text-amber">
                            {{ $t('ofensiva.ganancia', { pts: anadido.ganancia }) }}
                        </span>
                    </li>
                </ul>

                <template v-if="props.ofensiva.repetidos.length > 0">
                    <p class="rotulo mb-2">{{ $t('ofensiva.repetidos') }}</p>
                    <p
                        v-for="repetido in props.ofensiva.repetidos"
                        :key="repetido.tipo"
                        class="mb-1.5 flex flex-wrap items-baseline gap-x-2 text-[11px] leading-relaxed text-fog-dim"
                    >
                        <TypeTag :type="repetido.tipo" />
                        <span>
                            {{ $t('ofensiva.repetido', { n: repetido.cuantos, quienes: repetido.quienes.join(', ') }) }}
                        </span>
                    </p>
                    <p class="mt-2 text-[10px] leading-relaxed text-fog-dim">{{ $t('ofensiva.repetido_aviso') }}</p>
                </template>
            </div>
        </div>

        <details class="mt-5">
            <summary class="cursor-pointer text-[11px] text-fog-dim hover:text-fog">
                {{ $t('ofensiva.fuentes') }}
            </summary>
            <p class="mt-2 mb-2 text-[10px] leading-relaxed text-fog-dim">{{ $t('ofensiva.fuentes_texto') }}</p>
            <ul class="space-y-1">
                <li
                    v-for="fuente in props.ofensiva.fuentes"
                    :key="fuente.slug"
                    class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-[11px]"
                >
                    <span class="w-28 shrink-0 truncate text-mist">{{ nombre(fuente) }}</span>
                    <span class="cifra shrink-0" :class="fuente.origen === 'visto' ? 'text-fog' : 'text-amber'">
                        {{
                            fuente.origen === 'visto'
                                ? $t('ofensiva.origen_visto', { n: fuente.n })
                                : $t('ofensiva.origen_deducido')
                        }}
                    </span>
                    <span class="flex flex-wrap items-center gap-1">
                        <TypeTag v-for="tipo in fuente.tipos" :key="tipo" :type="tipo" />
                    </span>
                </li>
            </ul>
        </details>
    </section>
</template>
