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
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    kelas: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const filter = reactive({
    jenis: props.filters.jenis ?? null,
    kelas: props.filters.kelas ?? null,
    status: props.filters.status ?? null,
});

const statusOptions = [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
];

const jenisLabel = (value) => props.jenisOptions.find((o) => o.value === value)?.label ?? value;

function reload(params = {}) {
    router.get(
        '/nasabah',
        {
            search: search.value || undefined,
            jenis: filter.jenis || undefined,
            kelas: filter.kelas || undefined,
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

function lihat(event) {
    router.visit(`/nasabah/${event.data.id}`);
}
</script>

<template>
    <Head title="Nasabah" />

    <PageHeader title="Nasabah" subtitle="Siswa, guru, dan karyawan yang terdaftar di Bank Provita">
        <Link v-if="can('nasabah.tambah')" href="/nasabah/create">
            <Button label="Tambah nasabah" icon="pi pi-plus" />
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
            @row-click="lihat"
        >
            <template #header>
                <div class="flex flex-wrap gap-2">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Cari nama, nomor, NIS..." />
                    </IconField>
                    <Select
                        v-model="filter.jenis"
                        :options="jenisOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Semua jenis"
                        show-clear
                    />
                    <Select v-model="filter.kelas" :options="kelas" placeholder="Semua kelas" show-clear filter />
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
            <template #empty>Belum ada nasabah.</template>

            <Column field="nomor" header="Nomor" class="whitespace-nowrap" />
            <Column field="nis" header="NIS/NIP" />
            <Column field="nama" header="Nama" />
            <Column field="kelas" header="Kelas">
                <template #body="{ data }">{{ data.kelas || '-' }}</template>
            </Column>
            <Column header="Jenis">
                <template #body="{ data }">{{ jenisLabel(data.jenis) }}</template>
            </Column>
            <Column header="Status">
                <template #body="{ data }">
                    <Tag
                        :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
            <Column header="" class="w-px">
                <template #body="{ data }">
                    <Link v-if="can('nasabah.ubah')" :href="`/nasabah/${data.id}/edit`" @click.stop>
                        <Button icon="pi pi-pencil" text rounded aria-label="Ubah" />
                    </Link>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
