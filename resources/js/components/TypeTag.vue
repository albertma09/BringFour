<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { TYPE_ES, typeColor } from '@/design/types';

const props = defineProps<{ type: string }>();
const { locale } = useI18n();

const color = computed(() => typeColor(props.type));
const etiqueta = computed(() => (locale.value === 'es' ? (TYPE_ES[props.type] ?? props.type) : props.type));
</script>

<template>
    <span
        class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium tracking-wide uppercase"
        :style="{ backgroundColor: `${color}26`, color, boxShadow: `inset 0 0 0 1px ${color}59` }"
    >
        {{ etiqueta }}
    </span>
</template>
