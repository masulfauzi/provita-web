<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MoneyText from '@/Components/MoneyText.vue';
import { useSearch } from '@/composables/useSearch';
import { terbilang } from '@/utils/format';

/**
 * Dialog tambah/ubah autodebet. Tanpa `autodebet` = tambah (pilih rekening tujuan).
 */
const visible = defineModel('visible', { type: Boolean, default: false });

const props = defineProps({
    opsi: { type: Object, required: true },
    autodebet: { type: Object, default: null },
    rekeningAwal: { type: Object, default: null },
});

const ubah = computed(() => !!props.autodebet);

function toDate(value) {
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}
const pad = (n) => String(n).padStart(2, '0');
const toDateString = (date) =>
    date ? `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` : null;

const besok = () => {
    const d = toDate(props.opsi.tanggal_transaksi);
    d.setDate(d.getDate() + 1);
    return d;
};

const { suggestions, search: cariRekening } = useSearch('/rekening/cari?non_utama=1');
const tujuan = ref(null);
const labelRekening = (r) =>
    r && typeof r === 'object' ? `${r.nomor} · ${r.nasabah?.nama ?? ''} (${r.jenis ?? ''})` : r;

const form = useForm({
    rekening_id: null,
    nominal: null,
    periode: 'mingguan',
    hari: 1,
    mulai: null,
    target: null,
    keterangan: '',
});

function isi() {
    form.clearErrors();
    const a = props.autodebet;
    if (a) {
        Object.assign(form, {
            rekening_id: a.rekening.id,
            nominal: Number(a.nominal),
            periode: a.periode,
            hari: a.hari,
            target: a.target ? Number(a.target) : null,
            keterangan: a.keterangan ?? '',
        });
        return;
    }
    const mulai = besok();
    tujuan.value = props.rekeningAwal
        ? {
              ...props.rekeningAwal,
              jenis: props.rekeningAwal.jenis_rekening?.nama ?? props.rekeningAwal.jenisRekening?.nama,
          }
        : null;
    Object.assign(form, {
        rekening_id: props.rekeningAwal?.id ?? null,
        nominal: null,
        periode: 'mingguan',
        hari: ((mulai.getDay() + 6) % 7) + 1,
        mulai,
        target: null,
        keterangan: '',
    });
}

watch(visible, (buka) => buka && isi(), { immediate: true });

// Pindah periode: hari disesuaikan ke rentang yang valid.
watch(
    () => form.periode,
    (periode) => {
        if (periode === 'mingguan' && form.hari > 7) form.hari = 1;
    },
);

const tanggalOptions = Array.from({ length: 28 }, (_, i) => ({ value: i + 1, label: `Tanggal ${i + 1}` }));
const kaliMenuju = computed(() =>
    form.target && form.nominal ? Math.ceil(Number(form.target) / Number(form.nominal)) : null,
);

function simpan() {
    const opsi = { preserveScroll: true, onSuccess: () => (visible.value = false) };
    if (ubah.value) {
        form.transform(({ nominal, periode, hari, target, keterangan }) => ({
            nominal,
            periode,
            hari,
            target,
            keterangan,
        })).put(`/autodebet/${props.autodebet.id}`, opsi);
    } else {
        form.transform((data) => ({ ...data, mulai: toDateString(data.mulai) })).post('/autodebet', opsi);
    }
}
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="ubah ? 'Ubah autodebet' : 'Tambah autodebet'"
        :style="{ width: '34rem' }"
        :breakpoints="{ '640px': '95vw' }"
    >
        <form class="flex flex-col gap-4" @submit.prevent="simpan">
            <div class="flex flex-col gap-2">
                <label for="tujuan" class="font-medium">Rekening tujuan</label>
                <template v-if="ubah">
                    <div class="text-sm">
                        {{ autodebet.rekening.nomor }} · {{ autodebet.nasabah?.nama }} ({{ autodebet.rekening.jenis }})
                    </div>
                </template>
                <template v-else>
                    <AutoComplete
                        v-model="tujuan"
                        input-id="tujuan"
                        :suggestions="suggestions"
                        :option-label="labelRekening"
                        placeholder="Ketik nomor rekening atau nama nasabah"
                        force-selection
                        fluid
                        :invalid="!!form.errors.rekening_id"
                        @complete="cariRekening"
                        @option-select="(e) => (form.rekening_id = e.value.id)"
                        @clear="form.rekening_id = null"
                    />
                    <small class="text-muted-color">
                        Hanya rekening selain Tabungan Pelajar. Dana diambil dari Tabungan Pelajar nasabah yang sama.
                    </small>
                </template>
                <FieldError :message="form.errors.rekening_id || form.errors.sumber_rekening_id" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="nominal" class="font-medium">Nominal per autodebet</label>
                <MoneyInput v-model="form.nominal" input-id="nominal" :invalid="!!form.errors.nominal" fluid />
                <small v-if="form.nominal" class="text-muted-color capitalize italic">{{
                    terbilang(form.nominal)
                }}</small>
                <FieldError :message="form.errors.nominal" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <span class="font-medium">Periode</span>
                    <SelectButton
                        v-model="form.periode"
                        :options="opsi.periode"
                        option-label="label"
                        option-value="value"
                        :allow-empty="false"
                    />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="hari" class="font-medium">{{
                        form.periode === 'mingguan' ? 'Setiap hari' : 'Setiap tanggal'
                    }}</label>
                    <Select
                        v-model="form.hari"
                        input-id="hari"
                        :options="form.periode === 'mingguan' ? opsi.hari : tanggalOptions"
                        option-label="label"
                        option-value="value"
                        :invalid="!!form.errors.hari"
                    />
                    <FieldError :message="form.errors.hari" />
                </div>
            </div>

            <div v-if="!ubah" class="flex flex-col gap-2">
                <label for="mulai" class="font-medium">Mulai</label>
                <DatePicker
                    v-model="form.mulai"
                    input-id="mulai"
                    date-format="dd/mm/yy"
                    show-icon
                    :min-date="toDate(opsi.tanggal_transaksi)"
                    :invalid="!!form.errors.mulai"
                />
                <small class="text-muted-color"
                    >Diproses saat sesi kas dibuka pada atau setelah tanggal jatuh tempo.</small
                >
                <FieldError :message="form.errors.mulai" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="target" class="font-medium">Target <span class="text-muted-color">(opsional)</span></label>
                <MoneyInput v-model="form.target" input-id="target" :invalid="!!form.errors.target" fluid />
                <small v-if="kaliMenuju" class="text-muted-color">
                    Tercapai sekitar {{ kaliMenuju }}× autodebet. Setelah target tercapai, autodebet berhenti otomatis.
                </small>
                <small v-if="ubah && autodebet.terkumpul > 0" class="text-muted-color">
                    Sudah terkumpul <MoneyText :value="autodebet.terkumpul" />.
                </small>
                <FieldError :message="form.errors.target" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="keterangan" class="font-medium"
                    >Keterangan <span class="text-muted-color">(opsional)</span></label
                >
                <InputText id="keterangan" v-model="form.keterangan" placeholder="mis. Tabungan study tour Bali" />
                <FieldError :message="form.errors.keterangan" />
            </div>

            <div class="flex justify-end gap-2">
                <Button type="button" label="Batal" severity="secondary" text @click="visible = false" />
                <Button
                    type="submit"
                    :label="ubah ? 'Simpan perubahan' : 'Buat autodebet'"
                    icon="pi pi-check"
                    :loading="form.processing"
                    :disabled="!form.rekening_id || !form.nominal"
                />
            </div>
        </form>
    </Dialog>
</template>
