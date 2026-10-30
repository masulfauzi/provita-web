<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';
import { rupiah } from '@/utils/format';

const props = defineProps({
    tanggal: { type: String, required: true },
    rekap: { type: Object, default: null },
    saranModal: { type: String, default: '0.00' },
    riwayat: { type: Object, required: true },
});

const confirm = useConfirm();
const { can } = usePermission();

const sesi = computed(() => props.rekap?.sesi ?? null);
const terbuka = computed(() => sesi.value && !sesi.value.ditutup_at);
const tanggalLain = computed(() => props.rekap && props.rekap.tanggal !== props.tanggal);

const formBuka = useForm({ modal_awal: Number(props.saranModal) || null });
const formTutup = useForm({ uang_fisik: null, keterangan: '' });

// Selisih dihitung dalam sen agar tidak terkena pembulatan float.
const sen = (nilai) => Math.round(Number(nilai || 0) * 100);
const selisih = computed(() =>
    formTutup.uang_fisik === null ? null : (sen(formTutup.uang_fisik) - sen(props.rekap?.saldo_akhir)) / 100,
);

const statusSelisih = (nilai) => {
    if (nilai === null || nilai === undefined) return null;
    const angka = Number(nilai);
    if (angka === 0) return { label: 'Cocok', severity: 'success' };
    return angka > 0 ? { label: 'Lebih', severity: 'warn' } : { label: 'Kurang', severity: 'danger' };
};

function buka() {
    formBuka.post('/kas/sesi', { preserveScroll: true });
}

function tutup() {
    confirm.require({
        header: 'Tutup sesi kas',
        message: `Saldo kas seharusnya ${rupiah(props.rekap.saldo_akhir)}, uang fisik ${rupiah(formTutup.uang_fisik)}. Setelah ditutup, teller tidak bisa bertransaksi lagi pada tanggal ini. Lanjutkan?`,
        icon: 'pi pi-lock',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya, tutup sesi', severity: 'warn' },
        accept: () => formTutup.post(`/kas/sesi/${sesi.value.id}/tutup`, { preserveScroll: true }),
    });
}

function reload(params) {
    router.get('/kas/sesi', params, { preserveState: true, preserveScroll: true, replace: true, only: ['riwayat'] });
}
</script>

