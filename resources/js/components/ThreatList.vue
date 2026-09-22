<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Amenaza, type Amenazas } from '@/api/cliente';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ slug: string }>();

const { locale } = useI18n();

const cargando = ref(false);
const datos = ref<Amenazas | null>(null);

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

function golpe(amenaza: Amenaza): string {
    return locale.value === 'es' ? (amenaza.movimiento_es ?? amenaza.movimiento) : amenaza.movimiento;
}

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        datos.value = await api.amenazas(props.slug, comunes.value);
    } catch {
        datos.value = null;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.slug, comunes.value], cargar, { immediate: true, deep: true });
</script>

<template>
    <div>
        <p v-if="cargando" class="text-sm text-fog">{{ $t('cargando') }}…</p>

        <template v-else-if="datos">
            <div class="mb-5 flex flex-wrap gap-x-6 gap-y-2 text-xs">
                <span v-if="Object.keys(datos.debilidades).length > 0" class="flex items-center gap-1.5">
                    <span class="text-fog-dim">{{ $t('taller.debil') }}</span>
                    <TypeTag v-for="(x, tipo) in datos.debilidades" :key="tipo" :type="String(tipo)" />
                </span>
                <span v-if="Object.keys(datos.resistencias).length > 0" class="flex items-center gap-1.5">
                    <span class="text-fog-dim">{{ $t('taller.resiste') }}</span>
                    <TypeTag v-for="(x, tipo) in datos.resistencias" :key="tipo" :type="String(tipo)" />
                </span>
            </div>

            <section v-if="datos.confirmadas.length > 0" class="mb-6">
                <h3 class="mb-1 text-sm font-semibold text-mist">{{ $t('taller.confirmadas') }}</h3>
                <p class="mb-3 text-xs text-fog">{{ $t('taller.confirmadas_texto') }}</p>

                <ul class="space-y-1.5">
                    <li
                        v-for="amenaza in datos.confirmadas"
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
                            <span class="cifra block text-[11px] text-fog-dim">
                                {{ golpe(amenaza) }} ×{{ amenaza.x }} · {{ $t('taller.visto', { n: amenaza.n }) }}
                            </span>
                        </span>
                        <span
                            class="cifra shrink-0 rounded px-1.5 py-0.5 text-[10px]"
                            :class="amenaza.antes ? 'bg-clay/15 text-clay' : 'bg-line-soft text-fog-dim'"
                        >
                            {{ amenaza.antes ? $t('taller.antes') : $t('taller.despues') }}
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="datos.posibles.length > 0">
                <h3 class="mb-1 text-sm font-semibold text-mist">{{ $t('taller.posibles') }}</h3>
                <p class="mb-3 text-xs text-fog">{{ $t('taller.posibles_texto') }}</p>

                <ul class="flex flex-wrap gap-1.5">
                    <li
                        v-for="amenaza in datos.posibles"
                        :key="amenaza.slug"
                        class="flex items-center gap-1.5 rounded-lg border border-dashed border-line px-2 py-1 text-[11px] text-fog"
                    >
                        <SpeciesSprite
                            :sprite="amenaza.sprite"
                            :stone="amenaza.sprite_stone"
                            :name="nombre(amenaza)"
                            :size="22"
                        />
                        {{ nombre(amenaza) }}
                        <span class="cifra text-fog-dim">{{ golpe(amenaza) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
