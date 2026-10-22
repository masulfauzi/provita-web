<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';
import { rupiah } from '@/utils/format';

const props = defineProps({
    rekening: { type: Object, required: true },
    mutasi: { type: Object, required: true },
    jenisOptions: { type: Array, default: () => [] },
    jenisTransaksi: { type: Object, default: () => ({}) },
    penutupan: { type: Object, default: null },
});

const { can } = usePermission();
const confirm = useConfirm();

const aktif = computed(() => props.rekening.status === 'aktif');
const jenisRekening = computed(
    () => props.jenisOptions.find((o) => o.value === props.rekening.jenis)?.label ?? props.rekening.jenis,
);
const isKredit = (row) => !String(row.mutasi).startsWith('-');

function onPage(event) {
    router.get(
        `/rekening/${props.rekening.id}`,
        { page: event.page + 1, per_page: event.rows },
        { preserveState: true, preserveScroll: true, replace: true, only: ['mutasi'] },
    );
}

function tutup() {
    confirm.require({
        header: `Tutup rekening ${props.rekening.nomor}`,
        message:
            `Saldo ${rupiah(props.rekening.saldo)} dipotong biaya administrasi ${rupiah(props.penutupan.biaya)}. ` +
            `Uang yang diserahkan ke nasabah: ${rupiah(props.penutupan.diserahkan)}. Rekening tidak bisa dipakai lagi. Lanjutkan?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Tutup rekening', severity: 'danger' },
        accept: () => router.post(`/rekening/${props.rekening.id}/tutup`, {}, { preserveScroll: true }),
    });
}

const koreksi = ref(null);
const koreksiForm = useForm({ alasan: '' });

function bukaKoreksi(row) {
    koreksi.value = row;
    koreksiForm.reset();
    koreksiForm.clearErrors();
}

function kirimKoreksi() {
    koreksiForm.post(`/transaksi/${koreksi.value.id}/balik`, {
        preserveScroll: true,
        onSuccess: () => (koreksi.value = null),
    });
}
</script>

<template>
    <Head :title="`Buku tabungan ${rekening.nomor}`" />

    <PageHeader :title="rekening.nomor" :subtitle="`Buku ${jenisRekening.toLowerCase()} · ${rekening.nasabah.nama}`">
        <Tag :value="aktif ? 'Aktif' : 'Ditutup'" :severity="aktif ? 'success' : 'secondary'" />
        <Link :href="`/nasabah/${rekening.nasabah.id}`">
            <Button label="Data nasabah" icon="pi pi-user" severity="secondary" text />
        </Link>
        <template v-if="aktif">
            <Link v-if="can('transaksi.setor')" :href="`/transaksi?rekening=${rekening.id}&jenis=setor`">
                <Button label="Setor" icon="pi pi-arrow-down" severity="success" />
            </Link>
            <Link v-if="can('transaksi.tarik')" :href="`/transaksi?rekening=${rekening.id}&jenis=tarik`">
                <Button label="Tarik" icon="pi pi-arrow-up" severity="warn" />
            </Link>
            <Button
                v-if="can('rekening.tutup')"
                label="Tutup rekening"
                icon="pi pi-lock"
                severity="danger"
                outlined
                @click="tutup"
            />
        </template>
    </PageHeader>

    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="card">
            <div class="text-muted-color">Saldo</div>
            <div class="mt-2 text-2xl font-semibold"><MoneyText :value="rekening.saldo" /></div>
        </div>
        <div class="card">
            <div class="text-muted-color">Pemilik</div>
            <div class="mt-2 font-semibold">{{ rekening.nasabah.nama }}</div>
            <div class="text-sm text-muted-color">
                {{ rekening.nasabah.nomor }}<span v-if="rekening.nasabah.kelas"> · {{ rekening.nasabah.kelas }}</span>
            </div>
        </div>
        <div class="card">
            <div class="text-muted-color">Dibuka</div>
            <div class="mt-2 font-semibold"><DateText :value="rekening.tanggal_buka" /></div>
            <div v-if="rekening.tanggal_tutup" class="text-sm text-muted-color">
                Ditutup <DateText :value="rekening.tanggal_tutup" />
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="mb-3 text-lg font-semibold">Mutasi</h2>
        <DataTable
            :value="mutasi.data"
            lazy
            paginator
            :rows="mutasi.per_page"
            :total-records="mutasi.total"
            :first="(mutasi.current_page - 1) * mutasi.per_page"
            :rows-per-page-options="[25, 50, 100]"
            data-key="id"
            striped-rows
            size="small"
            @page="onPage"
        >
            <template #empty>Belum ada mutasi.</template>

            <Column field="urut" header="No" class="w-12" />
            <Column header="Tanggal">
                <template #body="{ data }"><DateText :value="data.tanggal" /></template>
            </Column>
            <Column header="Jenis">
                <template #body="{ data }">{{ jenisTransaksi[data.jenis] ?? data.jenis }}</template>
            </Column>
            <Column header="Keterangan">
                <template #body="{ data }">
                    {{ data.keterangan || '-' }}
                    <Tag v-if="data.sudah_dibalik" value="Dikoreksi" severity="secondary" class="ml-1" />
                </template>
            </Column>
            <Column header="Debit" class="text-right">
                <template #body="{ data }">
                    <MoneyText v-if="!isKredit(data)" :value="data.nominal" class="text-red-600 dark:text-red-400" />
                </template>
            </Column>
            <Column header="Kredit" class="text-right">
                <template #body="{ data }">
                    <MoneyText v-if="isKredit(data)" :value="data.nominal" class="text-green-700 dark:text-green-400" />
                </template>
            </Column>
            <Column header="Saldo" class="text-right">
                <template #body="{ data }"><MoneyText :value="data.saldo_sesudah" /></template>
            </Column>
            <Column header="Petugas">
                <template #body="{ data }">{{ data.petugas || '-' }}</template>
            </Column>
            <Column v-if="aktif && can('transaksi.balik')" header="" class="w-px">
                <template #body="{ data }">
                    <Button
                        v-if="data.jenis !== 'pembalik' && !data.sudah_dibalik"
                        v-tooltip.left="'Koreksi (transaksi pembalik)'"
                        icon="pi pi-undo"
                        text
                        rounded
                        severity="secondary"
                        aria-label="Koreksi"
                        @click="bukaKoreksi(data)"
                    />
                </template>
            </Column>
        </DataTable>
    </div>

    <Dialog
        :visible="!!koreksi"
        modal
        header="Koreksi transaksi"
        class="w-full max-w-md"
        @update:visible="(v) => !v && (koreksi = null)"
    >
        <form v-if="koreksi" class="flex flex-col gap-4" @submit.prevent="kirimKoreksi">
            <p class="text-muted-color">
                Transaksi tidak dihapus. Akan dibuat transaksi pembalik sebesar
                <MoneyText :value="koreksi.nominal" /> ({{ jenisTransaksi[koreksi.jenis] ?? koreksi.jenis }}, mutasi no.
                {{ koreksi.urut }}).
            </p>
            <div class="flex flex-col gap-2">
                <label for="alasan" class="font-medium">Alasan koreksi</label>
                <InputText id="alasan" v-model="koreksiForm.alasan" :invalid="!!koreksiForm.errors.alasan" autofocus />
                <FieldError :message="koreksiForm.errors.alasan" />
            </div>
            <div class="flex justify-end gap-2">
                <Button label="Batal" severity="secondary" text @click="koreksi = null" />
                <Button type="submit" label="Buat pembalik" icon="pi pi-undo" :loading="koreksiForm.processing" />
            </div>
        </form>
    </Dialog>
</template>
