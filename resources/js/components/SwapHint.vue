<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { type Cambio } from '@/api/cliente';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ cambio: Cambio }>();
const emit = defineEmits<{ sustituir: [{ sale: string; entra: string }] }>();

const { locale } = useI18n();

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}
</script>

<template>
    <div v-if="props.cambio.propuesta" class="rounded-xl border border-amber/25 bg-amber/5 p-4">
        <p class="mb-3 text-sm leading-relaxed text-mist">
            {{
                $t('cambio.titular', {
                    sale: nombre(props.cambio.propuesta.sale),
                    pareja: props.cambio.propuesta.pareja ? nombre(props.cambio.propuesta.pareja) : '',
                })
            }}
        </p>

        <p class="mb-4 text-[11px] leading-relaxed text-fog">
            {{
                props.cambio.propuesta.arregla.tipo
                    ? $t(`cambio.arregla.${props.cambio.propuesta.arregla.clave}`, {
                          tipo: props.cambio.propuesta.arregla.tipo,
                          n: props.cambio.propuesta.arregla.cuantos ?? 0,
                          pct: props.cambio.propuesta.arregla.peso,
                      })
                    : $t('cambio.arregla.papel', { papel: $t(`papel.cubo.${props.cambio.propuesta.arregla.papel}`) })
            }}
        </p>

        <p
            v-if="props.cambio.propuesta.sale.aporta.resistencias.length === 0"
            class="mb-4 text-[11px] leading-relaxed text-fog-dim"
        >
            {{ $t('cambio.nada_unico', { sale: nombre(props.cambio.propuesta.sale) }) }}
        </p>

        <ul class="grid gap-2 sm:grid-cols-2">
            <li
                v-for="entra in props.cambio.propuesta.entran"
                :key="entra.slug"
                class="superficie flex items-center gap-3 rounded-lg border border-line-soft px-3 py-2"
            >
                <SpeciesSprite :sprite="entra.sprite" :stone="entra.sprite_stone" :name="nombre(entra)" :size="36" />
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-1.5">
                        <span class="truncate text-sm text-mist">{{ nombre(entra) }}</span>
                        <TypeTag v-for="tipo in entra.tipos" :key="tipo" :type="tipo" />
                    </span>
                    <span v-if="entra.cierre" class="cifra block text-[10px] text-amber">
                        {{ $t('papel.cierre', { pct: entra.cierre.pct, n: entra.cierre.ganadas }) }}
                    </span>
                </span>
                <button
                    type="button"
                    class="shrink-0 rounded border border-line px-2 py-1 text-[11px] text-fog transition-colors hover:border-amber hover:text-amber"
                    @click="emit('sustituir', { sale: props.cambio.propuesta!.sale.slug, entra: entra.slug })"
                >
                    {{ $t('cambio.cambiar') }}
                </button>
            </li>
        </ul>

        <p class="mt-3 text-[10px] leading-relaxed text-fog-dim">{{ $t('cambio.aviso') }}</p>
    </div>

    <div v-else-if="props.cambio.redundantes.length > 0" class="rounded-xl border border-line-soft bg-surface/60 p-4">
        <p class="text-sm text-mist">{{ $t('cambio.equilibrado') }}</p>
        <p class="mt-2 text-[11px] leading-relaxed text-fog-dim">
            {{ $t('cambio.parejas') }}
            {{ props.cambio.redundantes.map((p) => `${nombre(p.a)} + ${nombre(p.b)}`).join(' · ') }}
        </p>
    </div>
</template>
