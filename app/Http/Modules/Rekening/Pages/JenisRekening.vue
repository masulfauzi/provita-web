<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import ConfirmDelete from '@/Components/ConfirmDelete.vue';
import FieldError from '@/Components/FieldError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

defineProps({
    items: { type: Array, default: () => [] },
});

const { can } = usePermission();

const dialog = ref(false);
const editing = ref(null);
const form = useForm({ kode: '', nama: '', prefix: '', keterangan: '' });

// Contoh nomor rekening untuk siswa ber-NISN 0012345678.
const CONTOH_NISN = '0012345678';
const contohNomor = (prefix) => `${prefix ?? ''}${CONTOH_NISN}`;

function tambah() {
    editing.value = null;
    form.defaults({ kode: '', nama: '', prefix: '', keterangan: '' });
    form.reset();
    form.clearErrors();
    dialog.value = true;
}

function ubah(item) {
    editing.value = item;
    form.defaults({ kode: item.kode, nama: item.nama, prefix: item.prefix ?? '', keterangan: item.keterangan ?? '' });
    form.reset();
    form.clearErrors();
    dialog.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (dialog.value = false) };

    if (editing.value) {
        form.put(`/jenis-rekening/${editing.value.id}`, options);
    } else {
        form.post('/jenis-rekening', options);
    }
}

function toggleActive(item) {
    router.patch(`/jenis-rekening/${item.id}/aktif`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Jenis rekening" />

    <PageHeader title="Jenis rekening" subtitle="Jenis yang aktif dapat dipilih teller saat membuka rekening">
        <Button v-if="can('jenis_rekening.tambah')" label="Tambah jenis" icon="pi pi-plus" @click="tambah" />
    </PageHeader>

    <div class="card">
        <DataTable :value="items" data-key="id" striped-rows>
            <template #empty>Belum ada jenis rekening.</template>

            <Column header="Nama">
                <template #body="{ data }">
                    <div>{{ data.nama }}</div>
                    <div v-if="data.keterangan" class="text-xs text-muted-color">{{ data.keterangan }}</div>
                </template>
            </Column>
            <Column field="kode" header="Kode" />
            <Column header="Prefix">
                <template #body="{ data }">
                    <span v-if="data.prefix">{{ data.prefix }}</span>
                    <Tag v-else value="Rekening utama" severity="info" />
                </template>
            </Column>
            <Column header="Contoh nomor" class="whitespace-nowrap">
                <template #body="{ data }">{{ contohNomor(data.prefix) }}</template>
            </Column>
            <Column field="rekening_count" header="Jumlah rekening" class="text-right" />
            <Column header="Status">
                <template #body="{ data }">
                    <Tag
                        :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
            <Column v-if="can('jenis_rekening.ubah') || can('jenis_rekening.hapus')" class="w-0 whitespace-nowrap">
                <template #body="{ data }">
                    <div class="flex justify-end gap-1">
                        <template v-if="can('jenis_rekening.ubah')">
                            <Button
                                v-tooltip.top="'Ubah'"
                                icon="pi pi-pencil"
                                text
                                rounded
                                aria-label="Ubah"
                                @click="ubah(data)"
                            />
                            <Button
                                v-tooltip.top="data.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                :icon="data.is_active ? 'pi pi-ban' : 'pi pi-check-circle'"
                                :severity="data.is_active ? 'warn' : 'success'"
                                text
                                rounded
                                :aria-label="data.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                @click="toggleActive(data)"
                            />
                        </template>
                        <ConfirmDelete
                            v-if="can('jenis_rekening.hapus') && data.rekening_count === 0"
                            :url="`/jenis-rekening/${data.id}`"
                            :message="`Hapus jenis rekening ${data.nama}?`"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <Dialog
        v-model:visible="dialog"
        modal
        :header="editing ? `Ubah ${editing.nama}` : 'Tambah jenis rekening'"
        class="w-full max-w-lg"
    >
        <form id="form-jenis-rekening" class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label for="nama" class="font-medium">Nama jenis</label>
                <InputText
                    id="nama"
                    v-model="form.nama"
                    placeholder="mis. Tabungan Berjangka"
                    :invalid="!!form.errors.nama"
                />
                <FieldError :message="form.errors.nama" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="kode" class="font-medium">Kode</label>
                <InputText
                    id="kode"
                    v-model="form.kode"
                    placeholder="mis. tabungan_berjangka"
                    :disabled="!!editing"
                    :invalid="!!form.errors.kode"
                />
                <small class="text-muted-color"
                    >Huruf kecil, angka, dan garis bawah. Tidak bisa diubah setelah disimpan.</small
                >
                <FieldError :message="form.errors.kode" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="prefix" class="font-medium">Prefix nomor rekening</label>
                <InputText
                    id="prefix"
                    v-model="form.prefix"
                    placeholder="mis. 2"
                    inputmode="numeric"
                    maxlength="5"
                    :invalid="!!form.errors.prefix"
                />
                <small class="text-muted-color">
                    Angka di depan NISN/NIS nasabah, mis. {{ contohNomor(form.prefix || null) }}. Kosongkan untuk
                    rekening utama (hanya satu jenis). Mengubah prefix tidak mengubah nomor rekening yang sudah ada.
                </small>
                <FieldError :message="form.errors.prefix" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="keterangan" class="font-medium"
                    >Keterangan <span class="text-muted-color">(opsional)</span></label
                >
                <InputText id="keterangan" v-model="form.keterangan" :invalid="!!form.errors.keterangan" />
                <FieldError :message="form.errors.keterangan" />
            </div>
        </form>

        <template #footer>
            <Button label="Batal" severity="secondary" text @click="dialog = false" />
            <Button
                type="submit"
                form="form-jenis-rekening"
                label="Simpan"
                icon="pi pi-check"
                :loading="form.processing"
            />
        </template>
    </Dialog>
</template>
