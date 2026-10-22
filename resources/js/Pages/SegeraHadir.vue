<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({
    judul: { type: String, required: true },
    keterangan: { type: String, default: null },
});
</script>

<template>
    <Head :title="judul" />

    <PageHeader :title="judul" />

    <div class="card flex flex-col items-center gap-2 py-12 text-center">
        <i class="pi pi-wrench text-4xl text-muted-color" />
        <div class="text-lg font-medium">Segera hadir</div>
        <p class="max-w-md text-muted-color">{{ keterangan }}</p>
    </div>
</template>
