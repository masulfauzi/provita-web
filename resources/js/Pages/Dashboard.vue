<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';
import { tanggal as tanggalText } from '@/utils/format';

defineProps({
    tanggal: { type: String, default: null },
    ringkasan: { type: Array, default: () => [] },
});

const { can } = usePermission();

// Kartu hanya menjadi link bila user boleh membuka halaman tujuannya.
const izin = { '/rekening': 'rekening.lihat', '/nasabah': 'nasabah.lihat', '/kas/sesi': 'kas.sesi' };
const bisaDibuka = (url) => url && can(izin[url] ?? '');
</script>

<template>
    <Head title="Dashboard" />

    <PageHeader title="Dashboard" :subtitle="`Ringkasan tanggal transaksi ${tanggalText(tanggal, { hari: true })}`" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <component
            :is="bisaDibuka(kartu.url) ? Link : 'div'"
            v-for="kartu in ringkasan"
            :key="kartu.label"
            :href="bisaDibuka(kartu.url) ? kartu.url : undefined"
            class="card flex items-start justify-between gap-3"
            :class="{ 'transition-colors hover:border-primary': bisaDibuka(kartu.url) }"
        >
            <div class="min-w-0">
                <div class="text-muted-color">{{ kartu.label }}</div>
                <div class="mt-2 text-2xl font-semibold">
                    <MoneyText v-if="kartu.tipe === 'uang'" :value="kartu.nilai" />
                    <span v-else>{{ kartu.nilai }}</span>
                </div>
                <div v-if="kartu.keterangan" class="mt-1 text-xs text-muted-color">{{ kartu.keterangan }}</div>
            </div>
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary dark:bg-primary-900/40"
            >
                <i :class="kartu.icon" />
            </span>
        </component>
    </div>
</template>
