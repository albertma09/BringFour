<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type EquipoDetalle } from '@/api/cliente';
import Cargando from '@/components/Cargando.vue';
import PctBar from '@/components/PctBar.vue';
import Rotulo from '@/components/Rotulo.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import TypeTag from '@/components/TypeTag.vue';

const props = defineProps<{ id: string }>();
const { locale } = useI18n();

const cargando = ref(true);
const fallo = ref(false);
const equipo = ref<EquipoDetalle | null>(null);
const copiado = ref(false);

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

async function cargar(): Promise<void> {
    cargando.value = true;
    fallo.value = false;

    try {
        equipo.value = (await api.equipo(props.id, comunes.value)).equipo;
    } catch {
        fallo.value = true;
    } finally {
        cargando.value = false;
    }
}

watch(() => [props.id, comunes.value], cargar, { immediate: true, deep: true });

const pegado = computed(() => (equipo.value?.miembros ?? []).map((m) => `${m.name}\nLevel: 50\n`).join('\n'));

async function copiar(): Promise<void> {
    try {
        await navigator.clipboard.writeText(pegado.value);
        copiado.value = true;
        setTimeout(() => (copiado.value = false), 2000);
    } catch {
        copiado.value = false;
    }
}
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'equipos' }" class="mb-6 inline-block text-sm text-fog hover:text-amber">
            ← {{ $t('equipos.volver') }}
        </RouterLink>

        <Cargando v-if="cargando" />
        <p v-else-if="fallo || !equipo" class="text-sm text-clay">{{ $t('error') }}</p>

        <template v-else>
            <header class="mb-10">
                <p class="rotulo mb-3">{{ $t('equipos.ficha') }}</p>
                <h1 class="text-3xl font-bold tracking-tight text-mist">
                    {{ $t('equipos.repetido', { n: equipo.n }) }}
                </h1>
                <p class="mt-2 text-sm text-fog">
                    {{ $t('equipos.completos', { completos: equipo.completos, n: equipo.n }) }}
                </p>
            </header>

            <section class="mb-12">
                <Rotulo :texto="$t('equipos.que_traen')" />
                <p class="mb-5 max-w-2xl text-sm text-fog">{{ $t('equipos.que_traen_texto') }}</p>

                <ul class="space-y-2">
                    <li
                        v-for="miembro in equipo.miembros"
                        :key="miembro.slug"
                        class="superficie flex items-center gap-4 rounded-xl border border-line-soft px-4 py-3"
                    >
                        <SpeciesSprite
                            :sprite="miembro.sprite"
                            :stone="miembro.sprite_stone"
                            :name="nombre(miembro)"
                            :size="52"
                        />
                        <div class="min-w-0 flex-1">
                            <RouterLink
                                :to="{ name: 'especie', params: { slug: miembro.slug } }"
                                class="font-semibold text-mist hover:text-amber"
                            >
                                {{ nombre(miembro) }}
                            </RouterLink>
                            <div class="mt-1 flex gap-1">
                                <TypeTag v-for="tipo in miembro.types" :key="tipo" :type="tipo" />
                            </div>
                        </div>
                        <div class="w-40 shrink-0">
                            <p class="mb-1 text-[10px] tracking-wider text-fog-dim uppercase">
                                {{ $t('meta.columna.trae') }}
                            </p>
                            <PctBar :pct="miembro.bring_pct" />
                        </div>
                        <div class="hidden w-40 shrink-0 sm:block">
                            <p class="mb-1 text-[10px] tracking-wider text-fog-dim uppercase">
                                {{ $t('meta.columna.lead') }}
                            </p>
                            <PctBar :pct="miembro.lead_pct" />
                        </div>
                    </li>
                </ul>
            </section>

            <section>
                <Rotulo :texto="$t('equipos.exportar')" />

                <div class="rounded-xl border border-line-soft bg-surface p-5">
                    <p class="mb-4 max-w-2xl text-sm leading-relaxed text-fog">{{ $t('equipos.sin_codigo') }}</p>

                    <button
                        type="button"
                        class="rounded-lg border border-amber-dim bg-raised px-4 py-2 text-sm font-medium text-mist transition-colors hover:border-amber"
                        @click="copiar"
                    >
                        {{ copiado ? $t('equipos.copiado') : $t('equipos.copiar') }}
                    </button>

                    <pre class="mt-4 overflow-x-auto rounded-lg bg-night p-4 text-xs leading-relaxed text-fog">{{ pegado }}</pre>
                </div>
            </section>
        </template>
    </div>
</template>
