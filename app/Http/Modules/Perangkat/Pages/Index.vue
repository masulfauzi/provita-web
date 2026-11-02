<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import ConfirmDelete from '@/Components/ConfirmDelete.vue';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

defineProps({
    items: { type: Array, required: true },
    perangkatIni: { type: Object, default: null },
});

const { can } = usePermission();

const daftar = useForm({ nama: '' });

function daftarkan() {
    daftar.post('/perangkat', { preserveScroll: true, onSuccess: () => daftar.reset() });
}

const ubah = useForm({ nama: '' });
const diubah = ref(null);

function bukaUbah(item) {
    diubah.value = item;
    ubah.clearErrors();
    ubah.nama = item.nama;
}

function simpanUbah() {
    ubah.put(`/perangkat/${diubah.value.id}`, { preserveScroll: true, onSuccess: () => (diubah.value = null) });
}

function toggle(item) {
    router.post(`/perangkat/${item.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Perangkat" />

    <PageHeader
        title="Perangkat"
        subtitle="Komputer yang boleh dipakai teller. Bendahara dan admin bisa login dari komputer mana saja."
    />

    <div class="card mb-4">
        <h2 class="mb-3 text-lg font-semibold">Komputer ini</h2>
        <Message v-if="perangkatIni" severity="success" :closable="false">
            Terdaftar sebagai <strong>{{ perangkatIni.nama }}</strong
            >. Teller bisa login di browser ini.
        </Message>
        <template v-else>
            <Message severity="warn" :closable="false" class="mb-4">
                Browser ini belum terdaftar. Teller tidak bisa login di sini.
            </Message>
            <form v-if="can('perangkat.kelola')" class="flex flex-wrap items-start gap-2" @submit.prevent="daftarkan">
                <div class="flex flex-col gap-1">
                    <InputText
                        v-model="daftar.nama"
                        placeholder="Nama komputer, mis. PC Teller 1"
                        aria-label="Nama komputer"
                        class="w-72 max-w-full"
                        :invalid="!!daftar.errors.nama"
                    />
                    <FieldError :message="daftar.errors.nama" />
                </div>
                <Button
                    type="submit"
                    label="Daftarkan komputer ini"
                    icon="pi pi-desktop"
                    :loading="daftar.processing"
                />
            </form>
        </template>
        <p class="mt-3 text-sm text-muted-color">
            Pendaftaran berlaku untuk browser yang sedang dipakai. Jika data browser dihapus, browser diganti, atau
            memakai mode penyamaran (incognito), komputer harus didaftarkan ulang.
        </p>
    </div>

    <div class="card">
        <DataTable :value="items" data-key="id" striped-rows>
            <template #empty>Belum ada komputer terdaftar.</template>

            <Column header="Nama">
                <template #body="{ data }">
                    <span class="font-medium">{{ data.nama }}</span>
                    <Tag v-if="data.ini" value="Komputer ini" severity="info" class="ml-2" />
                </template>
            </Column>
            <Column header="Status">
                <template #body="{ data }">
                    <Tag
                        :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
            <Column header="Terakhir dipakai">
                <template #body="{ data }">
                    <DateText v-if="data.terakhir_dipakai_at" :value="data.terakhir_dipakai_at" jam />
                    <span v-else class="text-muted-color">Belum pernah</span>
                    <div v-if="data.ip_terakhir" class="text-xs text-muted-color">IP {{ data.ip_terakhir }}</div>
                </template>
            </Column>
            <Column header="Didaftarkan">
                <template #body="{ data }">
                    <DateText :value="data.created_at" />
                    <div class="text-xs text-muted-color">{{ data.didaftarkan_oleh ?? '-' }}</div>
                </template>
            </Column>
            <Column v-if="can('perangkat.kelola')" header="" class="w-px">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button
                            v-tooltip.top="'Ubah nama'"
                            icon="pi pi-pencil"
                            text
                            rounded
                            aria-label="Ubah nama"
                            @click="bukaUbah(data)"
                        />
                        <Button
                            v-tooltip.top="data.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                            :icon="data.is_active ? 'pi pi-ban' : 'pi pi-check-circle'"
                            :severity="data.is_active ? 'warn' : 'success'"
                            text
                            rounded
                            :aria-label="data.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                            @click="toggle(data)"
                        />
                        <ConfirmDelete
                            :url="`/perangkat/${data.id}`"
                            message="Komputer ini dihapus dari daftar dan teller tidak bisa lagi login di sana. Lanjutkan?"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <Dialog
        :visible="!!diubah"
        modal
        header="Ubah nama komputer"
        class="w-full max-w-md"
        @update:visible="(v) => !v && (diubah = null)"
    >
        <form class="flex flex-col gap-2" @submit.prevent="simpanUbah">
            <InputText v-model="ubah.nama" aria-label="Nama komputer" :invalid="!!ubah.errors.nama" autofocus />
            <FieldError :message="ubah.errors.nama" />
            <div class="mt-2 flex justify-end gap-2">
                <Button label="Batal" severity="secondary" text @click="diubah = null" />
                <Button type="submit" label="Simpan" :loading="ubah.processing" />
            </div>
        </form>
    </Dialog>
</template>
