<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { comunes } from '@/ajustes';
import { api, type Alineamiento, type Especie, type FilaBringRate } from '@/api/cliente';
import BehaviorPanel from '@/components/BehaviorPanel.vue';
import Cargando from '@/components/Cargando.vue';
import PartnerSuggest from '@/components/PartnerSuggest.vue';
import Rotulo from '@/components/Rotulo.vue';
import SlotEditor, { type Hueco } from '@/components/SlotEditor.vue';
import SpeciesSprite from '@/components/SpeciesSprite.vue';
import { repartoVacio } from '@/design/stats';

const { locale } = useI18n();

const GUARDADO = 'bringfour:equipo';

function huecoVacio(): Hueco {
    return { slug: null, sp: repartoVacio(), alineamiento: null, objeto: null, habilidad: null, movimientos: [] };
}

function inicial(): Hueco[] {
    try {
        const crudo = localStorage.getItem(GUARDADO);
        if (crudo) {
            const leido = JSON.parse(crudo) as Hueco[];
            if (Array.isArray(leido) && leido.length === 6) return leido;
        }
    } catch {
        // el navegador puede tener el almacenamiento bloqueado
    }

    return Array.from({ length: 6 }, huecoVacio);
}

const cargando = ref(true);
const catalogo = ref<Especie[]>([]);
const alineamientos = ref<Alineamiento[]>([]);
const meta = ref<FilaBringRate[]>([]);
const huecos = ref<Hueco[]>(inicial());
const enfoque = ref<number | null>(null);

async function cargar(): Promise<void> {
    cargando.value = true;

    try {
        const [especies, alin, bring] = await Promise.all([
            api.catalogo(comunes.value),
            api.alineamientos(),
            api.bringRates(comunes.value),
        ]);

        catalogo.value = especies.especies;
        alineamientos.value = alin.alineamientos;
        meta.value = bring.especies;
    } catch {
        catalogo.value = [];
    } finally {
        cargando.value = false;
    }
}

watch(comunes, cargar, { immediate: true, deep: true });

watch(
    huecos,
    (valor) => {
        try {
            localStorage.setItem(GUARDADO, JSON.stringify(valor));
        } catch {
            // sin almacenamiento el equipo dura lo que la sesion
        }
    },
    { deep: true },
);

function cambiar(indice: number, hueco: Hueco): void {
    huecos.value[indice] = hueco;
    enfoque.value = indice;
}

function quitar(indice: number): void {
    huecos.value[indice] = huecoVacio();

    if (enfoque.value === indice) enfoque.value = null;
}

function vaciar(): void {
    huecos.value = Array.from({ length: 6 }, huecoVacio);
    enfoque.value = null;
}

function nombre(fila: { name: string; name_es: string | null }): string {
    return locale.value === 'es' ? (fila.name_es ?? fila.name) : fila.name;
}

const elegidos = computed(() => huecos.value.filter((h) => h.slug !== null));

const slugsElegidos = computed(() => elegidos.value.map((h) => h.slug as string));

const primerLibre = computed(() => huecos.value.findIndex((h) => h.slug === null));

function anadir(slug: string): void {
    const indice = primerLibre.value;

    if (indice < 0) return;

    huecos.value[indice] = { ...huecoVacio(), slug };
    enfoque.value = indice;
}

const enfocado = computed(() => {
    if (enfoque.value === null) return null;

    return huecos.value[enfoque.value]?.slug ?? null;
});

const datosMeta = computed(() =>
    elegidos.value
        .map((h) => meta.value.find((m) => m.slug === h.slug))
        .filter((m): m is FilaBringRate => m !== undefined),
);

