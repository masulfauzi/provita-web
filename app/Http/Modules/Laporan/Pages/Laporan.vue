<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Select from 'primevue/select';
import FilterPeriode from '../Components/FilterPeriode.vue';
import KopLaporan from '../Components/KopLaporan.vue';
import TabelArusKas from '../Components/TabelArusKas.vue';
import TabelBukuBesar from '../Components/TabelBukuBesar.vue';
import TabelLabaRugi from '../Components/TabelLabaRugi.vue';
import TabelNeraca from '../Components/TabelNeraca.vue';
import TabelNeracaSaldo from '../Components/TabelNeracaSaldo.vue';
import TandaTangan from '../Components/TandaTangan.vue';

const props = defineProps({
    laporan: { type: String, required: true },
    judul: { type: String, required: true },
    perTanggal: { type: Boolean, default: false },
    periode: { type: Object, required: true },
    data: { type: Object, required: true },
    kop: { type: Object, required: true },
    daftar: { type: Array, default: () => [] },
    akunOptions: { type: Array, default: () => [] },
});

const tabel = {
    neraca: TabelNeraca,
    'laba-rugi': TabelLabaRugi,
    'arus-kas': TabelArusKas,
    'neraca-saldo': TabelNeracaSaldo,
    'buku-besar': TabelBukuBesar,
};

const params = computed(() => ({
    dari: props.periode.dari,
    sampai: props.periode.sampai,
    ...(props.laporan === 'buku-besar' ? { akun: props.data.akun.id } : {}),
}));

const urlExcel = computed(() => `/laporan/${props.laporan}/excel?${new URLSearchParams(params.value)}`);
const urlLaporan = (slug) => `/laporan/${slug}?dari=${props.periode.dari}&sampai=${props.periode.sampai}`;

function muat(ubahan) {
    router.get(`/laporan/${props.laporan}`, { ...params.value, ...ubahan }, { preserveScroll: true, replace: true });
}

const cetak = () => window.print();

// Mode gelap dimatikan sementara saat mencetak agar hasil cetak hitam di atas putih.
let gelap = false;
const sebelumCetak = () => {
    gelap = document.documentElement.classList.contains('app-dark');
    document.documentElement.classList.remove('app-dark');
};
const sesudahCetak = () => gelap && document.documentElement.classList.add('app-dark');

onMounted(() => {
    window.addEventListener('beforeprint', sebelumCetak);
    window.addEventListener('afterprint', sesudahCetak);
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeprint', sebelumCetak);
    window.removeEventListener('afterprint', sesudahCetak);
});
</script>

<template>
    <Head :title="judul" />

    <div class="mb-4 flex flex-wrap items-center gap-2 print:hidden">
        <Link href="/laporan" class="text-sm text-primary hover:underline"
            ><i class="pi pi-arrow-left text-xs" /> Laporan keuangan</Link
        >
        <span class="text-muted-color">/</span>
        <Link
            v-for="item in daftar"
            :key="item.slug"
            :href="urlLaporan(item.slug)"
            class="rounded-md px-2 py-1 text-sm"
            :class="
                item.slug === laporan
                    ? 'bg-primary-50 font-semibold text-primary dark:bg-primary-900/40'
                    : 'hover:bg-surface-100 dark:hover:bg-surface-800'
            "
            >{{ item.judul }}</Link
        >
    </div>

    <div class="card mb-5 flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div class="flex flex-wrap items-end gap-3">
            <FilterPeriode :periode="periode" :per-tanggal="perTanggal" @ubah="muat" />
            <div v-if="laporan === 'buku-besar'" class="flex flex-col gap-1">
                <label for="akun" class="text-sm font-medium">Akun</label>
                <Select
                    :model-value="data.akun.id"
                    input-id="akun"
                    :options="akunOptions"
                    option-label="label"
                    option-value="value"
                    filter
                    class="w-72"
                    @update:model-value="(akun) => muat({ akun })"
                />
            </div>
        </div>
        <div class="flex gap-2">
            <a :href="urlExcel">
                <Button label="Excel" icon="pi pi-file-excel" severity="success" outlined />
            </a>
            <Button label="Cetak" icon="pi pi-print" @click="cetak" />
        </div>
    </div>

    <div class="card">
        <KopLaporan :kop="kop" />
        <component :is="tabel[laporan]" :data="data" />
        <TandaTangan :kop="kop" />
    </div>
</template>
