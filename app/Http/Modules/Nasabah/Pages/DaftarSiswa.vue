<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import FieldError from '@/Components/FieldError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    nama: { type: String, default: '' },
    hasil: { type: Array, default: null },
    galat: { type: String, default: null },
    galatNama: { type: String, default: null },
});

const { can } = usePermission();
const kata = ref(props.nama);
const memuat = ref(false);

function cari() {
    router.get(
        '/daftar-siswa',
        { nama: kata.value.trim() || undefined },
        { preserveState: true, onStart: () => (memuat.value = true), onFinish: () => (memuat.value = false) },
    );
}

const bisaDidaftarkan = (siswa) => can('nasabah.tambah') && /^\d{10}$/.test(siswa.nisn ?? '');
</script>

<template>
    <Head title="Daftar siswa" />

    <PageHeader title="Daftar siswa" subtitle="Cari data siswa SMK Negeri 2 Semarang dari sistem sekolah." />

    <div class="card">
        <form class="mb-5 flex max-w-xl flex-col gap-2" @submit.prevent="cari">
            <label for="nama" class="font-medium">Nama siswa</label>
            <div class="flex gap-2">
                <IconField class="flex-1">
                    <InputIcon class="pi pi-search" />
                    <InputText
                        id="nama"
                        v-model="kata"
                        placeholder="Ketik nama siswa (min. 3 huruf)"
                        class="w-full"
                        :invalid="!!galatNama"
                        autofocus
                    />
                </IconField>
                <Button
                    type="submit"
                    label="Cari"
                    icon="pi pi-search"
                    :loading="memuat"
                    :disabled="kata.trim().length < 3"
                />
            </div>
            <FieldError :message="galatNama" />
        </form>

        <Message v-if="galat" severity="warn" class="mb-4">{{ galat }} Coba lagi beberapa saat lagi.</Message>

        <p v-if="hasil === null && !galat" class="text-muted-color">
            <i class="pi pi-info-circle" /> Masukkan nama siswa lalu klik <strong>Cari</strong>. Data diambil langsung
            dari sistem data siswa sekolah.
        </p>

        <template v-else-if="hasil">
            <p class="mb-3 text-sm text-muted-color">
                {{ hasil.length }} siswa ditemukan untuk "<span class="font-semibold text-color">{{ nama }}</span
                >".
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-surface-200 text-left dark:border-surface-700">
                            <th class="py-2 pr-3">No</th>
                            <th class="py-2 pr-3">Nama</th>
                            <th class="py-2 pr-3">NIS</th>
                            <th class="py-2 pr-3">NISN</th>
                            <th class="py-2 pr-3">Kelas</th>
                            <th class="py-2">Status nasabah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(siswa, i) in hasil"
                            :key="`${siswa.nisn}-${i}`"
                            class="border-b border-surface-100 dark:border-surface-800"
                        >
                            <td class="py-2 pr-3 text-muted-color tabular-nums">{{ i + 1 }}</td>
                            <td class="py-2 pr-3 font-medium">{{ siswa.nama ?? '-' }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ siswa.nis ?? '-' }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ siswa.nisn ?? '-' }}</td>
                            <td class="py-2 pr-3">{{ siswa.kelas ?? '-' }}</td>
                            <td class="py-2">
                                <div v-if="siswa.nasabah" class="flex flex-wrap items-center gap-2">
                                    <Tag value="Sudah nasabah" severity="success" />
                                    <Link
                                        v-if="can('nasabah.lihat')"
                                        :href="`/nasabah/${siswa.nasabah.id}`"
                                        class="text-primary hover:underline"
                                        >{{ siswa.nasabah.nomor }}</Link
                                    >
                                    <span v-else>{{ siswa.nasabah.nomor }}</span>
                                    <Tag v-if="!siswa.nasabah.is_active" value="Nonaktif" severity="secondary" />
                                </div>
                                <Link v-else-if="bisaDidaftarkan(siswa)" :href="`/nasabah/create?nisn=${siswa.nisn}`">
                                    <Button label="Daftarkan" icon="pi pi-user-plus" size="small" outlined />
                                </Link>
                                <span v-else class="text-muted-color">Belum nasabah</span>
                            </td>
                        </tr>
                        <tr v-if="!hasil.length">
                            <td colspan="6" class="py-4 text-center text-muted-color italic">
                                Tidak ada siswa dengan nama "{{ nama }}".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
