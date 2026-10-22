<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Password from 'primevue/password';
import FieldError from '@/Components/FieldError.vue';
import PageHeader from '@/Components/PageHeader.vue';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put('/profil/password', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <Head title="Ganti kata sandi" />

    <PageHeader title="Ganti kata sandi" subtitle="Gunakan minimal 8 karakter." />

    <form class="card flex max-w-lg flex-col gap-5" @submit.prevent="submit">
        <div class="flex flex-col gap-2">
            <label for="current_password" class="font-medium">Kata sandi saat ini</label>
            <Password
                v-model="form.current_password"
                input-id="current_password"
                :feedback="false"
                toggle-mask
                fluid
                autocomplete="current-password"
                :invalid="!!form.errors.current_password"
            />
            <FieldError :message="form.errors.current_password" />
        </div>
        <div class="flex flex-col gap-2">
            <label for="password" class="font-medium">Kata sandi baru</label>
            <Password
                v-model="form.password"
                input-id="password"
                toggle-mask
                fluid
                autocomplete="new-password"
                :invalid="!!form.errors.password"
            />
            <FieldError :message="form.errors.password" />
        </div>
        <div class="flex flex-col gap-2">
            <label for="password_confirmation" class="font-medium">Ulangi kata sandi baru</label>
            <Password
                v-model="form.password_confirmation"
                input-id="password_confirmation"
                :feedback="false"
                toggle-mask
                fluid
                autocomplete="new-password"
            />
        </div>
        <div>
            <Button type="submit" label="Simpan" icon="pi pi-check" :loading="form.processing" />
        </div>
    </form>
</template>
