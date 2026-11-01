<script setup>
import Tag from 'primevue/tag';

defineProps({
    status: { type: String, required: true },
});

const TAMPILAN = {
    aktif: ['Aktif', 'success'],
    berhenti: ['Dihentikan', 'secondary'],
    selesai: ['Target tercapai', 'info'],
};
</script>

<template>
    <Tag :value="TAMPILAN[status]?.[0] ?? status" :severity="TAMPILAN[status]?.[1]" />
</template>
