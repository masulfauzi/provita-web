<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import ConfirmDelete from '@/Components/ConfirmDelete.vue';
import FieldError from '@/Components/FieldError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    items: { type: Array, default: () => [] },
    kategoriOptions: { type: Array, default: () => [] },
});

const { can } = usePermission();

const dialog = ref(false);
const editing = ref(null);
const kosong = { kode: '', nama: '', kategori: 'beban', keterangan: '' };
const form = useForm({ ...kosong });

const kategoriLabel = (value) => props.kategoriOptions.find((o) => o.value === value)?.label ?? value;
// Kategori akun sistem / yang sudah dipakai jurnal tidak boleh dipindah.
const kategoriTerkunci = computed(() => !!editing.value && (!!editing.value.peran || editing.value.detail_count > 0));

function buka(item = null) {
    editing.value = item;
    form.defaults(
        item
            ? { kode: item.kode, nama: item.nama, kategori: item.kategori, keterangan: item.keterangan ?? '' }
            : kosong,
    );
    form.reset();
    form.clearErrors();
    dialog.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (dialog.value = false) };

    if (editing.value) {
        form.put(`/akun/${editing.value.id}`, options);
    } else {
        form.post('/akun', options);
    }
}

function toggleActive(item) {
    router.patch(`/akun/${item.id}/aktif`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Bagan akun" />

    <PageHeader title="Bagan akun" subtitle="Daftar akun pembukuan. Akun bertanda Sistem dipakai jurnal otomatis.">
        <Button v-if="can('akun.kelola')" label="Tambah akun" icon="pi pi-plus" @click="buka()" />
    </PageHeader>

    <div class="card">
        <DataTable :value="items" data-key="id" striped-rows row-group-mode="subheader" group-rows-by="kategori">
            <template #groupheader="{ data }">
                <span class="font-semibold uppercase text-primary">{{ kategoriLabel(data.kategori) }}</span>
            </template>
            <template #empty>Belum ada akun.</template>

            <Column field="kode" header="Kode" class="w-28 tabular-nums" />
            <Column header="Nama">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        {{ data.nama }}
                        <Tag v-if="data.peran" value="Sistem" severity="info" />
                    </div>
                    <div v-if="data.keterangan" class="text-xs text-muted-color">{{ data.keterangan }}</div>
                </template>
            </Column>
            <Column header="Saldo normal">
                <template #body="{ data }">{{
                    ['aset', 'beban'].includes(data.kategori) ? 'Debit' : 'Kredit'
                }}</template>
            </Column>
            <Column field="detail_count" header="Dipakai" class="text-right">
                <template #body="{ data }">{{ data.detail_count }} baris jurnal</template>
            </Column>
            <Column header="Status">
                <template #body="{ data }">
                    <Tag
                        :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
            <Column v-if="can('akun.kelola')" class="w-0 whitespace-nowrap">
                <template #body="{ data }">
                    <div class="flex justify-end gap-1">
                        <Button
                            v-tooltip.top="'Ubah'"
                            icon="pi pi-pencil"
                            text
                            rounded
                            aria-label="Ubah"
                            @click="buka(data)"
                        />
                        <Button
                            v-if="!data.peran"
                            v-tooltip.top="data.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                            :icon="data.is_active ? 'pi pi-ban' : 'pi pi-check-circle'"
                            :severity="data.is_active ? 'warn' : 'success'"
                            text
                            rounded
                            :aria-label="data.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                            @click="toggleActive(data)"
                        />
                        <ConfirmDelete
                            v-if="!data.peran && data.detail_count === 0"
                            :url="`/akun/${data.id}`"
                            :message="`Hapus akun ${data.kode} ${data.nama}?`"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <Dialog
        v-model:visible="dialog"
        modal
        :header="editing ? `Ubah akun ${editing.kode}` : 'Tambah akun'"
        class="w-full max-w-lg"
    >
        <form id="form-akun" class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="flex flex-col gap-2">
                    <label for="kode" class="font-medium">Kode</label>
                    <InputText id="kode" v-model="form.kode" placeholder="5-103" :invalid="!!form.errors.kode" />
                    <FieldError :message="form.errors.kode" />
                </div>
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <label for="nama" class="font-medium">Nama akun</label>
                    <InputText
                        id="nama"
                        v-model="form.nama"
                        placeholder="mis. Beban ATK"
                        :invalid="!!form.errors.nama"
                    />
                    <FieldError :message="form.errors.nama" />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="kategori" class="font-medium">Kategori</label>
                <Select
                    v-model="form.kategori"
                    input-id="kategori"
                    :options="kategoriOptions"
                    option-label="label"
                    option-value="value"
                    :disabled="kategoriTerkunci"
                    :invalid="!!form.errors.kategori"
                />
                <small v-if="kategoriTerkunci" class="text-muted-color">
                    Akun sistem atau yang sudah dipakai jurnal tidak bisa pindah kategori.
                </small>
                <FieldError :message="form.errors.kategori" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="keterangan" class="font-medium">
                    Keterangan <span class="text-muted-color">(opsional)</span>
                </label>
                <InputText id="keterangan" v-model="form.keterangan" :invalid="!!form.errors.keterangan" />
                <FieldError :message="form.errors.keterangan" />
            </div>
        </form>

        <template #footer>
            <Button label="Batal" severity="secondary" text @click="dialog = false" />
            <Button type="submit" form="form-akun" label="Simpan" icon="pi pi-check" :loading="form.processing" />
        </template>
    </Dialog>
</template>
