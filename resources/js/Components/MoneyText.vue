<script setup>
import { computed } from 'vue';
import { rupiah } from '@/utils/format';

const props = defineProps({
    value: { type: [Number, String, null], default: 0 },
    // Beri warna merah untuk nilai negatif.
    colored: { type: Boolean, default: false },
});

const negative = computed(() =>
    String(props.value ?? '')
        .trim()
        .startsWith('-'),
);
</script>

<template>
    <span class="tabular-nums whitespace-nowrap" :class="{ 'text-red-600 dark:text-red-400': colored && negative }">
        {{ rupiah(value) }}
    </span>
</template>
