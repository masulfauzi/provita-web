<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import PageHeader from '@/Components/PageHeader.vue';
import NasabahForm from '@modules/Nasabah/Components/NasabahForm.vue';
import { useNasabahForm } from '@modules/Nasabah/Components/useNasabahForm';

const props = defineProps({
    item: { type: Object, required: true },
    kelas: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    jenisKelaminOptions: { type: Array, default: () => [] },
});

const form = useNasabahForm(props.item);

function submit() {
    form.put(`/nasabah/${props.item.id}`);
}
</script>

<template>
    <Head title="Ubah nasabah" />

    <PageHeader title="Ubah nasabah" :subtitle="`${item.nomor} · ${item.nama}`" />

    <NasabahForm
        mode="edit"
        :form="form"
        :kelas="kelas"
        :jenis-options="jenisOptions"
        :jenis-kelamin-options="jenisKelaminOptions"
        submit-label="Simpan perubahan"
        @submit="submit"
    >
        <template #actions>
            <Link :href="`/nasabah/${item.id}`">
                <Button label="Batal" severity="secondary" text />
            </Link>
        </template>
    </NasabahForm>
</template>