const pegado = computed(() =>
    elegidos.value
        .map((hueco) => {
            const especie = catalogo.value.find((e) => e.slug === hueco.slug);
            const lineas = [`${especie?.name ?? hueco.slug}${hueco.objeto ? ` @ ${hueco.objeto}` : ''}`];

            if (hueco.habilidad) lineas.push(`Ability: ${hueco.habilidad}`);
            lineas.push('Level: 50');

            const sp = Object.entries(hueco.sp)
                .filter(([, valor]) => valor > 0)
                .map(([clave, valor]) => `${valor} ${clave.toUpperCase()}`)
                .join(' / ');

            if (sp) lineas.push(`SP: ${sp}`);
            if (hueco.alineamiento) lineas.push(`Alignment: ${hueco.alineamiento}`);

            for (const movimiento of hueco.movimientos) lineas.push(`- ${movimiento}`);

            return lineas.join('\n');
        })
        .join('\n\n'),
);
</script>

<template>
    <div>
        <header class="mb-10">
            <p class="rotulo mb-3">{{ $t('nav.constructor') }}</p>
            <h1 class="max-w-3xl text-3xl leading-tight font-bold tracking-tight text-balance text-mist sm:text-4xl">
                {{ $t('constructor.titulo') }}
            </h1>
            <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-fog">{{ $t('constructor.entradilla') }}</p>
        </header>

        <Cargando v-if="cargando" />

        <template v-else>
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <span class="flex flex-wrap items-center gap-1">
                    <SpeciesSprite
                        v-for="(hueco, indice) in huecos"
                        :key="indice"
                        :sprite="catalogo.find((e) => e.slug === hueco.slug)?.sprite ?? null"
                        :stone="catalogo.find((e) => e.slug === hueco.slug)?.sprite_stone ?? null"
                        :name="hueco.slug ?? ''"
                        :size="40"
                    />
                </span>
                <button
                    v-if="elegidos.length > 0"
                    type="button"
                    class="text-xs text-fog-dim hover:text-clay"
                    @click="vaciar"
                >
                    {{ $t('constructor.vaciar') }}
                </button>
            </div>

            <PartnerSuggest
                v-if="slugsElegidos.length > 0"
                :elegidos="slugsElegidos"
                :hueco-libre="primerLibre >= 0"
                @anadir="anadir"
            />

            <div class="mb-14 grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                <SlotEditor
                    v-for="(hueco, indice) in huecos"
                    :key="indice"
                    :hueco="hueco"
                    :catalogo="catalogo"
                    :alineamientos="alineamientos"
                    @cambiar="cambiar(indice, $event)"
                    @quitar="quitar(indice)"
                />
            </div>

            <section v-if="datosMeta.length > 0" class="mb-14">
                <Rotulo :texto="$t('constructor.que_hace_la_gente')" />
                <p class="mb-5 max-w-2xl text-sm text-fog">{{ $t('constructor.que_hace_texto') }}</p>

                <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="fila in datosMeta"
                        :key="fila.slug"
                        class="superficie flex items-center gap-3 rounded-xl border border-line-soft px-3 py-2"
                    >
                        <SpeciesSprite
                            :sprite="fila.sprite"
                            :stone="fila.sprite_stone"
                            :name="nombre(fila)"
                            :size="36"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-mist">{{ nombre(fila) }}</span>
                            <span class="cifra block text-[11px] text-fog-dim">
                                {{ $t('constructor.resumen', {
                                    lleva: fila.usage_pct.toFixed(1),
                                    trae: fila.bring_pct.toFixed(0),
                                }) }}
                            </span>
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="enfocado" class="mb-14">
                <Rotulo :texto="$t('conducta.titulo')" />
                <p class="mb-5 max-w-2xl text-sm text-fog">{{ $t('constructor.conducta_texto') }}</p>
                <BehaviorPanel :slug="enfocado" />
            </section>

            <section v-if="elegidos.length > 0">
                <Rotulo :texto="$t('equipos.exportar')" />
                <div class="rounded-xl border border-line-soft bg-surface p-5">
                    <p class="mb-4 max-w-2xl text-sm leading-relaxed text-fog">{{ $t('equipos.sin_codigo') }}</p>
                    <pre class="overflow-x-auto rounded-lg bg-night p-4 text-xs leading-relaxed text-fog">{{ pegado }}</pre>
                </div>
            </section>
        </template>
    </div>
</template>
