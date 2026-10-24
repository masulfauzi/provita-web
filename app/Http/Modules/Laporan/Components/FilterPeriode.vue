<script setup>
import { ref, watch } from 'vue';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
    periode: { type: Object, required: true },
    // Laporan per tanggal (neraca) hanya memakai tanggal "sampai".
    perTanggal: { type: Boolean, default: false },
});

const emit = defineEmits(['ubah']);

function toDate(value) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function toString(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const dari = ref(toDate(props.periode.dari));
const sampai = ref(toDate(props.periode.sampai));

watch(
    () => props.periode,
    (periode) => {
        dari.value = toDate(periode.dari);
        sampai.value = toDate(periode.sampai);
    },
);

function ubah() {
    if (!dari.value || !sampai.value) return;
    emit('ubah', { dari: toString(dari.value), sampai: toString(sampai.value) });
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <div v-if="!perTanggal" class="flex flex-col gap-1">
            <label for="dari" class="text-sm font-medium">Dari tanggal</label>
            <DatePicker v-model="dari" input-id="dari" date-format="dd/mm/yy" show-icon @date-select="ubah" />
        </div>
        <div class="flex flex-col gap-1">
            <label for="sampai" class="text-sm font-medium">{{ perTanggal ? 'Per tanggal' : 'Sampai tanggal' }}</label>
            <DatePicker v-model="sampai" input-id="sampai" date-format="dd/mm/yy" show-icon @date-select="ubah" />
        </div>
    </div>
</template>
