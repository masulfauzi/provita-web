<script setup>
import Message from 'primevue/message';
import MoneyText from '@/Components/MoneyText.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <Message v-if="!data.seimbang" severity="error" class="mb-4 print:hidden">Neraca saldo tidak seimbang.</Message>

    <table class="w-full max-w-4xl print:max-w-none">
        <thead>
            <tr class="border-b-2 border-surface-400 text-left">
                <th class="py-2 pr-3">Kode</th>
                <th class="py-2 pr-3">Akun</th>
                <th class="py-2 pr-3 text-right">Debit</th>
                <th class="py-2 text-right">Kredit</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in data.akun" :key="item.id" class="border-b border-surface-100 dark:border-surface-800">
                <td class="py-1.5 pr-3 text-muted-color tabular-nums print:text-black">{{ item.kode }}</td>
                <td class="py-1.5 pr-3">{{ item.nama }}</td>
                <td class="py-1.5 pr-3 text-right">
                    <MoneyText v-if="Number(item.debit) !== 0" :value="item.debit" />
                </td>
                <td class="py-1.5 text-right">
                    <MoneyText v-if="Number(item.kredit) !== 0" :value="item.kredit" />
                </td>
            </tr>
            <tr class="border-y-2 border-surface-400 font-semibold">
                <td />
                <td class="py-2 pr-3">Total</td>
                <td class="py-2 pr-3 text-right"><MoneyText :value="data.total_debit" /></td>
                <td class="py-2 text-right"><MoneyText :value="data.total_kredit" /></td>
            </tr>
        </tbody>
    </table>
</template>
