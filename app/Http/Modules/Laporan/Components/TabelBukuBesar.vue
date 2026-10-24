<script setup>
import { Link } from '@inertiajs/vue3';
import DateText from '@/Components/DateText.vue';
import MoneyText from '@/Components/MoneyText.vue';
import { usePermission } from '@/composables/usePermission';

defineProps({
    data: { type: Object, required: true },
});

const { can } = usePermission();
</script>

<template>
    <div class="mb-3 font-semibold">Akun {{ data.akun.kode }} · {{ data.akun.nama }}</div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b-2 border-surface-400 text-left">
                    <th class="py-2 pr-3">Tanggal</th>
                    <th class="py-2 pr-3">Nomor</th>
                    <th class="py-2 pr-3">Keterangan</th>
                    <th class="py-2 pr-3 text-right">Debit</th>
                    <th class="py-2 pr-3 text-right">Kredit</th>
                    <th class="py-2 text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-surface-100 italic dark:border-surface-800">
                    <td colspan="5" class="py-1.5">Saldo awal</td>
                    <td class="py-1.5 text-right"><MoneyText :value="data.saldo_awal" colored /></td>
                </tr>
                <tr
                    v-for="(item, i) in data.baris"
                    :key="i"
                    class="border-b border-surface-100 dark:border-surface-800"
                >
                    <td class="py-1.5 pr-3"><DateText :value="item.tanggal" /></td>
                    <td class="py-1.5 pr-3 whitespace-nowrap">
                        <Link
                            v-if="can('jurnal.lihat')"
                            :href="`/jurnal/${item.jurnal_id}`"
                            class="text-primary hover:underline print:text-black"
                            >{{ item.nomor }}</Link
                        >
                        <span v-else>{{ item.nomor }}</span>
                    </td>
                    <td class="py-1.5 pr-3">{{ item.keterangan }}</td>
                    <td class="py-1.5 pr-3 text-right">
                        <MoneyText v-if="Number(item.debit) !== 0" :value="item.debit" />
                    </td>
                    <td class="py-1.5 pr-3 text-right">
                        <MoneyText v-if="Number(item.kredit) !== 0" :value="item.kredit" />
                    </td>
                    <td class="py-1.5 text-right"><MoneyText :value="item.saldo" colored /></td>
                </tr>
                <tr v-if="!data.baris.length">
                    <td colspan="6" class="py-3 text-center text-muted-color italic">
                        Tidak ada mutasi pada periode ini.
                    </td>
                </tr>
                <tr class="border-y-2 border-surface-400 font-semibold">
                    <td colspan="3" class="py-2">Total / saldo akhir</td>
                    <td class="py-2 pr-3 text-right"><MoneyText :value="data.total_debit" /></td>
                    <td class="py-2 pr-3 text-right"><MoneyText :value="data.total_kredit" /></td>
                    <td class="py-2 text-right"><MoneyText :value="data.saldo_akhir" colored /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
