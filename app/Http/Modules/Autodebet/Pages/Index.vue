<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DateText from '@/Components/DateText.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';
import AutodebetForm from '../Components/AutodebetForm.vue';
import ProgresTarget from '../Components/ProgresTarget.vue';
import StatusAutodebet from '../Components/StatusAutodebet.vue';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    opsi: { type: Object, required: true },
    rekeningAwal: { type: Object, default: null },
});

const { can } = usePermission();
const formTampil = ref(!!props.rekeningAwal && can('autodebet.kelola'));

const search = ref(props.filters.search ?? '');
const filter = reactive({ status: props.filters.status ?? null });

function reload(params = {}) {
    router.get(
        '/autodebet',
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
</script>

<template>
    <Head title="Autodebet" />

    <PageHeader
        title="Autodebet"
        subtitle="Pindah buku terjadwal dari Tabungan Pelajar ke rekening tabungan lain milik nasabah. Diproses saat sesi kas dibuka."
    >
        <Button v-if="can('autodebet.kelola')" label="Tambah autodebet" icon="pi pi-plus" @click="formTampil = true" />
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
            @row-click="(e) => router.visit(`/autodebet/${e.data.id}`)"
        >
            <template #header>
                <div class="flex flex-wrap gap-2">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Cari nama nasabah / nomor rekening..." />
                    </IconField>
                    <Select
                        v-model="filter.status"
                        :options="opsi.status"
                        option-label="label"
                        option-value="value"
                        placeholder="Semua status"
                        show-clear
                    />
                </div>
            </template>
            <template #empty>Belum ada autodebet.</template>

            <Column header="Nasabah">
                <template #body="{ data }">
                    <div class="font-medium">{{ data.nasabah?.nama }}</div>
                    <div class="text-xs text-muted-color">{{ data.nasabah?.kelas ?? data.nasabah?.nomor }}</div>
                </template>
            </Column>
            <Column header="Rekening">
                <template #body="{ data }">
                    <div>
                        {{ data.rekening.nomor }}
                        <span class="text-xs text-muted-color">({{ data.rekening.jenis }})</span>
                    </div>
                    <div class="text-xs text-muted-color">dari {{ data.sumber?.nomor }}</div>
                </template>
            </Column>
            <Column header="Autodebet">
                <template #body="{ data }">
                    <MoneyText :value="data.nominal" />
                    <div class="text-xs text-muted-color">{{ data.jadwal }}</div>
                </template>
            </Column>
            <Column header="Jatuh tempo">
                <template #body="{ data }">
                    <DateText v-if="data.status === 'aktif'" :value="data.jatuh_tempo" />
                    <span v-else class="text-muted-color">-</span>
                </template>
            </Column>
            <Column header="Target" style="min-width: 12rem">
                <template #body="{ data }"
                    ><ProgresTarget :saldo="data.rekening?.saldo" :target="data.target" :terkumpul="data.terkumpul"
                /></template>
            </Column>
            <Column header="Status">
                <template #body="{ data }"><StatusAutodebet :status="data.status" /></template>
            </Column>
        </DataTable>
    </div>

    <AutodebetForm v-model:visible="formTampil" :opsi="opsi" :rekening-awal="rekeningAwal" />
</template>
