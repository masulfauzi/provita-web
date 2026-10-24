<script setup>
import Message from 'primevue/message';
import BarisAkun from './BarisAkun.vue';
import BarisTotal from './BarisTotal.vue';
import JudulKelompok from './JudulKelompok.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <Message v-if="!data.seimbang" severity="error" class="mb-4 print:hidden">
        Neraca tidak seimbang. Periksa jurnal yang tercatat.
    </Message>

    <div class="grid items-start gap-8 lg:grid-cols-2 print:grid-cols-2">
        <table class="w-full">
            <tbody>
                <JudulKelompok label="Aset" />
                <BarisAkun :items="data.aset" />
                <BarisTotal label="Total aset" :nilai="data.total_aset" utama />
            </tbody>
        </table>

        <table class="w-full">
            <tbody>
                <JudulKelompok label="Kewajiban" />
                <BarisAkun :items="data.kewajiban" />
                <BarisTotal label="Total kewajiban" :nilai="data.total_kewajiban" />
                <JudulKelompok label="Ekuitas" />
                <BarisAkun :items="data.ekuitas" />
                <BarisAkun
                    :items="[{ id: 'laba', kode: '', nama: 'Laba (rugi) berjalan', nilai: data.laba_berjalan }]"
                />
                <BarisTotal label="Total ekuitas" :nilai="data.total_ekuitas" />
                <BarisTotal label="Total kewajiban & ekuitas" :nilai="data.total_pasiva" utama />
            </tbody>
        </table>
    </div>
</template>
