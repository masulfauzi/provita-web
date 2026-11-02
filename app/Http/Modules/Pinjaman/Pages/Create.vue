<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ColumnGroup from 'primevue/columngroup';
import DataTable from 'primevue/datatable';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Row from 'primevue/row';
import { useConfirm } from 'primevue/useconfirm';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useSearch } from '@/composables/useSearch';
import { rupiah, terbilang } from '@/utils/format';
import { useJadwalPinjaman } from '../Components/useJadwalPinjaman';

const props = defineProps({
    tanggal: { type: String, required: true },
    plafon: { type: String, required: true },
    jasaPersen: { type: String, required: true },
    tenorMaksimal: { type: Number, required: true },
});

const confirm = useConfirm();
const { suggestions, search } = useSearch('/pinjaman/cari-nasabah');
const nasabahTerpilih = ref(null);

const form = useForm({ nasabah_id: null, pokok: null, tenor: null, keterangan: '' });

const adaPlafon = computed(() => Number(props.plafon) > 0);
const persen = computed(() => Number(props.jasaPersen).toLocaleString('id-ID', { maximumFractionDigits: 4 }));
const jadwal = useJadwalPinjaman(
    () => form.pokok,
    () => form.tenor,
    () => props.jasaPersen,
    () => props.tanggal,
);
const total = computed(() => ({
    pokok: jadwal.value.reduce((s, r) => s + r.pokok, 0),
    jasa: jadwal.value.reduce((s, r) => s + r.jasa, 0),
    total: jadwal.value.reduce((s, r) => s + r.total, 0),
}));
const teksTerbilang = computed(() => (form.pokok ? terbilang(form.pokok) : ''));
const berjalan = computed(() => nasabahTerpilih.value?.pinjaman_berjalan ?? null);

const labelNasabah = (n) => (n && typeof n === 'object' ? `${n.nama} (${n.nomor})` : n);

function submit() {
    confirm.require({
        header: 'Simpan & cairkan pinjaman',
        message: `${nasabahTerpilih.value.nama}\nPokok ${rupiah(form.pokok)} selama ${form.tenor} bulan\nAngsuran ${rupiah(jadwal.value[0]?.total)} per bulan.\nUang diserahkan tunai dari kas.`,
        icon: 'pi pi-briefcase',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya, cairkan', severity: 'warn' },
        accept: () => form.post('/pinjaman'),
    });
}
</script>

<template>
    <Head title="Pinjaman baru" />

    <PageHeader
        title="Pinjaman baru"
        :subtitle="`Khusus guru/karyawan. Jasa flat ${persen}% per bulan dari pokok, dicairkan tunai dari kas.`"
    />

    <div class="grid gap-5 xl:grid-cols-[minmax(0,26rem)_1fr]">
        <form class="card flex flex-col gap-5 self-start" @submit.prevent="submit">
            <div class="flex items-center gap-2 text-sm text-muted-color">
                <i class="pi pi-calendar" /> Tanggal akad:
                <DateText class="font-semibold text-color" :value="tanggal" hari />
            </div>

            <div class="flex flex-col gap-2">
                <label for="nasabah" class="font-medium">Nasabah (guru/karyawan)</label>
                <AutoComplete
                    v-model="nasabahTerpilih"
                    input-id="nasabah"
                    :suggestions="suggestions"
                    :option-label="labelNasabah"
                    placeholder="Ketik nama, nomor nasabah, atau NIP"
                    :invalid="!!form.errors.nasabah_id"
                    fluid
                    force-selection
                    @complete="search"
                    @option-select="(e) => (form.nasabah_id = e.value.id)"
                    @clear="form.nasabah_id = null"
                >
                    <template #option="{ option }">
                        <div>
                            <div>{{ option.nama }}</div>
                            <div class="text-xs text-muted-color">
                                {{ option.nomor }} · {{ option.jenis }}
                                <span v-if="option.pinjaman_berjalan" class="text-red-500">
                                    · pinjaman berjalan {{ option.pinjaman_berjalan }}</span
                                >
                            </div>
                        </div>
                    </template>
                </AutoComplete>
                <Message v-if="berjalan" severity="warn" :closable="false">
                    Nasabah ini masih punya pinjaman berjalan ({{ berjalan }}). Lunasi dulu sebelum meminjam lagi.
                </Message>
                <FieldError :message="form.errors.nasabah_id" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="pokok" class="font-medium">Pokok pinjaman</label>
                <MoneyInput
                    v-model="form.pokok"
                    input-id="pokok"
                    :max="adaPlafon ? Number(plafon) : undefined"
                    :invalid="!!form.errors.pokok"
                    fluid
                />
                <small class="text-muted-color">
                    <template v-if="adaPlafon">Plafon <MoneyText :value="plafon" /></template>
                    <template v-else>Tanpa plafon</template>
                    <span v-if="teksTerbilang">
                        · <em class="capitalize">{{ teksTerbilang }}</em></span
                    >
                </small>
                <FieldError :message="form.errors.pokok" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="tenor" class="font-medium">Jangka waktu</label>
                <InputNumber
                    v-model="form.tenor"
                    input-id="tenor"
                    :min="1"
                    :max="tenorMaksimal"
                    suffix=" bulan"
                    show-buttons
                    :invalid="!!form.errors.tenor"
                    fluid
                />
                <small class="text-muted-color">Maksimal {{ tenorMaksimal }} bulan.</small>
                <FieldError :message="form.errors.tenor" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="keterangan" class="font-medium">
                    Keterangan <span class="text-muted-color">(opsional)</span>
                </label>
                <InputText
                    id="keterangan"
                    v-model="form.keterangan"
                    placeholder="mis. Keperluan biaya sekolah anak"
                    :invalid="!!form.errors.keterangan"
                />
                <FieldError :message="form.errors.keterangan" />
            </div>

            <div class="flex gap-2">
                <Button
                    type="submit"
                    label="Simpan & cairkan"
                    icon="pi pi-check"
                    severity="warn"
                    :loading="form.processing"
                    :disabled="!form.nasabah_id || !form.pokok || !form.tenor || !!berjalan"
                />
                <Link href="/pinjaman"><Button label="Batal" severity="secondary" text /></Link>
            </div>
        </form>

        <div class="card min-w-0">
            <h2 class="mb-3 text-lg font-semibold">Jadwal angsuran</h2>
            <p v-if="!jadwal.length" class="text-muted-color">
                Isi pokok pinjaman dan jangka waktu untuk melihat rincian angsuran tiap bulan.
            </p>
            <DataTable v-else :value="jadwal" data-key="ke" striped-rows size="small">
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
                <ColumnGroup type="footer">
                    <Row>
                        <Column footer="Total" :colspan="2" />
                        <Column footer-class="text-right">
                            <template #footer><MoneyText :value="total.pokok" /></template>
                        </Column>
                        <Column footer-class="text-right">
                            <template #footer><MoneyText :value="total.jasa" /></template>
                        </Column>
                        <Column footer-class="text-right">
                            <template #footer><MoneyText class="font-semibold" :value="total.total" /></template>
                        </Column>
                    </Row>
                </ColumnGroup>
            </DataTable>
        </div>
    </div>
</template>
