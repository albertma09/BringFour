<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { cambiarIdioma } from '@/i18n';

const { locale } = useI18n();

const idiomas = [
    { codigo: 'es', etiqueta: 'ES' },
    { codigo: 'en', etiqueta: 'EN' },
] as const;
</script>

<template>
    <div class="min-h-screen bg-carbon-900">
        <header class="border-b border-carbon-700">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <RouterLink :to="{ name: 'meta' }" class="flex items-baseline gap-2">
                    <span class="text-base font-semibold tracking-tight text-bone">{{ $t('marca') }}</span>
                    <span class="hidden text-xs text-ash-dim sm:inline">{{ $t('lema') }}</span>
                </RouterLink>
                <nav class="flex items-center gap-1">
                    <button
                        v-for="idioma in idiomas"
                        :key="idioma.codigo"
                        type="button"
                        class="rounded px-2 py-1 text-xs font-medium transition-colors"
                        :class="locale === idioma.codigo ? 'bg-carbon-700 text-bone' : 'text-ash hover:text-bone'"
                        @click="cambiarIdioma(idioma.codigo)"
                    >
                        {{ idioma.etiqueta }}
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <RouterView />
        </main>

        <footer class="border-t border-carbon-700">
            <div class="mx-auto max-w-5xl px-6 py-6">
                <p class="text-xs text-ash-dim">{{ $t('aviso') }}</p>
            </div>
        </footer>
    </div>
</template>
