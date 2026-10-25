<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useSearch } from '@/composables/useSearch';
import { rupiah, terbilang } from '@/utils/format';

const props = defineProps({
    jenis: { type: String, required: true },
    judul: { type: String, required: true },
    items: { type: Object, required: true },
    total: { type: String, default: '0.00' },
    filters: { type: Object, default: () => ({}) },
    akunOptions: { type: Array, default: () => [] },
    hargaBukuTabungan: { type: String, default: '0.00' },
});

const page = usePage();
const confirm = useConfirm();
const masuk = computed(() => props.jenis === 'masuk');
const url = computed(() => (masuk.value ? '/kas/penerimaan' : '/kas/pengeluaran'));

const { suggestions, search: cariNasabah } = useSearch('/nasabah/cari');
const nasabahTerpilih = ref(null);

const form = useForm({ akun_id: null, nominal: null, keterangan: '', nasabah_id: null });
const teksTerbilang = computed(() => (form.nominal ? terbilang(form.nominal) : ''));
const labelNasabah = (n) => (n && typeof n === 'object' ? `${n.nama} (${n.nomor})` : n);

// Memilih kategori penjualan buku tabungan mengisi nominal dari pengaturan harga buku.
watch(
    () => form.akun_id,
    (akunId) => {
        const akun = props.akunOptions.find((o) => o.value === akunId);
        if (akun?.peran !== 'pendapatan_buku_tabungan') return;
        if (!form.nominal && Number(props.hargaBukuTabungan) > 0) form.nominal = Number(props.hargaBukuTabungan);
        if (!form.keterangan) form.keterangan = 'Penjualan buku tabungan';
    },
);

function submit() {
    const kategori = props.akunOptions.find((o) => o.value === form.akun_id)?.label ?? '';

    confirm.require({
        header: `Simpan ${props.judul.toLowerCase()}`,
        message: `${kategori}\n${rupiah(form.nominal)} — ${form.keterangan}`,
        icon: masuk.value ? 'pi pi-arrow-circle-down' : 'pi pi-arrow-circle-up',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya, simpan', severity: masuk.value ? 'success' : 'warn' },
        accept: () =>
            form.post(url.value, {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    nasabahTerpilih.value = null;
                },
            }),
    });
}

const search = ref(props.filters.search ?? '');
const filter = reactive({ akun_id: props.filters.akun_id ?? null });

function reload(params = {}) {
    router.get(
        url.value,
        {
            search: search.value || undefined,
            akun_id: filter.akun_id || undefined,
            dari: props.filters.dari,
            sampai: props.filters.sampai,
            per_page: props.items.per_page,
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true, only: ['items', 'total', 'filters'] },
    );
}

watchDebounced(search, () => reload({ page: 1 }), { debounce: 400 });
watch(filter, () => reload({ page: 1 }));
</script>

