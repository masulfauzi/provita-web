<script setup>
import MoneyText from '@/Components/MoneyText.vue';

defineProps({
    items: { type: Array, default: () => [] },
    kosong: { type: String, default: 'Tidak ada saldo.' },
});
</script>

<!-- Baris akun (kode, nama, nilai) untuk tabel laporan bertingkat. -->
<template>
    <tr v-for="item in items" :key="item.id" class="border-b border-surface-100 dark:border-surface-800">
        <td class="py-1.5 pl-4 pr-3 text-muted-color tabular-nums print:text-black">{{ item.kode }}</td>
        <td class="py-1.5 pr-3">{{ item.nama }}</td>
        <td class="py-1.5 text-right"><MoneyText :value="item.nilai" colored /></td>
    </tr>
    <tr v-if="!items.length">
        <td colspan="3" class="py-1.5 pl-4 text-muted-color italic">{{ kosong }}</td>
    </tr>
</template>
