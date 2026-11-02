<script setup>
import { computed } from 'vue';
import Tag from 'primevue/tag';

const props = defineProps({
    status: { type: String, required: true },
});

const tampilan = computed(
    () =>
        ({
            berjalan: { label: 'Berjalan', severity: 'info' },
            lunas: { label: 'Lunas', severity: 'success' },
            dibatalkan: { label: 'Dibatalkan', severity: 'secondary' },
            terlambat: { label: 'Terlambat', severity: 'danger' },
            belum: { label: 'Belum dibayar', severity: 'warn' },
        })[props.status] ?? { label: props.status, severity: 'secondary' },
);
</script>

<template>
    <Tag :value="tampilan.label" :severity="tampilan.severity" />
</template>