<template>
    <Head :title="judul" />

    <PageHeader
        :title="judul"
        :subtitle="
            masuk
                ? 'Uang masuk di luar tabungan, mis. penjualan buku tabungan.'
                : 'Uang keluar untuk kebutuhan bank, mis. belanja ATK.'
        "
    />

    <div class="grid gap-5 xl:grid-cols-[minmax(0,26rem)_1fr]">
        <form class="card flex flex-col gap-5 self-start" @submit.prevent="submit">
            <div class="flex items-center gap-2 text-sm text-muted-color">
                <i class="pi pi-calendar" /> Tanggal:
                <DateText class="font-semibold text-color" :value="page.props.app?.tanggal_transaksi" hari />
            </div>

            <div class="flex flex-col gap-2">
                <label for="akun_id" class="font-medium">Kategori</label>
                <Select
                    v-model="form.akun_id"
                    input-id="akun_id"
                    :options="akunOptions"
                    option-label="label"
                    option-value="value"
                    :placeholder="masuk ? 'Pilih jenis penerimaan' : 'Pilih jenis pengeluaran'"
                    filter
                    :invalid="!!form.errors.akun_id"
                />
                <small class="text-muted-color">
                    Daftar diambil dari akun {{ masuk ? 'Pendapatan' : 'Beban' }} di Bagan akun.
                </small>
                <FieldError :message="form.errors.akun_id" />
            </div>

            <div v-if="masuk" class="flex flex-col gap-2">
                <label for="nasabah" class="font-medium">
                    Diterima dari nasabah <span class="text-muted-color">(opsional)</span>
                </label>
                <AutoComplete
                    v-model="nasabahTerpilih"
                    input-id="nasabah"
                    :suggestions="suggestions"
                    :option-label="labelNasabah"
                    placeholder="Ketik nama, nomor, NIS, atau NISN"
                    force-selection
                    fluid
                    :invalid="!!form.errors.nasabah_id"
                    @complete="cariNasabah"
                    @option-select="(e) => (form.nasabah_id = e.value.id)"
                    @clear="form.nasabah_id = null"
                />
                <FieldError :message="form.errors.nasabah_id" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="nominal" class="font-medium">Nominal</label>
                <MoneyInput v-model="form.nominal" input-id="nominal" :invalid="!!form.errors.nominal" fluid />
                <small v-if="teksTerbilang" class="text-muted-color capitalize italic">{{ teksTerbilang }}</small>
                <FieldError :message="form.errors.nominal" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="keterangan" class="font-medium">Keterangan</label>
                <InputText
                    id="keterangan"
                    v-model="form.keterangan"
                    :placeholder="masuk ? 'mis. Penjualan buku tabungan' : 'mis. Belanja kertas & tinta printer'"
                    :invalid="!!form.errors.keterangan"
                />
                <FieldError :message="form.errors.keterangan" />
            </div>

            <Button
                type="submit"
                :label="masuk ? 'Simpan penerimaan' : 'Simpan pengeluaran'"
                :icon="masuk ? 'pi pi-arrow-circle-down' : 'pi pi-arrow-circle-up'"
                :severity="masuk ? 'success' : 'warn'"
                :loading="form.processing"
                :disabled="!form.akun_id || !form.nominal || !form.keterangan"
            />
        </form>

        <div class="card min-w-0">
            <DataTable
                :value="items.data"
                lazy
                paginator
                :rows="items.per_page"
                :total-records="items.total"
                :first="(items.current_page - 1) * items.per_page"
                :rows-per-page-options="[15, 30, 50, 100]"
                data-key="id"
                striped-rows
                row-hover
                class="cursor-pointer"
                @page="(e) => reload({ page: e.page + 1, per_page: e.rows })"
                @row-click="(e) => router.visit(`/kas/${e.data.id}`)"
            >
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap gap-2">
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText v-model="search" placeholder="Cari nomor, keterangan, nasabah..." />
                            </IconField>
                            <Select
                                v-model="filter.akun_id"
                                :options="akunOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Semua kategori"
                                show-clear
                            />
                        </div>
                        <div class="text-sm">Total: <MoneyText class="font-semibold" :value="total" /></div>
                    </div>
                </template>
                <template #empty>Belum ada {{ judul.toLowerCase() }}.</template>

                <Column header="Tanggal">
                    <template #body="{ data }"><DateText :value="data.tanggal" /></template>
                </Column>
                <Column field="nomor" header="Nomor" class="whitespace-nowrap" />
                <Column header="Keterangan">
                    <template #body="{ data }">
                        <div :class="{ 'line-through opacity-60': data.batal_at }">{{ data.keterangan }}</div>
                        <div class="text-xs text-muted-color">
                            {{ data.akun?.nama }}<span v-if="data.nasabah"> · {{ data.nasabah.nama }}</span>
                        </div>
                    </template>
                </Column>
                <Column header="Nominal" class="text-right">
                    <template #body="{ data }">
                        <MoneyText :value="data.nominal" :class="{ 'line-through opacity-60': data.batal_at }" />
                        <div v-if="data.batal_at"><Tag value="Dibatalkan" severity="danger" /></div>
                    </template>
                </Column>
                <Column header="Petugas">
                    <template #body="{ data }">{{ data.user?.name ?? '-' }}</template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
