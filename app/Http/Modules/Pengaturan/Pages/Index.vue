<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import PengaturanGroup from '@modules/Pengaturan/Components/PengaturanGroup.vue';

defineProps({
    groups: { type: Array, required: true },
});

const judulGrup = {
    harian: ['Operasional harian', 'Tanggal transaksi aktif. Modal awal teller diisi saat membuka sesi kas.'],
    keuangan: ['Keuangan', 'Bunga, setoran minimal, dan biaya administrasi.'],
    umum: ['Umum', 'Identitas bank dan sekolah untuk kop laporan.'],
};
</script>

<template>
    <Head title="Pengaturan" />

    <PageHeader title="Pengaturan" subtitle="Pengaturan aplikasi Bank Provita" />

    <div class="flex flex-col gap-5">
        <PengaturanGroup
            v-for="group in groups"
            :key="group.grup"
            :group="group"
            :title="judulGrup[group.grup]?.[0] ?? group.grup"
            :description="judulGrup[group.grup]?.[1]"
        />
    </div>
</template>
