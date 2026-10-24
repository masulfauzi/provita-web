<script setup>
import BarisAkun from './BarisAkun.vue';
import BarisTotal from './BarisTotal.vue';
import JudulKelompok from './JudulKelompok.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<!-- Metode langsung: penerimaan/pengeluaran dikelompokkan menurut akun lawan kas. -->
<template>
    <table class="w-full max-w-3xl print:max-w-none">
        <tbody>
            <BarisTotal label="Saldo kas awal periode" :nilai="data.saldo_awal" />
            <JudulKelompok label="Penerimaan kas dari" />
            <BarisAkun :items="data.penerimaan" kosong="Tidak ada penerimaan." />
            <BarisTotal label="Total penerimaan" :nilai="data.total_penerimaan" />
            <JudulKelompok label="Pengeluaran kas untuk" />
            <BarisAkun :items="data.pengeluaran" kosong="Tidak ada pengeluaran." />
            <BarisTotal label="Total pengeluaran" :nilai="data.total_pengeluaran" />
            <tr>
                <td colspan="3" class="py-2" />
            </tr>
            <BarisTotal label="Kenaikan (penurunan) kas" :nilai="data.kenaikan" />
            <BarisTotal label="Saldo kas akhir periode" :nilai="data.saldo_akhir" utama />
        </tbody>
    </table>
</template>
