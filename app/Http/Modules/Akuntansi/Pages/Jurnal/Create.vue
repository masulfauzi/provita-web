<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    akunOptions: { type: Array, default: () => [] },
    tanggal: { type: String, required: true },
});

function toDate(value) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function toDateString(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const barisKosong = () => ({ akun_id: null, debit: null, kredit: null, keterangan: '' });

const form = useForm({
    tanggal: toDate(props.tanggal),
    keterangan: '',
    baris: [barisKosong(), barisKosong()],
});

// Total dalam sen agar penjumlahan desimal tidak meleset.
const sen = (value) => Math.round(Number(value || 0) * 100);
const totalDebit = computed(() => form.baris.reduce((sum, b) => sum + sen(b.debit), 0) / 100);
const totalKredit = computed(() => form.baris.reduce((sum, b) => sum + sen(b.kredit), 0) / 100);
const selisih = computed(() => Math.round((totalDebit.value - totalKredit.value) * 100) / 100);
const seimbang = computed(() => totalDebit.value > 0 && selisih.value === 0);

function tambahBaris() {
    form.baris.push(barisKosong());
}

function hapusBaris(index) {
    form.baris.splice(index, 1);
}

// Satu baris hanya boleh debit atau kredit: mengisi yang satu mengosongkan yang lain.
function isi(baris, sisi, nilai) {
    baris[sisi] = nilai;
    if (nilai) baris[sisi === 'debit' ? 'kredit' : 'debit'] = null;
}

function submit() {
    form.transform((data) => ({ ...data, tanggal: toDateString(data.tanggal) })).post('/jurnal');
}

const error = (i, field) => form.errors[`baris.${i}.${field}`];
</script>

<template>
    <Head title="Catat jurnal" />

    <PageHeader
        title="Catat jurnal umum"
        subtitle="Mis. setoran modal, pembayaran beban, atau pemindahan kas ke bank."
    />

    <form class="card flex max-w-5xl flex-col gap-5" @submit.prevent="submit">
        <div class="grid gap-5 md:grid-cols-3">
            <div class="flex flex-col gap-2">
                <label for="tanggal" class="font-medium">Tanggal</label>
                <DatePicker
                    v-model="form.tanggal"
                    input-id="tanggal"
                    date-format="dd MM yy"
                    show-icon
                    :invalid="!!form.errors.tanggal"
                />
                <FieldError :message="form.errors.tanggal" />
            </div>
            <div class="flex flex-col gap-2 md:col-span-2">
                <label for="keterangan" class="font-medium">Keterangan</label>
                <InputText
                    id="keterangan"
                    v-model="form.keterangan"
                    placeholder="mis. Setoran modal awal dari sekolah"
                    :invalid="!!form.errors.keterangan"
                />
                <FieldError :message="form.errors.keterangan" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px]">
                <thead>
                    <tr class="border-b border-surface-200 text-left text-sm dark:border-surface-700">
                        <th class="py-2 pr-2 font-medium">Akun</th>
                        <th class="w-44 py-2 pr-2 font-medium">Debit</th>
                        <th class="w-44 py-2 pr-2 font-medium">Kredit</th>
                        <th class="py-2 pr-2 font-medium">Keterangan baris</th>
                        <th class="w-10" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(baris, i) in form.baris" :key="i" class="align-top">
                        <td class="py-2 pr-2">
                            <Select
                                v-model="baris.akun_id"
                                :options="akunOptions"
                                option-group-label="label"
                                option-group-children="items"
                                option-label="label"
                                option-value="value"
                                placeholder="Pilih akun"
                                filter
                                fluid
                                :invalid="!!error(i, 'akun_id')"
                            />
                            <FieldError :message="error(i, 'akun_id')" />
                        </td>
                        <td class="py-2 pr-2">
                            <MoneyInput
                                :model-value="baris.debit"
                                fluid
                                :invalid="!!error(i, 'debit')"
                                @update:model-value="(v) => isi(baris, 'debit', v)"
                            />
                            <FieldError :message="error(i, 'debit')" />
                        </td>
                        <td class="py-2 pr-2">
                            <MoneyInput
                                :model-value="baris.kredit"
                                fluid
                                :invalid="!!error(i, 'kredit')"
                                @update:model-value="(v) => isi(baris, 'kredit', v)"
                            />
                            <FieldError :message="error(i, 'kredit')" />
                        </td>
                        <td class="py-2 pr-2">
                            <InputText v-model="baris.keterangan" fluid />
                        </td>
                        <td class="py-2">
                            <Button
                                icon="pi pi-times"
                                text
                                rounded
                                severity="secondary"
                                aria-label="Hapus baris"
                                :disabled="form.baris.length <= 2"
                                @click="hapusBaris(i)"
                            />
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-surface-200 font-semibold dark:border-surface-700">
                        <td class="py-2 pr-2">
                            <Button label="Tambah baris" icon="pi pi-plus" text size="small" @click="tambahBaris" />
                        </td>
                        <td class="py-2 pr-2"><MoneyText :value="totalDebit" /></td>
                        <td class="py-2 pr-2"><MoneyText :value="totalKredit" /></td>
                        <td colspan="2" class="py-2">
                            <span v-if="seimbang" class="text-green-600 dark:text-green-400">
                                <i class="pi pi-check-circle" /> Seimbang
                            </span>
                            <span v-else-if="totalDebit || totalKredit" class="text-red-600 dark:text-red-400">
                                Selisih <MoneyText :value="Math.abs(selisih)" />
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <Message v-if="form.errors.baris" severity="error">{{ form.errors.baris }}</Message>

        <div class="flex gap-2">
            <Button
                type="submit"
                label="Simpan jurnal"
                icon="pi pi-check"
                :loading="form.processing"
                :disabled="!seimbang"
            />
            <Link href="/jurnal"><Button label="Batal" severity="secondary" text /></Link>
        </div>
    </form>
</template>
