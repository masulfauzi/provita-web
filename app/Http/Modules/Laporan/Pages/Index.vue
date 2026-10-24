<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Tag from 'primevue/tag';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterPeriode from '../Components/FilterPeriode.vue';

const props = defineProps({
    periode: { type: Object, required: true },
    daftar: { type: Array, default: () => [] },
    ringkasan: { type: Object, required: true },
});

const ikon = {
    neraca: 'pi pi-building-columns',
    'laba-rugi': 'pi pi-chart-line',
    'arus-kas': 'pi pi-wallet',
    'neraca-saldo': 'pi pi-list-check',
    'buku-besar': 'pi pi-book',
};

const keterangan = {
    neraca: 'Posisi aset, kewajiban (simpanan nasabah), dan ekuitas per tanggal.',
    'laba-rugi': 'Pendapatan dan beban pada periode, beserta laba atau rugi bersih.',
    'arus-kas': 'Penerimaan dan pengeluaran kas beserta saldo awal dan akhir.',
    'neraca-saldo': 'Saldo debit dan kredit setiap akun untuk memeriksa keseimbangan.',
    'buku-besar': 'Rincian mutasi dan saldo berjalan satu akun.',
};

const query = () => `?dari=${props.periode.dari}&sampai=${props.periode.sampai}`;

function ubah(periode) {
    router.get('/laporan', periode, { preserveScroll: true, preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Laporan keuangan" />

    <PageHeader
        title="Laporan keuangan"
        subtitle="Dihitung dari jurnal: transaksi tabungan otomatis dan jurnal umum."
    />

    <div class="card mb-5">
        <FilterPeriode :periode="periode" @ubah="ubah" />
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card">
            <div class="text-muted-color">Saldo kas</div>
            <div class="mt-2 text-2xl font-semibold"><MoneyText :value="ringkasan.kas" colored /></div>
        </div>
        <div class="card">
            <div class="text-muted-color">Total aset</div>
            <div class="mt-2 text-2xl font-semibold"><MoneyText :value="ringkasan.total_aset" colored /></div>
            <Tag v-if="!ringkasan.seimbang" value="Neraca tidak seimbang" severity="danger" class="mt-2" />
        </div>
        <div class="card">
            <div class="text-muted-color">Kewajiban (simpanan nasabah)</div>
            <div class="mt-2 text-2xl font-semibold"><MoneyText :value="ringkasan.simpanan" colored /></div>
        </div>
        <div class="card">
            <div class="text-muted-color">Laba (rugi) periode</div>
            <div class="mt-2 text-2xl font-semibold"><MoneyText :value="ringkasan.laba" colored /></div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Link
            v-for="item in daftar"
            :key="item.slug"
            :href="`/laporan/${item.slug}${query()}`"
            class="card flex items-start gap-4 transition-colors hover:border-primary"
        >
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary dark:bg-primary-900/40"
            >
                <i :class="ikon[item.slug]" />
            </span>
            <div>
                <div class="font-semibold">{{ item.judul }}</div>
                <div class="mt-1 text-sm text-muted-color">{{ keterangan[item.slug] }}</div>
            </div>
        </Link>
    </div>
</template>
