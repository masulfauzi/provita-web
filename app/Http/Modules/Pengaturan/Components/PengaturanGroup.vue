<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';

const props = defineProps({
    group: { type: Object, required: true },
    title: { type: String, required: true },
    description: { type: String, default: null },
});

const page = usePage();

function toDate(value) {
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function toDateString(date) {
    if (!date) return null;
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function initialValue(item) {
    switch (item.tipe) {
        case 'tanggal':
            // Tanggal transaksi yang belum diisi memakai tanggal aktif (hari ini) seperti di topbar.
            return toDate(
                item.nilai ?? (item.kunci === 'tanggal_transaksi' ? page.props.app?.tanggal_transaksi : null),
            );
        case 'uang':
        case 'persen':
        case 'angka':
            return item.nilai === null || item.nilai === '' ? null : Number(item.nilai);
        default:
            return item.nilai ?? '';
    }
}

const form = useForm({
    nilai: Object.fromEntries(props.group.items.map((item) => [item.kunci, initialValue(item)])),
});

function submit() {
    form.transform((data) => ({
        nilai: Object.fromEntries(
            props.group.items.map((item) => [
                item.kunci,
                item.tipe === 'tanggal' ? toDateString(data.nilai[item.kunci]) : data.nilai[item.kunci],
            ]),
        ),
    })).put('/pengaturan', { preserveScroll: true });
}
</script>

<template>
    <form class="card" @submit.prevent="submit">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <p v-if="description" class="text-muted-color">{{ description }}</p>
            </div>
            <Tag v-if="!group.editable" value="Hanya lihat" severity="secondary" icon="pi pi-lock" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div v-for="item in group.items" :key="item.kunci" class="flex flex-col gap-2">
                <label :for="item.kunci" class="font-medium">{{ item.label }}</label>

                <MoneyInput
                    v-if="item.tipe === 'uang'"
                    v-model="form.nilai[item.kunci]"
                    :input-id="item.kunci"
                    :disabled="!group.editable"
                    :invalid="!!form.errors[`nilai.${item.kunci}`]"
                    fluid
                />
                <DatePicker
                    v-else-if="item.tipe === 'tanggal'"
                    v-model="form.nilai[item.kunci]"
                    :input-id="item.kunci"
                    date-format="dd MM yy"
                    show-icon
                    show-button-bar
                    :disabled="!group.editable"
                    :invalid="!!form.errors[`nilai.${item.kunci}`]"
                    fluid
                />
                <InputNumber
                    v-else-if="item.tipe === 'persen'"
                    v-model="form.nilai[item.kunci]"
                    :input-id="item.kunci"
                    suffix=" %"
                    locale="id-ID"
                    :min-fraction-digits="0"
                    :max-fraction-digits="2"
                    :min="0"
                    :max="100"
                    :disabled="!group.editable"
                    :invalid="!!form.errors[`nilai.${item.kunci}`]"
                    fluid
                />
                <InputNumber
                    v-else-if="item.tipe === 'angka'"
                    v-model="form.nilai[item.kunci]"
                    :input-id="item.kunci"
                    locale="id-ID"
                    :disabled="!group.editable"
                    :invalid="!!form.errors[`nilai.${item.kunci}`]"
                    fluid
                />
                <InputText
                    v-else
                    :id="item.kunci"
                    v-model="form.nilai[item.kunci]"
                    :disabled="!group.editable"
                    :invalid="!!form.errors[`nilai.${item.kunci}`]"
                />

                <small v-if="item.keterangan" class="text-muted-color">{{ item.keterangan }}</small>
                <FieldError :message="form.errors[`nilai.${item.kunci}`]" />
            </div>
        </div>

        <div v-if="group.editable" class="mt-5">
            <Button type="submit" label="Simpan" icon="pi pi-check" :loading="form.processing" />
        </div>
    </form>
</template>
