<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';
import { rupiah } from '@/utils/format';
import StatusPinjaman from '../Components/StatusPinjaman.vue';

const props = defineProps({
    pinjaman: { type: Object, required: true },
    angsuran: { type: Array, required: true },
    riwayat: { type: Array, default: () => [] },
    tanggal: { type: String, required: true },
});

const { can } = usePermission();
const confirm = useConfirm();

const berjalan = computed(() => props.pinjaman.status === 'berjalan');
const belum = computed(() => props.angsuran.filter((a) => !a.dibayar_tanggal));
const adaPembayaran = computed(() => props.angsuran.some((a) => a.dibayar_tanggal));
const angsuranPerBulan = computed(() => props.angsuran[0]?.total ?? 0);
const persen = computed(() => Number(props.pinjaman.jasa_persen).toLocaleString('id-ID', { maximumFractionDigits: 4 }));

const bayarForm = useForm({ jumlah: 1 });
const dibayar = computed(() => belum.value.slice(0, bayarForm.jumlah || 0));
const totalBayar = computed(() => dibayar.value.reduce((s, a) => s + Number(a.total), 0));

function bayar() {
    const ke = dibayar.value.map((a) => a.ke);
    confirm.require({
        header: 'Terima angsuran',
        message: `${props.pinjaman.nasabah.nama}\nAngsuran ke-${ke.length > 1 ? `${ke[0]}–${ke.at(-1)}` : ke[0]}: ${rupiah(totalBayar.value)} tunai.`,
        icon: 'pi pi-arrow-circle-down',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya, terima', severity: 'success' },
        accept: () =>
            bayarForm.post(`/pinjaman/${props.pinjaman.id}/bayar`, {
                preserveScroll: true,
                onSuccess: () => bayarForm.reset(),
            }),
    });
}

const dialog = ref(null); // 'bayar' | 'pinjaman'
const batalForm = useForm({ alasan: '' });

function bukaBatal(jenis) {
    batalForm.reset();
    batalForm.clearErrors();
    dialog.value = jenis;
}

function batal() {
    const url = dialog.value === 'bayar' ? 'batal-bayar' : 'batal';
    batalForm.post(`/pinjaman/${props.pinjaman.id}/${url}`, {
        preserveScroll: true,
        onSuccess: () => (dialog.value = null),
    });
}
</script>

