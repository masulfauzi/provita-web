<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import DateText from '@/Components/DateText.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const filter = reactive({ sumber: props.filters.sumber ?? null });

const sumberOptions = [
    { value: 'manual', label: 'Jurnal umum' },
    { value: 'transaksi', label: 'Transaksi tabungan & kas' },
];

const sumberLabel = { manual: 'Jurnal umum', transaksi: 'Transaksi' };
const total = (jurnal) => jurnal.detail.reduce((sum, d) => sum + Number(d.debit), 0);

function reload(params = {}) {
    router.get(
        '/jurnal',
        {
            search: search.value || undefined,
            sumber: filter.sumber || undefined,
            dari: props.filters.dari,
            sampai: props.filters.sampai,
            per_page: props.items.per_page,
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, () => reload({ page: 1 }), { debounce: 400 });
watch(filter, () => reload({ page: 1 }));

function onPage(event) {
    reload({ page: event.page + 1, per_page: event.rows });
}
</script>

<template>
    <Head title="Jurnal umum" />

    <PageHeader title="Jurnal umum" subtitle="Semua jurnal: dicatat manual atau otomatis dari transaksi tabungan">
        <Link v-if="can('jurnal.tambah')" href="/jurnal/create">
            <Button label="Catat jurnal" icon="pi pi-plus" />
        </Link>
    </PageHeader>

    <div class="card">
        <DataTable
            :value="items.data"
            lazy
            paginator
            :rows="items.per_page"
            :total-records="items.total"
            :first="(items.current_page - 1) * items.per_page"
            :rows-per-page-options="[25, 50, 100]"
            data-key="id"
            striped-rows
            row-hover
            class="cursor-pointer"
            @page="onPage"
            @row-click="(e) => router.visit(`/jurnal/${e.data.id}`)"
        >
            <template #header>
                <div class="flex flex-wrap gap-2">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Cari nomor atau keterangan..." />
                    </IconField>
                    <Select
                        v-model="filter.sumber"
                        :options="sumberOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Semua sumber"
                        show-clear
                    />
                </div>
            </template>
            <template #empty>Belum ada jurnal.</template>

            <Column header="Tanggal">
                <template #body="{ data }"><DateText :value="data.tanggal" /></template>
            </Column>
            <Column field="nomor" header="Nomor" class="whitespace-nowrap" />
            <Column header="Keterangan">
                <template #body="{ data }">
                    <div>{{ data.keterangan }}</div>
                    <div class="text-xs text-muted-color">
                        <span v-for="(d, i) in data.detail" :key="d.id"
                            >{{ i ? ' · ' : '' }}{{ Number(d.debit) ? 'D' : 'K' }} {{ d.akun?.nama }}</span
                        >
                    </div>
                </template>
            </Column>
            <Column header="Sumber">
                <template #body="{ data }">
                    <Tag
                        :value="sumberLabel[data.sumber]"
                        :severity="data.sumber === 'manual' ? 'info' : 'secondary'"
                    />
                    <Tag v-if="data.pembalik" value="Dikoreksi" severity="warn" class="ml-1" />
                </template>
            </Column>
            <Column header="Jumlah" class="text-right">
                <template #body="{ data }"><MoneyText :value="total(data)" /></template>
            </Column>
        </DataTable>
    </div>
</template>
