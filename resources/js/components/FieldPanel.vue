<script setup lang="ts">
import { computed } from 'vue';
import { type CampoEquipo, type CamposVistos } from '@/api/cliente';

const props = defineProps<{ campo: CampoEquipo; vistos: CamposVistos }>();

const puestos = computed(() =>
    [
        props.campo.clima ? { campo: props.campo.clima, quien: props.campo.clima_quien } : null,
        props.campo.terreno ? { campo: props.campo.terreno, quien: props.campo.terreno_quien } : null,
    ].filter((x): x is { campo: string; quien: string | null } => x !== null),
);

function frecuencia(campo: string): number | null {
    return props.vistos.campos.find((c) => c.campo === campo)?.pct ?? null;
}
</script>

<template>
    <section>
        <h3 class="mb-3 text-sm font-semibold text-mist">{{ $t('campo.titulo') }}</h3>

        <p v-if="puestos.length === 0" class="text-sm text-fog">{{ $t('campo.nadie') }}</p>

        <ul v-else class="mb-3 space-y-1.5">
            <li
                v-for="puesto in puestos"
                :key="puesto.campo"
                class="superficie flex items-center gap-3 rounded-lg border border-line-soft px-3 py-2"
            >
                <span class="min-w-0 flex-1 text-sm text-mist">
                    {{ $t('campo.pone', { quien: puesto.quien, campo: $t(`campos.${puesto.campo}`) }) }}
                </span>
                <span v-if="frecuencia(puesto.campo) !== null" class="cifra shrink-0 text-[11px] text-fog-dim">
                    {{ $t('campo.visto', { pct: frecuencia(puesto.campo) }) }}
                </span>
            </li>
        </ul>

        <p v-if="props.campo.aprovechan.length > 0" class="mb-2 text-[11px] leading-relaxed text-fog">
            {{ $t('campo.aprovechan') }}
            <span class="text-mist">
                {{
                    props.campo.aprovechan
                        .map((a) =>
                            a.bonus
                                .map((b) =>
                                    $t(`campo.bonus.${b.clave}`, { quien: a.quien, habilidad: b.habilidad ?? '' }),
                                )
                                .join(', '),
                        )
                        .join(' · ')
                }}
            </span>
        </p>

        <p
            v-for="choque in props.campo.choques"
            :key="choque.tipo"
            class="mb-2 rounded-lg border border-clay/25 bg-clay/5 px-3 py-2 text-[11px] leading-relaxed text-fog"
        >
            {{ $t('campo.choque', { quienes: choque.quienes.join(' y ') }) }}
        </p>

        <p
            v-for="huerfano in props.campo.huerfanos"
            :key="huerfano.quien"
            class="mb-2 rounded-lg border border-clay/25 bg-clay/5 px-3 py-2 text-[11px] leading-relaxed text-fog"
        >
            {{ $t('campo.huerfano', { quien: huerfano.quien, campo: $t(`campos.${huerfano.necesita}`) }) }}
        </p>

        <p v-if="props.campo.bloquea_prioridad" class="mb-2 text-[11px] leading-relaxed text-fog-dim">
            {{ $t('campo.bloquea_prioridad') }}
        </p>

        <details class="mt-4">
            <summary class="cursor-pointer text-[11px] text-fog-dim hover:text-fog">
                {{ $t('campo.frecuencias') }}
            </summary>
            <p class="mt-2 mb-2 text-[10px] leading-relaxed text-fog-dim">
                {{ $t('campo.frecuencias_texto', { n: props.vistos.partidas }) }}
            </p>
            <ul class="space-y-1">
                <li
                    v-for="visto in props.vistos.campos"
                    :key="visto.campo"
                    class="flex items-baseline gap-2 text-[11px]"
                >
                    <span class="w-36 shrink-0 text-fog">{{ $t(`campos.${visto.campo}`) }}</span>
                    <span class="cifra text-mist">{{ visto.pct }}%</span>
                </li>
            </ul>
        </details>
    </section>
</template>