<template>
    <Head :title="`Pinjaman ${pinjaman.nomor}`" />

    <PageHeader
        :title="`Pinjaman ${pinjaman.nomor}`"
        :subtitle="`${pinjaman.nasabah.nama} · ${pinjaman.nasabah.jenis}`"
    >
        <Link href="/pinjaman"
            ><Button label="Daftar pinjaman" icon="pi pi-arrow-left" severity="secondary" text
        /></Link>
        <Link v-if="can('jurnal.lihat') && pinjaman.jurnal_id" :href="`/jurnal/${pinjaman.jurnal_id}`">
            <Button label="Jurnal pencairan" icon="pi pi-book" severity="secondary" outlined />
        </Link>
        <Button
            v-if="can('pinjaman.batal') && adaPembayaran && pinjaman.status !== 'dibatalkan'"
            label="Batalkan pembayaran terakhir"
            icon="pi pi-undo"
            severity="danger"
            outlined
            @click="bukaBatal('bayar')"
        />
        <Button
            v-if="can('pinjaman.batal') && berjalan && !adaPembayaran"
            label="Batalkan pinjaman"
            icon="pi pi-times"
            severity="danger"
            outlined
            @click="bukaBatal('pinjaman')"
        />
    </PageHeader>

    <Message v-if="pinjaman.batal_at" severity="error" class="mb-4">
        Dibatalkan oleh {{ pinjaman.batal_user ?? '-' }} pada <DateText :value="pinjaman.batal_at" jam />. Alasan:
        {{ pinjaman.batal_alasan }}
    </Message>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,24rem)_1fr]">
        <div class="flex flex-col gap-5">
            <div class="card">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Ringkasan</h2>
                    <StatusPinjaman :status="pinjaman.status" />
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-muted-color">Nasabah</dt>
                    <dd class="font-medium">
                        {{ pinjaman.nasabah.nama }}
                        <div class="text-xs text-muted-color">{{ pinjaman.nasabah.nomor }}</div>
                    </dd>
                    <dt class="text-muted-color">Tanggal akad</dt>
                    <dd class="font-medium"><DateText :value="pinjaman.tanggal" /></dd>
                    <dt class="text-muted-color">Pokok</dt>
                    <dd class="font-medium"><MoneyText :value="pinjaman.pokok" /></dd>
                    <dt class="text-muted-color">Jangka waktu</dt>
                    <dd class="font-medium">{{ pinjaman.tenor }} bulan</dd>
                    <dt class="text-muted-color">Jasa</dt>
                    <dd class="font-medium">{{ persen }}% per bulan (flat)</dd>
                    <dt class="text-muted-color">Total jasa</dt>
                    <dd class="font-medium"><MoneyText :value="pinjaman.total_jasa" /></dd>
                    <dt class="text-muted-color">Angsuran per bulan</dt>
                    <dd class="font-medium"><MoneyText :value="angsuranPerBulan" /></dd>
                    <template v-if="pinjaman.status !== 'dibatalkan'">
                        <dt class="text-muted-color">Sisa pokok</dt>
                        <dd class="font-semibold"><MoneyText :value="pinjaman.sisa_pokok" /></dd>
                        <dt class="text-muted-color">Sisa jasa</dt>
                        <dd class="font-semibold"><MoneyText :value="pinjaman.sisa_jasa" /></dd>
                    </template>
                    <dt class="text-muted-color">Dicatat oleh</dt>
                    <dd class="font-medium">{{ pinjaman.pembuat ?? '-' }}</dd>
                    <template v-if="pinjaman.keterangan">
                        <dt class="text-muted-color">Keterangan</dt>
                        <dd>{{ pinjaman.keterangan }}</dd>
                    </template>
                </dl>
            </div>

            <form v-if="berjalan && can('pinjaman.angsuran')" class="card flex flex-col gap-4" @submit.prevent="bayar">
                <h2 class="text-lg font-semibold">Bayar angsuran</h2>
                <div class="flex flex-col gap-2">
                    <label for="jumlah" class="font-medium">Jumlah angsuran yang dibayar</label>
                    <InputNumber
                        v-model="bayarForm.jumlah"
                        input-id="jumlah"
                        :min="1"
                        :max="belum.length"
                        suffix=" kali"
                        show-buttons
                        :invalid="!!bayarForm.errors.jumlah"
                        fluid
                    />
                    <small class="text-muted-color">
                        Dibayar berurutan mulai angsuran ke-{{ belum[0]?.ke }}. Sisa {{ belum.length }} angsuran.
                    </small>
                    <FieldError :message="bayarForm.errors.jumlah" />
                </div>
                <div class="flex items-center justify-between rounded-md bg-emphasis p-3">
                    <span class="text-muted-color">Total diterima</span>
                    <MoneyText class="text-lg font-semibold" :value="totalBayar" />
                </div>
                <Button
                    type="submit"
                    label="Terima angsuran"
                    icon="pi pi-arrow-circle-down"
                    severity="success"
                    :loading="bayarForm.processing"
                    :disabled="!bayarForm.jumlah"
                />
            </form>
        </div>

        <div class="flex min-w-0 flex-col gap-5">
            <div class="card">
                <h2 class="mb-3 text-lg font-semibold">Jadwal angsuran</h2>
                <DataTable :value="angsuran" data-key="id" striped-rows size="small">
                    <Column field="ke" header="Ke" />
                    <Column header="Jatuh tempo">
                        <template #body="{ data }"><DateText :value="data.jatuh_tempo" /></template>
                    </Column>
                    <Column header="Pokok" class="text-right">
                        <template #body="{ data }"><MoneyText :value="data.pokok" /></template>
                    </Column>
                    <Column header="Jasa" class="text-right">
                        <template #body="{ data }"><MoneyText :value="data.jasa" /></template>
                    </Column>
                    <Column header="Angsuran" class="text-right">
                        <template #body="{ data }"><MoneyText class="font-semibold" :value="data.total" /></template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <StatusPinjaman v-if="pinjaman.status !== 'dibatalkan'" :status="data.status" />
                            <span v-else class="text-muted-color">-</span>
                            <div v-if="data.dibayar_tanggal" class="text-xs text-muted-color">
                                <DateText :value="data.dibayar_tanggal" /> · {{ data.nomor_bukti }}
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div class="card">
                <h2 class="mb-3 text-lg font-semibold">Riwayat kas</h2>
                <DataTable :value="riwayat" data-key="id" striped-rows size="small">
                    <template #empty>Belum ada transaksi.</template>
                    <Column header="Tanggal">
                        <template #body="{ data }"><DateText :value="data.tanggal" /></template>
                    </Column>
                    <Column header="Transaksi">
                        <template #body="{ data }">
                            <div :class="{ 'line-through opacity-60': data.dibatalkan }">
                                {{ data.jenis }} <span class="text-muted-color">{{ data.nomor }}</span>
                            </div>
                            <div class="text-xs text-muted-color">{{ data.keterangan }}</div>
                            <Tag v-if="data.dibatalkan" value="Dibatalkan" severity="danger" />
                        </template>
                    </Column>
                    <Column header="Masuk" class="text-right">
                        <template #body="{ data }"><MoneyText v-if="data.masuk" :value="data.masuk" /></template>
                    </Column>
                    <Column header="Keluar" class="text-right">
                        <template #body="{ data }"><MoneyText v-if="data.keluar" :value="data.keluar" /></template>
                    </Column>
                    <Column header="Petugas">
                        <template #body="{ data }">{{ data.petugas ?? '-' }}</template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>

    <Dialog
        :visible="!!dialog"
        modal
        :header="dialog === 'bayar' ? 'Batalkan pembayaran angsuran terakhir' : 'Batalkan pinjaman'"
        class="w-full max-w-md"
        @update:visible="(v) => !v && (dialog = null)"
    >
        <form class="flex flex-col gap-3" @submit.prevent="batal">
            <p class="text-sm text-muted-color">
                <template v-if="dialog === 'bayar'">
                    Uang angsuran terakhir dikembalikan dari kas dan jadwalnya kembali belum dibayar.
                </template>
                <template v-else>
                    Uang pinjaman <MoneyText :value="pinjaman.pokok" /> dikembalikan ke kas dan pinjaman ditandai
                    dibatalkan.
                </template>
            </p>
            <label for="alasan" class="font-medium">Alasan</label>
            <InputText id="alasan" v-model="batalForm.alasan" :invalid="!!batalForm.errors.alasan" autofocus />
            <FieldError :message="batalForm.errors.alasan" />
            <div class="mt-2 flex justify-end gap-2">
                <Button label="Batal" severity="secondary" text @click="dialog = null" />
                <Button
                    type="submit"
                    label="Ya, batalkan"
                    severity="danger"
                    :loading="batalForm.processing"
                    :disabled="!batalForm.alasan"
                />
            </div>
        </form>
    </Dialog>
</template>
