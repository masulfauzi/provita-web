<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import MoneyText from '@/Components/MoneyText.vue';
import { usePermission } from '@/composables/usePermission';
import BarisTotal from '../Components/BarisTotal.vue';
import JudulKelompok from '../Components/JudulKelompok.vue';
import KopLaporan from '../Components/KopLaporan.vue';

const props = defineProps({
    tanggal: { type: String, required: true },
    data: { type: Object, required: true },
    kop: { type: Object, required: true },
});

const { can } = usePermission();
const sesi = computed(() => props.data.sesi);
const status = computed(() => {
    if (!sesi.value) return { label: 'Sesi belum dibuka', severity: 'secondary' };
    return sesi.value.ditutup_at
        ? { label: 'Sesi ditutup', severity: 'info' }
        : { label: 'Sesi terbuka', severity: 'success' };
});

function toDate(value) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}

const pad = (n) => String(n).padStart(2, '0');
const pilihan = ref(toDate(props.tanggal));

function muat() {
    if (!pilihan.value) return;
    const d = pilihan.value;
    router.get(
        '/laporan/harian',
        { tanggal: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` },
        { replace: true },
    );
}

const jam = (nilai) =>
    nilai ? new Date(nilai).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-';
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
    <Head title="Laporan kas harian" />

    <div class="card mb-5 flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label for="tanggal" class="text-sm font-medium">Tanggal</label>
                <DatePicker v-model="pilihan" input-id="tanggal" date-format="dd/mm/yy" show-icon @date-select="muat" />
            </div>
            <Tag :value="status.label" :severity="status.severity" class="mb-2" />
        </div>
        <div class="flex gap-2">
            <Link v-if="can('kas.sesi')" href="/kas/sesi">
                <Button label="Sesi kas" icon="pi pi-wallet" severity="secondary" outlined />
            </Link>
            <a :href="`/laporan/harian/excel?tanggal=${tanggal}`">
                <Button label="Excel" icon="pi pi-file-excel" severity="success" outlined />
            </a>
            <Button label="Cetak" icon="pi pi-print" @click="cetak" />
        </div>
    </div>

    <Message v-if="!sesi" severity="warn" class="mb-4 print:hidden">
        Sesi kas tanggal ini belum dibuka, sehingga modal awal dihitung Rp 0.
    </Message>
    <Message v-else-if="data.setelah_tutup" severity="warn" class="mb-4 print:hidden">
        Ada transaksi setelah sesi ditutup. Saldo kas akhir di bawah berbeda dengan saldo sistem saat sesi ditutup
        (<MoneyText :value="sesi.saldo_sistem" />).
    </Message>

    <div class="card">
        <KopLaporan :kop="kop" />

        <div v-if="sesi" class="mb-4 grid gap-1 text-sm sm:grid-cols-2">
            <div>
                <span class="text-muted-color print:text-black">Dibuka:</span> {{ sesi.pembuka ?? '-' }}, pukul
                {{ jam(sesi.dibuka_at) }}
            </div>
            <div>
                <span class="text-muted-color print:text-black">Ditutup:</span>&nbsp;<template v-if="sesi.ditutup_at"
                    >{{ sesi.penutup ?? '-' }}, pukul {{ jam(sesi.ditutup_at) }}</template
                >
                <template v-else>belum</template>
            </div>
        </div>

        <table class="mb-8 w-full max-w-3xl print:max-w-none">
            <tbody>
                <BarisTotal label="Modal awal" :nilai="data.modal_awal" />
                <template v-for="bagian in ['pemasukan', 'pengeluaran']" :key="bagian">
                    <JudulKelompok
                        :label="bagian === 'pemasukan' ? 'Pemasukan (uang masuk)' : 'Pengeluaran (uang keluar)'"
                    />
                    <tr
                        v-for="item in data[bagian]"
                        :key="item.id"
                        class="border-b border-surface-100 dark:border-surface-800"
                    >
                        <td class="py-1.5 pl-4 pr-3 text-muted-color tabular-nums print:text-black">{{ item.kode }}</td>
                        <td class="py-1.5 pr-3">
                            {{ item.nama }}
                            <span class="text-xs text-muted-color print:text-black">({{ item.jumlah }} transaksi)</span>
                        </td>
                        <td class="py-1.5 text-right"><MoneyText :value="item.nilai" /></td>
                    </tr>
                    <tr v-if="!data[bagian].length">
                        <td colspan="3" class="py-1.5 pl-4 text-muted-color italic">Tidak ada.</td>
                    </tr>
                    <BarisTotal
                        :label="bagian === 'pemasukan' ? 'Total pemasukan' : 'Total pengeluaran'"
                        :nilai="data[`total_${bagian}`]"
                    />
                </template>
                <tr>
                    <td colspan="3" class="py-2" />
                </tr>
                <BarisTotal label="Saldo kas akhir (modal + pemasukan − pengeluaran)" :nilai="data.saldo_akhir" utama />
                <template v-if="sesi?.ditutup_at">
                    <BarisTotal label="Uang fisik saat sesi ditutup" :nilai="sesi.uang_fisik" />
                    <BarisTotal label="Selisih (uang fisik − saldo sistem)" :nilai="sesi.selisih" />
                    <tr v-if="sesi.keterangan">
                        <td />
                        <td colspan="2" class="py-1 text-sm">Keterangan: {{ sesi.keterangan }}</td>
                    </tr>
                </template>
            </tbody>
        </table>

        <h3 class="mb-2 font-semibold">Rincian transaksi</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-surface-300 text-left">
                        <th class="py-2 pr-3">Waktu</th>
                        <th class="py-2 pr-3">Jenis</th>
                        <th class="py-2 pr-3">Referensi</th>
                        <th class="py-2 pr-3">Nasabah / keterangan</th>
                        <th class="py-2 pr-3 text-right">Masuk</th>
                        <th class="py-2 pr-3 text-right">Keluar</th>
                        <th class="py-2">Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in data.rincian"
                        :key="item.id"
                        class="border-b border-surface-100 dark:border-surface-800"
                    >
                        <td class="py-1.5 pr-3 tabular-nums">{{ item.waktu }}</td>
                        <td class="py-1.5 pr-3">{{ item.jenis }}</td>
                        <td class="py-1.5 pr-3 whitespace-nowrap tabular-nums">{{ item.referensi ?? '-' }}</td>
                        <td class="py-1.5 pr-3">
                            <div>{{ item.nasabah ?? '-' }}</div>
                            <div v-if="item.keterangan" class="text-xs text-muted-color print:text-black">
                                {{ item.keterangan }}
                            </div>
                        </td>
                        <td class="py-1.5 pr-3 text-right"><MoneyText v-if="item.masuk" :value="item.masuk" /></td>
                        <td class="py-1.5 pr-3 text-right"><MoneyText v-if="item.keluar" :value="item.keluar" /></td>
                        <td class="py-1.5">{{ item.petugas ?? '-' }}</td>
                    </tr>
                    <tr v-if="!data.rincian.length">
                        <td colspan="7" class="py-3 text-center text-muted-color italic">Tidak ada transaksi.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tanda tangan; hanya tampil saat dicetak. -->
        <div class="mt-12 hidden grid-cols-2 gap-8 text-center print:grid">
            <div>
                <div>Diperiksa,</div>
                <div>Bendahara</div>
                <div class="mt-20 font-semibold">........................................</div>
            </div>
            <div>
                <div>Semarang, {{ kop.tanggal_cetak }}</div>
                <div>Teller</div>
                <div class="mt-20 font-semibold">{{ sesi?.penutup || '........................................' }}</div>
            </div>
        </div>
    </div>
</template>
