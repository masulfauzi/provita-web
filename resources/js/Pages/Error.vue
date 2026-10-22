<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    status: { type: Number, required: true },
});

const pesan = {
    403: ['Akses ditolak', 'Anda tidak memiliki hak akses ke halaman ini.'],
    404: ['Halaman tidak ditemukan', 'Halaman yang Anda cari tidak ada atau sudah dipindahkan.'],
    500: ['Terjadi kesalahan', 'Terjadi kesalahan di server. Silakan coba lagi.'],
    503: ['Sedang pemeliharaan', 'Aplikasi sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.'],
};

const judul = computed(() => pesan[props.status]?.[0] ?? 'Kesalahan');
const keterangan = computed(() => pesan[props.status]?.[1] ?? '');
</script>

<template>
    <Head :title="judul" />

    <div class="card mx-auto max-w-lg text-center">
        <div class="text-5xl font-bold text-primary">{{ status }}</div>
        <h1 class="mt-3 text-xl font-semibold">{{ judul }}</h1>
        <p class="mt-1 text-muted-color">{{ keterangan }}</p>
        <Link href="/dashboard" class="mt-5 inline-block">
            <Button label="Kembali ke dashboard" icon="pi pi-home" />
        </Link>
    </div>
</template>
