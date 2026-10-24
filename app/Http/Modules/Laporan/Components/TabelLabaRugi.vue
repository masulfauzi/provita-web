<script setup>
import BarisAkun from './BarisAkun.vue';
import BarisTotal from './BarisTotal.vue';
import JudulKelompok from './JudulKelompok.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <table class="w-full max-w-3xl print:max-w-none">
        <tbody>
            <JudulKelompok label="Pendapatan" />
            <BarisAkun :items="data.pendapatan" />
            <BarisTotal label="Total pendapatan" :nilai="data.total_pendapatan" />
            <JudulKelompok label="Beban" />
            <BarisAkun :items="data.beban" />
            <BarisTotal label="Total beban" :nilai="data.total_beban" />
            <tr>
                <td colspan="3" class="py-2" />
            </tr>
            <BarisTotal
                :label="String(data.laba).startsWith('-') ? 'Rugi bersih' : 'Laba bersih'"
                :nilai="data.laba"
                utama
            />
        </tbody>
    </table>
</template>
