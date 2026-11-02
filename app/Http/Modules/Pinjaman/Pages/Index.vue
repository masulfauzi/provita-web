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
import StatusPinjaman from '../Components/StatusPinjaman.vue';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    tanggal: { type: String, required: true },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const filter = reactive({ status: props.filters.status ?? null });

function reload(params = {}) {
    router.get(
        '/pinjaman',
        {
            search: search.value || undefined,
            status: filter.status || undefined,
            per_page: props.items.per_page,
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true, only: ['items', 'filters'] },
    );
}

watchDebounced(search, () => reload({ page: 1 }), { debounce: 400 });
watch(filter, () => reload({ page: 1 }));

const terlambat = (data) =>
    data.status === 'berjalan' && data.jatuh_tempo_berikutnya && data.jatuh_tempo_berikutnya < props.tanggal;
</script>

<template>
    <Head title="Pinjaman" />

    <PageHeader title="Pinjaman" subtitle="Pinjaman tunai guru/karyawan dengan jasa flat per bulan.">
        <Link v-if="can('pinjaman.akad')" href="/pinjaman/create">
            <Button label="Pinjaman baru" icon="pi pi-plus" />
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
            @page="(e) => reload({ page: e.page + 1, per_page: e.rows })"
            @row-click="(e) => router.visit(`/pinjaman/${e.data.id}`)"
        >
            <template #header>
                <div class="flex flex-wrap gap-2">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Cari nomor atau nama nasabah..." />
                    </IconField>
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
            <template #empty>Belum ada pinjaman.</template>

            <Column header="Nomor">
                <template #body="{ data }">
                    <div class="font-medium whitespace-nowrap">{{ data.nomor }}</div>
                    <div class="text-xs text-muted-color"><DateText :value="data.tanggal" /></div>
                </template>
            </Column>
            <Column header="Nasabah">
                <template #body="{ data }">
                    <div>{{ data.nasabah?.nama }}</div>
                    <div class="text-xs text-muted-color">{{ data.nasabah?.nomor }}</div>
                </template>
            </Column>
            <Column header="Pokok" class="text-right">
                <template #body="{ data }"><MoneyText :value="data.pokok" /></template>
            </Column>
            <Column header="Tenor">
                <template #body="{ data }">{{ data.tenor }} bulan</template>
            </Column>
            <Column header="Sisa pokok" class="text-right">
                <template #body="{ data }">
                    <MoneyText :value="data.status === 'dibatalkan' ? 0 : (data.sisa_pokok ?? 0)" />
                    <div v-if="data.status === 'berjalan'" class="text-xs text-muted-color">
                        {{ data.sisa_angsuran }} angsuran lagi
                    </div>
                </template>
            </Column>
            <Column header="Jatuh tempo berikutnya">
                <template #body="{ data }">
                    <template v-if="data.status === 'berjalan' && data.jatuh_tempo_berikutnya">
                        <DateText :value="data.jatuh_tempo_berikutnya" />
                        <div v-if="terlambat(data)"><Tag value="Terlambat" severity="danger" /></div>
                    </template>
                    <span v-else class="text-muted-color">-</span>
                </template>
            </Column>
            <Column header="Status">
                <template #body="{ data }"><StatusPinjaman :status="data.status" /></template>
            </Column>
        </DataTable>
    </div>
</template>
