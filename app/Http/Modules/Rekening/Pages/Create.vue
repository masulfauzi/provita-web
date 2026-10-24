<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Message from 'primevue/message';
import Select from 'primevue/select';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useSearch } from '@/composables/useSearch';
import { terbilang } from '@/utils/format';

const props = defineProps({
    nasabah: { type: Object, default: null },
    jenisOptions: { type: Array, default: () => [] },
    setoranAwalMinimal: { type: String, default: '0.00' },
});

const { suggestions, search } = useSearch('/nasabah/cari');
const nasabahTerpilih = ref(props.nasabah?.is_active ? props.nasabah : null);

const form = useForm({
    nasabah_id: nasabahTerpilih.value?.id ?? null,
    jenis_rekening_id: props.jenisOptions[0]?.value ?? null,
    setoran_awal: Number(props.setoranAwalMinimal) > 0 ? Number(props.setoranAwalMinimal) : null,
});

const labelNasabah = (n) => (n && typeof n === 'object' ? `${n.nama} (${n.nomor})` : n);
const teksTerbilang = computed(() => (form.setoran_awal ? terbilang(form.setoran_awal) : ''));

function pilih(event) {
    form.nasabah_id = event.value.id;
}

function submit() {
    form.post('/rekening');
}
</script>

<template>
    <Head title="Buka rekening" />

    <PageHeader title="Buka rekening" subtitle="Nomor rekening dibuat otomatis. Setoran awal langsung tercatat." />

    <Message v-if="nasabah && !nasabah.is_active" severity="warn" class="mb-4 max-w-2xl">
        Nasabah {{ nasabah.nama }} tidak aktif sehingga tidak dapat dibukakan rekening.
    </Message>

    <form class="card flex max-w-2xl flex-col gap-5" @submit.prevent="submit">
        <div class="flex flex-col gap-2">
            <label for="nasabah" class="font-medium">Nasabah</label>
            <AutoComplete
                v-model="nasabahTerpilih"
                input-id="nasabah"
                :suggestions="suggestions"
                :option-label="labelNasabah"
                placeholder="Ketik nama, nomor nasabah, atau NIS"
                :invalid="!!form.errors.nasabah_id"
                fluid
                force-selection
                @complete="search"
                @option-select="pilih"
                @clear="form.nasabah_id = null"
            >
                <template #option="{ option }">
                    <div>
                        <div>{{ option.nama }}</div>
                        <div class="text-xs text-muted-color">
                            {{ option.nomor }}<span v-if="option.nis"> · NIS {{ option.nis }}</span>
                            <span v-if="option.kelas"> · {{ option.kelas }}</span>
                        </div>
                    </div>
                </template>
            </AutoComplete>
            <FieldError :message="form.errors.nasabah_id" />
        </div>

        <div class="flex flex-col gap-2">
            <label for="jenis_rekening_id" class="font-medium">Jenis rekening</label>
            <Select
                v-model="form.jenis_rekening_id"
                input-id="jenis_rekening_id"
                :options="jenisOptions"
                option-label="label"
                option-value="value"
                placeholder="Pilih jenis rekening"
                :invalid="!!form.errors.jenis_rekening_id"
            />
            <FieldError :message="form.errors.jenis_rekening_id" />
        </div>

        <div class="flex flex-col gap-2">
            <label for="setoran_awal" class="font-medium">Setoran awal</label>
            <MoneyInput
                v-model="form.setoran_awal"
                input-id="setoran_awal"
                :invalid="!!form.errors.setoran_awal"
                fluid
            />
            <small class="text-muted-color">
                Minimal <MoneyText :value="setoranAwalMinimal" />
                <span v-if="teksTerbilang">
                    · <em class="capitalize">{{ teksTerbilang }}</em></span
                >
            </small>
            <FieldError :message="form.errors.setoran_awal" />
        </div>

        <div class="flex gap-2">
            <Button
                type="submit"
                label="Buka rekening"
                icon="pi pi-check"
                :loading="form.processing"
                :disabled="!form.nasabah_id"
            />
            <Link :href="nasabah ? `/nasabah/${nasabah.id}` : '/rekening'">
                <Button label="Batal" severity="secondary" text />
            </Link>
        </div>
    </form>
</template>
