<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    rekening: { type: Object, required: true },
    filter: { type: Object, required: true },
    baris: { type: Array, default: () => [] },
    posisi: { type: Object, required: true },
    terakhir: { type: Object, default: null },
    tataLetak: { type: Object, required: true },
});

const confirm = useConfirm();
const { can } = usePermission();

const KOLOM = ['No', 'Tanggal', 'Sandi', 'Debit', 'Kredit', 'Saldo', 'Paraf'];
const modeOptions = [
    { label: 'Belum dicetak', value: 'belum' },
    { label: 'Rentang nomor', value: 'nomor' },
    { label: 'Rentang tanggal', value: 'tanggal' },
];

function toDate(value) {
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}
const pad = (n) => String(n).padStart(2, '0');
const toDateString = (date) =>
    date ? `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` : undefined;

// Pilihan data
const mode = ref(props.filter.mode ?? 'belum');
const dariNo = ref(props.filter.dari_no ? Number(props.filter.dari_no) : null);
const sampaiNo = ref(props.filter.sampai_no ? Number(props.filter.sampai_no) : null);
const dari = ref(toDate(props.filter.dari));
const sampai = ref(toDate(props.filter.sampai));

function tampilkan() {
    const params = { mode: mode.value };
    if (mode.value === 'nomor')
        Object.assign(params, { dari_no: dariNo.value ?? undefined, sampai_no: sampaiNo.value ?? undefined });
    if (mode.value === 'tanggal')
        Object.assign(params, { dari: toDateString(dari.value), sampai: toDateString(sampai.value) });
    router.get(`/rekening/${props.rekening.id}/cetak`, params, { preserveScroll: true, replace: true });
}

// Centang manual: semua baris hasil filter terpilih saat dimuat.
const terpilih = ref([...props.baris]);
watch(
    () => props.baris,
    (baris) => (terpilih.value = [...baris]),
);

// Posisi mulai di buku
const halaman = ref(props.posisi.halaman);
const barisMulai = ref(props.posisi.baris);
watch(
    () => props.posisi,
    (posisi) => {
        halaman.value = posisi.halaman;
        barisMulai.value = posisi.baris;
    },
);

const perHalaman = computed(() => props.tataLetak.baris_per_halaman);
const urutTerpilih = computed(() => [...terpilih.value].sort((a, b) => a.no - b.no));

// Setiap baris terpilih mendapat (halaman, baris) berurutan, pindah halaman otomatis.
const halamanCetak = computed(() => {
    const awal =
        (Math.max(halaman.value || 1, 1) - 1) * perHalaman.value +
        (Math.min(Math.max(barisMulai.value || 1, 1), perHalaman.value) - 1);
    const hasil = new Map();
    urutTerpilih.value.forEach((item, i) => {
        const indeks = awal + i;
        const hal = Math.floor(indeks / perHalaman.value) + 1;
        if (!hasil.has(hal)) hasil.set(hal, []);
        hasil.get(hal).push({ ...item, baris: (indeks % perHalaman.value) + 1 });
    });
    return [...hasil.entries()].map(([nomor, items]) => ({ nomor, items }));
});

// Ukuran & posisi (mm)
const lebar = computed(() => props.tataLetak.lebar_kolom);
const kiriKolom = computed(() =>
    lebar.value.map((_, i) => props.tataLetak.jarak_kiri + lebar.value.slice(0, i).reduce((a, b) => a + b, 0)),
);
const lebarHalaman = computed(() => props.tataLetak.jarak_kiri + lebar.value.reduce((a, b) => a + b, 0) + 10);
const tinggiHalaman = computed(() => props.tataLetak.jarak_atas + perHalaman.value * props.tataLetak.tinggi_baris + 10);
const atasBaris = (baris) => props.tataLetak.jarak_atas + (baris - 1) * props.tataLetak.tinggi_baris;

const angka = (nilai) =>
    nilai === null || nilai === undefined ? '' : Number(nilai).toLocaleString('id-ID', { maximumFractionDigits: 2 });
const tglPendek = (nilai) => {
    const [y, m, d] = nilai.split('-');
    return `${d}/${m}/${y.slice(2)}`;
};
const sel = (item) => [
    item.no,
    tglPendek(item.tanggal),
    item.sandi,
    angka(item.debit),
    angka(item.kredit),
    angka(item.saldo),
    item.paraf,
];
const RATA_KANAN = [3, 4, 5];

// Cetak, lalu tanyakan apakah hasilnya benar sebelum ditandai sudah dicetak.
const menungguKonfirmasi = ref(false);

function cetak() {
    menungguKonfirmasi.value = true;
    window.print();
}

