<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import FieldError from '@/Components/FieldError.vue';

const page = usePage();

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk" />

    <div class="mb-6 text-center">
        <i class="pi pi-building-columns text-4xl text-primary" />
        <h1 class="mt-2 text-2xl font-semibold">{{ page.props.app?.name ?? 'Bank Provita' }}</h1>
        <p class="text-muted-color">Bank Mini SMK Negeri 2 Semarang</p>
    </div>

    <form class="card flex flex-col gap-5" @submit.prevent="submit">
        <div class="flex flex-col gap-2">
            <label for="username" class="font-medium">Nama pengguna</label>
            <InputText
                id="username"
                v-model="form.username"
                autocomplete="username"
                autofocus
                :invalid="!!form.errors.username"
            />
            <FieldError :message="form.errors.username" />
        </div>

        <div class="flex flex-col gap-2">
            <label for="password" class="font-medium">Kata sandi</label>
            <Password
                v-model="form.password"
                input-id="password"
                :feedback="false"
                toggle-mask
                fluid
                autocomplete="current-password"
                :invalid="!!form.errors.password"
            />
            <FieldError :message="form.errors.password" />
        </div>

        <label class="flex items-center gap-2">
            <Checkbox v-model="form.remember" binary input-id="remember" />
            <span>Ingat saya</span>
        </label>

        <Button type="submit" label="Masuk" icon="pi pi-sign-in" :loading="form.processing" />
    </form>
</template>
