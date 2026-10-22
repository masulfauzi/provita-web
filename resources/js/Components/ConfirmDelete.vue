<script setup>
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
    url: { type: String, required: true },
    message: { type: String, default: 'Data yang dihapus tidak dapat dikembalikan. Lanjutkan?' },
    label: { type: String, default: null },
});

const emit = defineEmits(['deleted']);
const confirm = useConfirm();

function ask() {
    confirm.require({
        header: 'Hapus data',
        message: props.message,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Hapus', severity: 'danger' },
        accept: () => router.delete(props.url, { preserveScroll: true, onSuccess: () => emit('deleted') }),
    });
}
</script>

<template>
    <Button
        :label="label"
        icon="pi pi-trash"
        severity="danger"
        :text="!label"
        :rounded="!label"
        aria-label="Hapus"
        @click="ask"
    />
</template>