function sesudahCetak() {
    if (!menungguKonfirmasi.value) return;
    menungguKonfirmasi.value = false;

    confirm.require({
        header: 'Konfirmasi cetak',
        message: `Apakah ${urutTerpilih.value.length} baris buku tabungan tercetak dengan benar?`,
        icon: 'pi pi-print',
        rejectProps: { label: 'Belum, jangan tandai', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya, tandai sudah dicetak', severity: 'success' },
        accept: () =>
            router.post(
                `/rekening/${props.rekening.id}/cetak`,
                {
                    transaksi: urutTerpilih.value.map((item) => item.id),
                    halaman: halaman.value,
                    baris: barisMulai.value,
                },
                { preserveScroll: true },
            ),
    });
}

onMounted(() => window.addEventListener('afterprint', sesudahCetak));
onBeforeUnmount(() => window.removeEventListener('afterprint', sesudahCetak));
</script>

<template>
    <Head :title="`Cetak buku ${rekening.nomor}`" />

    <PageHeader
        :title="`Cetak buku tabungan ${rekening.nomor}`"
        :subtitle="`${rekening.nasabah?.nama ?? ''}${rekening.nasabah?.kelas ? ' · ' + rekening.nasabah.kelas : ''}`"
    >
        <Link v-if="can('pengaturan.ubah_umum')" href="/pengaturan">
            <Button label="Atur posisi cetak" icon="pi pi-sliders-h" severity="secondary" text />
        </Link>
        <Link :href="`/rekening/${rekening.id}`">
            <Button label="Buku tabungan" icon="pi pi-arrow-left" severity="secondary" outlined />
        </Link>
    </PageHeader>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto]">
        <div class="flex min-w-0 flex-col gap-5">
            <!-- Pilihan data -->
            <div class="card flex flex-col gap-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-medium">Data yang dicetak</span>
                        <SelectButton
                            v-model="mode"
                            :options="modeOptions"
                            option-label="label"
                            option-value="value"
                            :allow-empty="false"
                        />
                    </div>
                    <template v-if="mode === 'nomor'">
                        <div class="flex flex-col gap-1">
                            <label for="dari_no" class="text-sm font-medium">Dari No</label>
                            <InputNumber
                                v-model="dariNo"
                                input-id="dari_no"
                                :min="1"
                                :use-grouping="false"
                                class="w-24"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="sampai_no" class="text-sm font-medium">Sampai No</label>
                            <InputNumber
                                v-model="sampaiNo"
                                input-id="sampai_no"
                                :min="1"
                                :use-grouping="false"
                                class="w-24"
                            />
                        </div>
                    </template>
                    <template v-if="mode === 'tanggal'">
                        <div class="flex flex-col gap-1">
                            <label for="dari" class="text-sm font-medium">Dari tanggal</label>
                            <DatePicker v-model="dari" input-id="dari" date-format="dd/mm/yy" show-icon />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="sampai" class="text-sm font-medium">Sampai tanggal</label>
                            <DatePicker v-model="sampai" input-id="sampai" date-format="dd/mm/yy" show-icon />
                        </div>
                    </template>
                    <Button label="Tampilkan" icon="pi pi-filter" outlined @click="tampilkan" />
                </div>

                <DataTable
                    v-model:selection="terpilih"
                    :value="baris"
                    data-key="id"
                    size="small"
                    striped-rows
                    scrollable
                    scroll-height="22rem"
                >
                    <template #empty>
                        {{
                            filter.mode === 'belum'
                                ? 'Semua mutasi sudah dicetak.'
                                : 'Tidak ada mutasi pada rentang ini.'
                        }}
                    </template>
                    <Column selection-mode="multiple" header-style="width: 3rem" />
                    <Column field="no" header="No" />
                    <Column header="Tanggal">
                        <template #body="{ data }">{{ tglPendek(data.tanggal) }}</template>
                    </Column>
                    <Column field="sandi" header="Sandi" />
                    <Column header="Debit" class="text-right">
                        <template #body="{ data }">{{ angka(data.debit) }}</template>
                    </Column>
                    <Column header="Kredit" class="text-right">
                        <template #body="{ data }">{{ angka(data.kredit) }}</template>
                    </Column>
                    <Column header="Saldo" class="text-right">
                        <template #body="{ data }">{{ angka(data.saldo) }}</template>
                    </Column>
                    <Column field="paraf" header="Paraf" />
                    <Column header="Status">
                        <template #body="{ data }">
                            <Tag
                                v-if="data.dicetak"
                                :value="`Sudah dicetak${data.dicetak > 1 ? ' ' + data.dicetak + '×' : ''}`"
                                severity="secondary"
                            />
                            <Tag v-else value="Belum" severity="warn" />
                        </template>
                    </Column>
                </DataTable>
                <small class="text-muted-color">
                    Sandi: 01 setoran · 02 penarikan · 03 koreksi · 04 biaya administrasi · 05 tutup rekening · 06
                    autodebet keluar · 07 autodebet masuk. Paraf = inisial petugas.
                </small>
            </div>
        </div>

        <!-- Posisi & pratinjau -->
        <div class="card flex flex-col gap-4 self-start xl:w-[30rem]">
            <div class="text-sm text-muted-color">
                <i class="pi pi-bookmark" />
                <template v-if="terakhir">
                    Terakhir terisi: halaman {{ terakhir.halaman }} baris {{ terakhir.baris }}.</template
                >
                <template v-else> Buku ini belum pernah dicetak.</template>
                {{ perHalaman }} baris per halaman.
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label for="halaman" class="text-sm font-medium">Mulai halaman</label>
                    <InputNumber
                        v-model="halaman"
                        input-id="halaman"
                        :min="1"
                        :max="999"
                        :use-grouping="false"
                        show-buttons
                        class="w-32"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label for="baris_mulai" class="text-sm font-medium">Baris</label>
                    <InputNumber
                        v-model="barisMulai"
                        input-id="baris_mulai"
                        :min="1"
                        :max="perHalaman"
                        :use-grouping="false"
                        show-buttons
                        class="w-32"
                    />
                </div>
            </div>

            <Message v-if="halamanCetak.length > 1" severity="info" size="small">
                Mencetak {{ urutTerpilih.length }} baris di {{ halamanCetak.length }} halaman (halaman
                {{ halamanCetak.map((h) => h.nomor).join(', ') }}). Printer akan mencetak per halaman; masukkan halaman
                buku berikutnya setelah satu halaman selesai.
            </Message>

            <!-- Pratinjau halaman pertama -->
            <div
                v-if="halamanCetak.length"
                class="overflow-auto rounded border border-surface-200 bg-white p-2 dark:border-surface-700"
            >
                <div class="mb-1 text-xs text-surface-500">Pratinjau halaman {{ halamanCetak[0].nomor }}</div>
                <div
                    class="pratinjau relative text-black"
                    :style="{ width: `${lebarHalaman}mm`, height: `${tinggiHalaman}mm`, zoom: 0.62 }"
                >
                    <div
                        v-for="n in perHalaman"
                        :key="`garis-${n}`"
                        class="absolute border-b border-dashed border-surface-200"
                        :class="{ 'bg-surface-100': halamanCetak[0].nomor === halaman && n < barisMulai }"
                        :style="{
                            top: `${atasBaris(n)}mm`,
                            left: `${tataLetak.jarak_kiri}mm`,
                            width: `${lebarHalaman - tataLetak.jarak_kiri - 10}mm`,
                            height: `${tataLetak.tinggi_baris}mm`,
                        }"
                    />
                    <div
                        v-for="item in halamanCetak[0].items"
                        :key="item.id"
                        class="baris-buku absolute"
                        :style="{ top: `${atasBaris(item.baris)}mm`, height: `${tataLetak.tinggi_baris}mm` }"
                    >
                        <span
                            v-for="(isi, k) in sel(item)"
                            :key="k"
                            class="absolute"
                            :class="{ 'text-right': RATA_KANAN.includes(k) }"
                            :style="{ left: `${kiriKolom[k]}mm`, width: `${lebar[k]}mm` }"
                            >{{ isi }}</span
                        >
                    </div>
                </div>
                <div class="mt-1 flex text-[10px] text-surface-500">
                    <span v-for="(nama, k) in KOLOM" :key="nama" class="mr-2">{{ nama }} {{ lebar[k] }}mm</span>
                </div>
            </div>

            <Button
                label="Cetak"
                icon="pi pi-print"
                :disabled="!urutTerpilih.length || !halaman || !barisMulai"
                @click="cetak"
            />
        </div>
    </div>

    <!-- Area cetak: di luar #app agar posisi dihitung dari tepi kertas. -->
    <Teleport to="body">
        <div class="cetak-buku">
            <div
                v-for="hal in halamanCetak"
                :key="hal.nomor"
                class="halaman-buku"
                :style="{ width: `${lebarHalaman}mm`, height: `${tinggiHalaman}mm` }"
            >
                <div
                    v-for="item in hal.items"
                    :key="item.id"
                    class="baris-buku"
                    :style="{ top: `${atasBaris(item.baris)}mm`, height: `${tataLetak.tinggi_baris}mm` }"
                >
                    <span
                        v-for="(isi, k) in sel(item)"
                        :key="k"
                        :class="{ kanan: RATA_KANAN.includes(k) }"
                        :style="{ left: `${kiriKolom[k]}mm`, width: `${lebar[k]}mm` }"
                        >{{ isi }}</span
                    >
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style>
.cetak-buku {
    display: none;
}

.baris-buku {
    left: 0;
    right: 0;
    font-family: 'Courier New', monospace;
    font-size: 9pt;
    display: flex;
    align-items: center;
}

.baris-buku span {
    position: absolute;
    box-sizing: border-box;
    padding: 0 1mm;
    white-space: nowrap;
    overflow: hidden;
}

.baris-buku .kanan {
    text-align: right;
}

@media print {
    @page {
        margin: 0;
    }

    /* Hanya area cetak; aplikasi, dialog, dan notifikasi tidak ikut tercetak. */
    body > *:not(.cetak-buku) {
        display: none !important;
    }

    .cetak-buku {
        display: block;
    }

    .halaman-buku {
        position: relative;
        page-break-after: always;
        break-after: page;
        color: #000;
    }

    .halaman-buku:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    .halaman-buku .baris-buku {
        position: absolute;
    }
}
</style>
