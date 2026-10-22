<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Password from 'primevue/password';
import FieldError from '@/Components/FieldError.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    username: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: ['teller'],
});

function submit() {
    form.post('/pengguna');
}
</script>

<template>
    <Head title="Tambah pengguna" />

    <PageHeader title="Tambah pengguna" />

    <form class="card flex max-w-2xl flex-col gap-5" @submit.prevent="submit">
        <div class="grid gap-5 md:grid-cols-2">
            <div class="flex flex-col gap-2">
                <label for="username" class="font-medium">Username</label>
                <InputText id="username" v-model="form.username" :invalid="!!form.errors.username" autofocus />
                <FieldError :message="form.errors.username" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="name" class="font-medium">Nama lengkap</label>
                <InputText id="name" v-model="form.name" :invalid="!!form.errors.name" />
                <FieldError :message="form.errors.name" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="email" class="font-medium">Email (opsional)</label>
                <InputText id="email" v-model="form.email" type="email" :invalid="!!form.errors.email" />
                <FieldError :message="form.errors.email" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="roles" class="font-medium">Peran</label>
                <MultiSelect
                    v-model="form.roles"
                    input-id="roles"
                    :options="roles"
                    option-label="label"
                    option-value="value"
                    display="chip"
                    :invalid="!!form.errors.roles"
                />
                <FieldError :message="form.errors.roles" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="password" class="font-medium">Kata sandi</label>
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
                <label for="password_confirmation" class="font-medium">Ulangi kata sandi</label>
                <Password
                    v-model="form.password_confirmation"
                    input-id="password_confirmation"
                    :feedback="false"
                    toggle-mask
                    fluid
                    autocomplete="new-password"
                />
            </div>
        </div>

        <div class="flex gap-2">
            <Button type="submit" label="Simpan" icon="pi pi-check" :loading="form.processing" />
            <Link href="/pengguna">
                <Button label="Batal" severity="secondary" text />
            </Link>
        </div>
    </form>
</template>
