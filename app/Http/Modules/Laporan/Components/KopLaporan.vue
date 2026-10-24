<script setup>
defineProps({
    kop: { type: Object, required: true },
});
</script>

<template>
    <div class="mb-5 border-b-2 border-surface-800 pb-3 text-center dark:border-surface-200 print:border-black">
        <div class="text-lg font-bold uppercase">{{ kop.nama_bank }}</div>
        <div v-if="kop.nama_sekolah" class="font-medium">{{ kop.nama_sekolah }}</div>
        <div v-if="kop.alamat_sekolah" class="text-sm text-muted-color print:text-black">{{ kop.alamat_sekolah }}</div>
    </div>
    <div class="mb-5 text-center">
        <h2 class="text-lg font-semibold">{{ kop.judul }}</h2>
        <div class="text-muted-color print:text-black">{{ kop.periode }}</div>
    </div>
</template>
