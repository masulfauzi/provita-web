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
    jenisOptions: { type: Array, default: () => [] },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const filter = reactive({
    jenis_rekening_id: props.filters.jenis_rekening_id ?? null,
    status: props.filters.status ?? null,
});

const statusOptions = [
    { value: 'aktif', label: 'Aktif' },
    { value: 'tutup', label: 'Ditutup' },
];

function reload(params = {}) {
    router.get(
        '/rekening',
        {
            search: search.value || undefined,
            jenis_rekening_id: filter.jenis_rekening_id || undefined,
            status: filter.status || undefined,
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
    <Head title="Rekening" />

    <PageHeader title="Rekening" subtitle="Semua rekening nasabah Bank Provita">
        <Link v-if="can('rekening.buka')" href="/rekening/create">
            <Button label="Buka rekening" icon="pi pi-plus" />
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
            :rows-per-page-options="[15, 30, 50, 100]"
            data-key="id"
            striped-rows
            row-hover
            class="cursor-pointer"
            @page="onPage"
            @row-click="(e) => router.visit(`/rekening/${e.data.id}`)"
        >
            <template #header>
                <div class="flex flex-wrap gap-2">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Cari nomor rekening, nama, NIS, NISN..." />
                    </IconField>
                    <Select
                        v-model="filter.jenis_rekening_id"
                        :options="jenisOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Semua jenis"
                        show-clear
                    />
                    <Select
                        v-model="filter.status"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Semua status"
                        show-clear
                    />
                </div>
            </template>
            <template #empty>Belum ada rekening.</template>

            <Column field="nomor" header="Nomor rekening" class="whitespace-nowrap" />
            <Column header="Nasabah">
                <template #body="{ data }">
                    <div>{{ data.nasabah?.nama }}</div>
                    <div class="text-xs text-muted-color">
                        {{ data.nasabah?.nomor }}<span v-if="data.nasabah?.kelas"> · {{ data.nasabah.kelas }}</span>
                    </div>
                </template>
            </Column>
            <Column header="Jenis">
                <template #body="{ data }">{{ data.jenis_rekening?.nama }}</template>
            </Column>
            <Column header="Saldo" class="text-right">
                <template #body="{ data }"><MoneyText :value="data.saldo" /></template>
            </Column>
            <Column header="Dibuka">
                <template #body="{ data }"><DateText :value="data.tanggal_buka" /></template>
            </Column>
            <Column header="Status">
                <template #body="{ data }">
                    <Tag
                        :value="data.status === 'aktif' ? 'Aktif' : 'Ditutup'"
                        :severity="data.status === 'aktif' ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
