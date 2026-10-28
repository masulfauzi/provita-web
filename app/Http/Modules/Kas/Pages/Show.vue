<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyText from '@/Components/MoneyText.vue';
import { usePermission } from '@/composables/usePermission';
import { terbilang } from '@/utils/format';

const props = defineProps({
    kas: { type: Object, required: true },
    judul: { type: String, required: true },
    kop: { type: Object, required: true },
});

const { can } = usePermission();
const masuk = computed(() => props.kas.jenis === 'kas_masuk');
const kembali = computed(() => (masuk.value ? '/kas/penerimaan' : '/kas/pengeluaran'));
const dialog = ref(false);
const form = useForm({ alasan: '' });

function batal() {
    form.post(`/kas/${props.kas.id}/batal`, { preserveScroll: true, onSuccess: () => (dialog.value = false) });
}

const cetak = () => window.print();
</script>

<template>
    <Head :title="`${judul} ${kas.nomor}`" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 print:hidden">
        <Link :href="kembali"><Button :label="judul" icon="pi pi-arrow-left" severity="secondary" text /></Link>
        <div class="flex gap-2">
            <Link v-if="can('jurnal.lihat')" :href="`/jurnal/${kas.jurnal_id}`">
                <Button label="Lihat jurnal" icon="pi pi-book" severity="secondary" outlined />
            </Link>
            <Button
                v-if="can('kas.batal') && !kas.batal_at"
                label="Batalkan"
                icon="pi pi-times"
                severity="danger"
                outlined
                @click="dialog = true"
            />
            <Button label="Cetak bukti" icon="pi pi-print" @click="cetak" />
        </div>
    </div>

    <Message v-if="kas.batal_at" severity="error" class="mb-4 max-w-3xl print:hidden">
        Dibatalkan oleh {{ kas.batal_user?.name ?? '-' }} pada <DateText :value="kas.batal_at" jam />. Alasan:
        {{ kas.batal_alasan }}
    </Message>

    <div class="card max-w-3xl">
        <div
            class="mb-5 flex items-start justify-between gap-4 border-b-2 border-surface-800 pb-3 dark:border-surface-200 print:border-black"
        >
            <div>
                <div class="text-lg font-bold uppercase">{{ kop.nama_bank }}</div>
                <div v-if="kop.nama_sekolah">{{ kop.nama_sekolah }}</div>
                <div v-if="kop.alamat_sekolah" class="text-sm text-muted-color print:text-black">
                    {{ kop.alamat_sekolah }}
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-semibold uppercase">Bukti {{ judul.toLowerCase() }}</div>
                <div class="font-mono">{{ kas.nomor }}</div>
                <div v-if="kas.batal_at" class="mt-1 font-bold text-red-600 uppercase print:text-black">Dibatalkan</div>
            </div>
        </div>

        <dl class="grid grid-cols-[10rem_1fr] gap-x-4 gap-y-3">
            <dt class="text-muted-color print:text-black">Tanggal</dt>
            <dd><DateText :value="kas.tanggal" hari /></dd>

            <template v-if="masuk">
                <dt class="text-muted-color print:text-black">Diterima dari</dt>
                <dd>
                    <template v-if="kas.nasabah">
                        {{ kas.nasabah.nama }} ({{ kas.nasabah.nomor
                        }}<span v-if="kas.nasabah.kelas">, {{ kas.nasabah.kelas }}</span
                        >)
                    </template>
                    <template v-else>-</template>
                </dd>
            </template>

            <dt class="text-muted-color print:text-black">{{ masuk ? 'Untuk pembayaran' : 'Dibayarkan untuk' }}</dt>
            <dd>{{ kas.keterangan }}</dd>

            <dt class="text-muted-color print:text-black">Kategori</dt>
            <dd>{{ kas.akun?.kode }} · {{ kas.akun?.nama }}</dd>

            <dt class="text-muted-color print:text-black">Jumlah</dt>
            <dd class="text-xl font-semibold"><MoneyText :value="kas.nominal" /></dd>

            <dt class="text-muted-color print:text-black">Terbilang</dt>
            <dd class="capitalize italic">{{ terbilang(kas.nominal) }}</dd>
        </dl>

        <div class="mt-12 grid grid-cols-2 gap-8 text-center">
            <div>
                <div>{{ masuk ? 'Penyetor' : 'Penerima' }}</div>
                <div class="mt-16">........................................</div>
            </div>
            <div>
                <div>Petugas</div>
                <div class="mt-16 font-semibold">
                    {{ kas.user?.name ?? '........................................' }}
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:visible="dialog" modal :header="`Batalkan ${kas.nomor}`" class="w-full max-w-md">
        <p class="mb-4">
            Transaksi pembalik dan jurnal baliknya akan dicatat pada tanggal transaksi aktif. Bukti ini tetap tersimpan
            dengan tanda dibatalkan.
        </p>
        <div class="flex flex-col gap-2">
            <label for="alasan" class="font-medium">Alasan pembatalan</label>
            <InputText id="alasan" v-model="form.alasan" autofocus :invalid="!!form.errors.alasan" />
            <FieldError :message="form.errors.alasan" />
        </div>
        <template #footer>
            <Button label="Tutup" severity="secondary" text @click="dialog = false" />
            <Button label="Batalkan bukti" severity="danger" :loading="form.processing" @click="batal" />
        </template>
    </Dialog>
</template>
