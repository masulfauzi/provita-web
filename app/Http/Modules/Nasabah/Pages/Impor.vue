<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Message from 'primevue/message';
import { useConfirm } from 'primevue/useconfirm';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { rupiah, tanggal as formatTanggal } from '@/utils/format';

const props = defineProps({
    hasil: { type: Object, default: null },
    tanggalDefault: { type: String, required: true },
});

const confirm = useConfirm();

function keDate(value) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function keString(date) {
    if (!date) return null;
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const tanggal = ref(keDate(props.hasil?.tanggal ?? props.tanggalDefault));
const form = useForm({ file: null, tanggal: null });

// File dipegang browser: dikirim saat Periksa, lalu dikirim lagi saat Impor sekarang.
const file = ref(null);
const sudahDiperiksa = computed(() => !!props.hasil && !!file.value && props.hasil.file === file.value.name);
const bisaImpor = computed(() => sudahDiperiksa.value && props.hasil.error.length === 0);

function pilih(event) {
    file.value = event.target.files[0] ?? null;
}

function kirim(url, options = {}) {
    form.transform(() => ({ file: file.value, tanggal: keString(tanggal.value) })).post(url, {
        preserveScroll: true,
        // File yang dipilih tetap dipegang setelah Periksa, untuk dikirim lagi saat Impor sekarang.
        preserveState: true,
        forceFormData: true,
        ...options,
    });
}

function periksa() {
    kirim('/nasabah/impor/periksa');
}

function impor() {
    const r = props.hasil.ringkasan;
    confirm.require({
        header: 'Impor data sistem lama',
        message:
            `${r.jumlah_nasabah} nasabah dan ${r.jumlah_rekening} rekening akan dibuat dengan total saldo ${rupiah(r.total_saldo)}.\n` +
            `Jurnal saldo awal D Kas / K Simpanan tabungan nasabah dicatat per ${formatTanggal(keString(tanggal.value))}.\n` +
            'Impor hanya bisa dilakukan sekali. Lanjutkan?',
        icon: 'pi pi-upload',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya, impor', severity: 'warn' },
        accept: () => kirim('/nasabah/impor'),
    });
}
</script>

<template>
    <Head title="Impor nasabah" />

    <PageHeader
        title="Impor data sistem lama"
        subtitle="Satu kali migrasi nasabah, rekening, dan saldo dari file Excel sistem lama."
    >
        <Link href="/nasabah"><Button label="Nasabah" icon="pi pi-arrow-left" severity="secondary" text /></Link>
    </PageHeader>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,26rem)_1fr]">
        <form class="card flex flex-col gap-5 self-start" @submit.prevent="periksa">
            <div class="flex flex-col gap-2">
                <label for="file" class="font-medium">File Excel (.xlsx)</label>
                <input
                    id="file"
                    type="file"
                    accept=".xlsx"
                    class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-primary-contrast"
                    @change="pilih"
                />
                <small class="text-muted-color">
                    Kolom: kelas, nipd, nisn, nama_nasabah, saldo, jenis_nasabah, jenis_rekening. Satu baris = satu
                    rekening; nomor rekening = nipd (nomor lama), bila kosong = prefix jenis + NISN.
                </small>
                <FieldError :message="form.errors.file" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="tanggal" class="font-medium">Tanggal saldo awal</label>
                <DatePicker v-model="tanggal" input-id="tanggal" date-format="dd/mm/yy" show-icon fluid />
                <small class="text-muted-color">Tanggal buka rekening dan tanggal jurnal saldo awal.</small>
                <FieldError :message="form.errors.tanggal" />
            </div>

            <Button
                type="submit"
                label="Periksa"
                icon="pi pi-search"
                severity="secondary"
                :loading="form.processing"
                :disabled="!file || !tanggal"
            />
            <small class="text-muted-color">Periksa tidak menyimpan apa pun.</small>
        </form>

        <div class="flex min-w-0 flex-col gap-5">
            <div v-if="!hasil" class="card text-muted-color">
                Pilih file lalu klik <strong>Periksa</strong> untuk melihat ringkasan, error, dan peringatan sebelum
                data disimpan.
            </div>

            <template v-else>
                <Message v-if="!sudahDiperiksa" severity="warn" :closable="false">
                    Hasil di bawah untuk file <strong>{{ hasil.file }}</strong
                    >. Pilih file yang sama lalu klik Periksa lagi sebelum mengimpor.
                </Message>

                <div class="card">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">Ringkasan · {{ hasil.file }}</h2>
                        <span class="text-sm text-muted-color">
                            Tanggal saldo awal <DateText :value="hasil.tanggal" />
                        </span>
                    </div>
                    <dl class="mb-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div>
                            <dt class="text-sm text-muted-color">Baris data</dt>
                            <dd class="text-xl font-semibold">{{ hasil.ringkasan.jumlah_baris }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-color">Nasabah</dt>
                            <dd class="text-xl font-semibold">{{ hasil.ringkasan.jumlah_nasabah }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-color">Rekening</dt>
                            <dd class="text-xl font-semibold">{{ hasil.ringkasan.jumlah_rekening }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-color">Total saldo</dt>
                            <dd class="text-xl font-semibold"><MoneyText :value="hasil.ringkasan.total_saldo" /></dd>
                        </div>
                    </dl>
                    <div class="grid gap-4 md:grid-cols-2">
                        <DataTable :value="hasil.ringkasan.nasabah_per_jenis" size="small">
                            <Column field="jenis" header="Jenis nasabah" />
                            <Column field="jumlah" header="Jumlah" class="text-right" />
                        </DataTable>
                        <DataTable :value="hasil.ringkasan.rekening_per_jenis" size="small">
                            <Column field="jenis" header="Jenis rekening" />
                            <Column field="jumlah" header="Jumlah" class="text-right" />
                            <Column header="Saldo" class="text-right">
                                <template #body="{ data }"><MoneyText :value="data.saldo" /></template>
                            </Column>
                        </DataTable>
                    </div>
                    <p class="mt-4 text-sm text-muted-color">
                        Saldo masuk sebagai saldo awal rekening (tidak tampil sebagai transaksi di sesi kas maupun buku
                        tabungan). Dibuat satu jurnal: D 1-101 Kas / K 2-101 Simpanan tabungan nasabah sebesar
                        <MoneyText :value="hasil.ringkasan.total_saldo" />.
                    </p>
                </div>

                <div class="card">
                    <h2 class="mb-3 text-lg font-semibold">
                        Error <span class="text-muted-color">({{ hasil.error.length }})</span>
                    </h2>
                    <Message v-if="!hasil.error.length" severity="success" :closable="false">
                        Tidak ada error. Data siap diimpor.
                    </Message>
                    <template v-else>
                        <Message severity="error" :closable="false" class="mb-3">
                            Perbaiki baris berikut di Excel, lalu periksa ulang. Selama ada error, tidak ada data yang
                            diimpor.
                        </Message>
                        <DataTable :value="hasil.error" size="small" paginator :rows="20" striped-rows>
                            <Column field="baris" header="Baris" class="w-24" />
                            <Column field="pesan" header="Pesan" />
                        </DataTable>
                    </template>
                </div>

                <div v-if="hasil.peringatan.length" class="card">
                    <h2 class="mb-3 text-lg font-semibold">
                        Peringatan <span class="text-muted-color">({{ hasil.peringatan.length }})</span>
                    </h2>
                    <p class="mb-3 text-sm text-muted-color">
                        Tetap diimpor; periksa dan perbaiki datanya setelah impor bila perlu.
                    </p>
                    <DataTable :value="hasil.peringatan" size="small" paginator :rows="20" striped-rows>
                        <Column field="baris" header="Baris" class="w-24" />
                        <Column field="pesan" header="Pesan" />
                    </DataTable>
                </div>

                <div class="flex justify-end">
                    <Button
                        label="Impor sekarang"
                        icon="pi pi-upload"
                        severity="warn"
                        :loading="form.processing"
                        :disabled="!bisaImpor"
                        @click="impor"
                    />
                </div>
            </template>
        </div>
    </div>
</template>