<template>
    <Head title="Sesi kas" />

    <PageHeader
        title="Sesi kas"
        subtitle="Buka sesi dengan modal awal sebelum bertransaksi, lalu cocokkan uang fisik saat menutup sesi."
    >
        <Link v-if="rekap && can('laporan.harian')" :href="`/laporan/harian?tanggal=${rekap.tanggal}`">
            <Button label="Laporan kas harian" icon="pi pi-file" outlined />
        </Link>
    </PageHeader>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,28rem)_1fr]">
        <!-- Belum ada sesi: buka sesi -->
        <form v-if="!sesi" class="card flex flex-col gap-5 self-start" @submit.prevent="buka">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-sm text-muted-color">
                    <i class="pi pi-calendar" /> Tanggal:
                    <DateText class="font-semibold text-color" :value="tanggal" hari />
                </div>
                <Tag value="Belum dibuka" severity="secondary" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="modal_awal" class="font-medium">Modal awal</label>
                <MoneyInput
                    v-model="formBuka.modal_awal"
                    input-id="modal_awal"
                    :invalid="!!formBuka.errors.modal_awal"
                    fluid
                />
                <small class="text-muted-color">Uang tunai di laci teller saat membuka loket.</small>
                <FieldError :message="formBuka.errors.modal_awal" />
            </div>

            <Button
                type="submit"
                label="Buka sesi"
                icon="pi pi-lock-open"
                :loading="formBuka.processing"
                :disabled="formBuka.modal_awal === null"
            />
        </form>

        <!-- Sesi terbuka: ringkasan + tutup sesi -->
        <form v-else-if="terbuka" class="card flex flex-col gap-5 self-start" @submit.prevent="tutup">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-sm text-muted-color">
                    <i class="pi pi-calendar" /> Sesi:
                    <DateText class="font-semibold text-color" :value="rekap.tanggal" hari />
                </div>
                <Tag value="Terbuka" severity="success" />
            </div>

            <Message v-if="tanggalLain" severity="warn">
                Sesi ini belum ditutup, sedangkan tanggal transaksi aktif sudah berganti. Tutup sesi ini sebelum membuka
                sesi baru.
            </Message>

            <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted-color">Modal awal</dt>
                <dd class="text-right"><MoneyText :value="rekap.modal_awal" /></dd>
                <dt class="text-muted-color">Pemasukan</dt>
                <dd class="text-right text-green-600 dark:text-green-400">
                    + <MoneyText :value="rekap.total_pemasukan" />
                </dd>
                <dt class="text-muted-color">Pengeluaran</dt>
                <dd class="text-right text-red-600 dark:text-red-400">
                    − <MoneyText :value="rekap.total_pengeluaran" />
                </dd>
                <div class="col-span-2 border-t border-surface-200 dark:border-surface-700" />
                <dt class="font-semibold">Saldo kas seharusnya</dt>
                <dd class="text-right text-base font-semibold">
                    <MoneyText :value="rekap.saldo_akhir" />
                </dd>
            </dl>

            <div class="flex flex-col gap-2">
                <label for="uang_fisik" class="font-medium">Uang fisik di laci</label>
                <MoneyInput
                    v-model="formTutup.uang_fisik"
                    input-id="uang_fisik"
                    :invalid="!!formTutup.errors.uang_fisik"
                    fluid
                />
                <div v-if="selisih !== null" class="flex items-center gap-2 text-sm">
                    <Tag :value="statusSelisih(selisih).label" :severity="statusSelisih(selisih).severity" />
                    <span v-if="selisih !== 0">Selisih <MoneyText :value="selisih" colored /></span>
                </div>
                <FieldError :message="formTutup.errors.uang_fisik || formTutup.errors.sesi" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="keterangan" class="font-medium">
                    Keterangan <span v-if="!selisih" class="text-muted-color">(opsional)</span>
                </label>
                <InputText
                    id="keterangan"
                    v-model="formTutup.keterangan"
                    placeholder="Wajib diisi bila ada selisih, mis. salah memberi kembalian"
                    :invalid="!!formTutup.errors.keterangan"
                />
                <FieldError :message="formTutup.errors.keterangan" />
            </div>

            <Button
                type="submit"
                label="Tutup sesi"
                icon="pi pi-lock"
                severity="warn"
                :loading="formTutup.processing"
                :disabled="formTutup.uang_fisik === null || (selisih !== 0 && !formTutup.keterangan)"
            />
        </form>

        <!-- Sesi hari ini sudah ditutup -->
        <div v-else class="card flex flex-col gap-5 self-start">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-sm text-muted-color">
                    <i class="pi pi-calendar" /> Sesi:
                    <DateText class="font-semibold text-color" :value="rekap.tanggal" hari />
                </div>
                <Tag value="Ditutup" severity="secondary" />
            </div>

            <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted-color">Modal awal</dt>
                <dd class="text-right"><MoneyText :value="sesi.modal_awal" /></dd>
                <dt class="text-muted-color">Saldo kas seharusnya</dt>
                <dd class="text-right"><MoneyText :value="sesi.saldo_sistem" /></dd>
                <dt class="text-muted-color">Uang fisik</dt>
                <dd class="text-right"><MoneyText :value="sesi.uang_fisik" /></dd>
                <dt class="font-semibold">Selisih</dt>
                <dd class="flex items-center justify-end gap-2">
                    <Tag :value="statusSelisih(sesi.selisih).label" :severity="statusSelisih(sesi.selisih).severity" />
                    <MoneyText :value="sesi.selisih" colored />
                </dd>
            </dl>
            <p v-if="sesi.keterangan" class="text-sm">
                <span class="text-muted-color">Keterangan:</span> {{ sesi.keterangan }}
            </p>
            <p class="text-sm text-muted-color">
                Dibuka {{ sesi.pembuka ?? '-' }}, ditutup {{ sesi.penutup ?? '-' }}. Sesi berikutnya dibuka setelah
                tanggal transaksi diganti di Pengaturan.
            </p>
            <Message v-if="rekap.setelah_tutup" severity="warn">
                Ada transaksi setelah sesi ditutup; lihat laporan kas harian.
            </Message>
        </div>

        <div class="card min-w-0">
            <h2 class="mb-3 font-semibold">Riwayat sesi</h2>
            <DataTable
                :value="riwayat.data"
                lazy
                paginator
                :rows="riwayat.per_page"
                :total-records="riwayat.total"
                :first="(riwayat.current_page - 1) * riwayat.per_page"
                :rows-per-page-options="[10, 30, 50]"
                data-key="id"
                striped-rows
                @page="(e) => reload({ page: e.page + 1, per_page: e.rows })"
            >
                <template #empty>Belum ada sesi kas.</template>
                <Column header="Tanggal">
                    <template #body="{ data }">
                        <Link
                            v-if="can('laporan.harian')"
                            :href="`/laporan/harian?tanggal=${data.tanggal}`"
                            class="text-primary hover:underline"
                        >
                            <DateText :value="data.tanggal" />
                        </Link>
                        <DateText v-else :value="data.tanggal" />
                    </template>
                </Column>
                <Column header="Modal awal" class="text-right">
                    <template #body="{ data }"><MoneyText :value="data.modal_awal" /></template>
                </Column>
                <Column header="Saldo sistem" class="text-right">
                    <template #body="{ data }"
                        ><MoneyText v-if="data.saldo_sistem !== null" :value="data.saldo_sistem" /><span v-else
                            >-</span
                        ></template
                    >
                </Column>
                <Column header="Uang fisik" class="text-right">
                    <template #body="{ data }"
                        ><MoneyText v-if="data.uang_fisik !== null" :value="data.uang_fisik" /><span v-else
                            >-</span
                        ></template
                    >
                </Column>
                <Column header="Selisih" class="text-right">
                    <template #body="{ data }">
                        <template v-if="data.ditutup_at">
                            <MoneyText :value="data.selisih" colored />
                            <div v-if="data.keterangan" class="text-xs text-muted-color">{{ data.keterangan }}</div>
                        </template>
                        <Tag v-else value="Terbuka" severity="success" />
                    </template>
                </Column>
                <Column header="Petugas">
                    <template #body="{ data }">
                        <div>{{ data.pembuka?.name ?? '-' }}</div>
                        <div v-if="data.penutup" class="text-xs text-muted-color">tutup: {{ data.penutup.name }}</div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
