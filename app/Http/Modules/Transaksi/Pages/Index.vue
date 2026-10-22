<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useConfirm } from 'primevue/useconfirm';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';
import { useSearch } from '@/composables/useSearch';
import { rupiah, terbilang } from '@/utils/format';

const props = defineProps({
    rekening: { type: Object, default: null },
    jenis: { type: String, default: 'setor' },
});

const { can } = usePermission();
const confirm = useConfirm();
const { suggestions, search } = useSearch('/rekening/cari');

const terpilih = ref(props.rekening?.status === 'aktif' ? props.rekening : null);
const jenisOptions = computed(() =>
    [
        { value: 'setor', label: 'Setoran', disabled: !can('transaksi.setor') },
        { value: 'tarik', label: 'Penarikan', disabled: !can('transaksi.tarik') },
    ].filter((o) => !o.disabled),
);
const jenis = ref(props.jenis);

const form = useForm({
    rekening_id: terpilih.value?.id ?? null,
    nominal: null,
    keterangan: '',
});

const label = (r) => (r && typeof r === 'object' ? `${r.nomor} · ${r.nasabah?.nama ?? ''}` : r);
const teksTerbilang = computed(() => (form.nominal ? terbilang(form.nominal) : ''));

function pilih(event) {
    form.rekening_id = event.value.id;
    form.clearErrors();
}

function kosongkan() {
    form.rekening_id = null;
}

function submit() {
    const judul = jenis.value === 'setor' ? 'Setoran' : 'Penarikan';
    confirm.require({
        header: `Konfirmasi ${judul.toLowerCase()}`,
        message: `${judul} ${rupiah(form.nominal)} untuk rekening ${terpilih.value.nomor} a.n. ${terpilih.value.nasabah?.nama}?`,
        icon: 'pi pi-question-circle',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: `Ya, ${judul.toLowerCase()}`, severity: jenis.value === 'setor' ? 'success' : 'warn' },
        accept: () => form.post(`/transaksi/${jenis.value}`, { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Setor / Tarik" />

    <PageHeader title="Setor / Tarik" subtitle="Setoran dan penarikan tabungan nasabah" />

    <form class="card flex max-w-2xl flex-col gap-5" @submit.prevent="submit">
        <div class="flex flex-col gap-2">
            <label class="font-medium">Jenis transaksi</label>
            <div class="flex gap-2">
                <Button
                    v-for="option in jenisOptions"
                    :key="option.value"
                    type="button"
                    :label="option.label"
                    :icon="option.value === 'setor' ? 'pi pi-arrow-down' : 'pi pi-arrow-up'"
                    :outlined="jenis !== option.value"
                    :severity="option.value === 'setor' ? 'success' : 'warn'"
                    @click="jenis = option.value"
                />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label for="rekening" class="font-medium">Rekening</label>
            <AutoComplete
                v-model="terpilih"
                input-id="rekening"
                :suggestions="suggestions"
                :option-label="label"
                placeholder="Ketik nomor rekening, nama nasabah, atau NIS"
                :invalid="!!form.errors.rekening_id"
                fluid
                force-selection
                @complete="search"
                @option-select="pilih"
                @clear="kosongkan"
            >
                <template #option="{ option }">
                    <div class="flex w-full justify-between gap-4">
                        <div>
                            <div>{{ option.nasabah?.nama }}</div>
                            <div class="text-xs text-muted-color">
                                {{ option.nomor
                                }}<span v-if="option.nasabah?.kelas"> · {{ option.nasabah.kelas }}</span>
                            </div>
                        </div>
                        <MoneyText :value="option.saldo" class="text-sm" />
                    </div>
                </template>
            </AutoComplete>
            <FieldError :message="form.errors.rekening_id" />
        </div>

        <div
            v-if="terpilih && typeof terpilih === 'object'"
            class="flex items-center justify-between rounded-lg bg-surface-100 p-4 dark:bg-surface-800"
        >
            <div>
                <div class="font-semibold">{{ terpilih.nasabah?.nama }}</div>
                <div class="text-sm text-muted-color">{{ terpilih.nomor }}</div>
            </div>
            <div class="text-right">
                <div class="text-sm text-muted-color">Saldo</div>
                <div class="text-lg font-semibold"><MoneyText :value="terpilih.saldo" /></div>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label for="nominal" class="font-medium">Nominal</label>
            <MoneyInput v-model="form.nominal" input-id="nominal" :invalid="!!form.errors.nominal" fluid />
            <small v-if="teksTerbilang" class="capitalize text-muted-color"
                ><em>{{ teksTerbilang }}</em></small
            >
            <FieldError :message="form.errors.nominal" />
        </div>

        <div class="flex flex-col gap-2">
            <label for="keterangan" class="font-medium">Keterangan (opsional)</label>
            <InputText id="keterangan" v-model="form.keterangan" :invalid="!!form.errors.keterangan" />
            <FieldError :message="form.errors.keterangan" />
        </div>

        <div class="flex gap-2">
            <Button
                type="submit"
                :label="jenis === 'setor' ? 'Simpan setoran' : 'Simpan penarikan'"
                :icon="jenis === 'setor' ? 'pi pi-arrow-down' : 'pi pi-arrow-up'"
                :severity="jenis === 'setor' ? 'success' : 'warn'"
                :loading="form.processing"
                :disabled="!form.rekening_id || !form.nominal"
            />
            <Link v-if="form.rekening_id" :href="`/rekening/${form.rekening_id}`">
                <Button label="Buku tabungan" icon="pi pi-book" severity="secondary" text />
            </Link>
        </div>
    </form>
</template>
