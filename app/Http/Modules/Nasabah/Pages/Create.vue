<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import PageHeader from '@/Components/PageHeader.vue';
import NasabahForm from '@modules/Nasabah/Components/NasabahForm.vue';
import { useNasabahForm } from '@modules/Nasabah/Components/useNasabahForm';

defineProps({
    kelas: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    jenisKelaminOptions: { type: Array, default: () => [] },
});

const form = useNasabahForm();

function submit() {
    form.post('/nasabah');
}
</script>

<template>
    <Head title="Tambah nasabah" />

    <PageHeader title="Tambah nasabah" subtitle="Nomor nasabah dibuat otomatis saat disimpan." />

    <NasabahForm
        :form="form"
        :kelas="kelas"
        :jenis-options="jenisOptions"
        :jenis-kelamin-options="jenisKelaminOptions"
        @submit="submit"
    >
        <template #actions>
            <Link href="/nasabah">
                <Button label="Batal" severity="secondary" text />
            </Link>
        </template>
    </NasabahForm>
</template>
