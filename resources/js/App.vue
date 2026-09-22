<script setup lang="ts">
import TopNav from '@/components/TopNav.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-night">
        <TopNav />

        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-10">
            <RouterView />
        </main>

        <footer class="border-t border-line-soft">
            <div class="mx-auto flex max-w-6xl flex-wrap gap-x-6 gap-y-1 px-5 py-6 text-[11px] text-fog-dim">
                <p>{{ $t('fuente') }}</p>
                <p>{{ $t('aviso') }}</p>
            </div>
        </footer>
    </div>
</template>
