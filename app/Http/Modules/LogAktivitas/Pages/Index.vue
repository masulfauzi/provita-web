<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import DateText from '@/Components/DateText.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    logNames: { type: Array, default: () => [] },
});

const toDate = (value) => (value ? new Date(`${value}T00:00:00`) : null);
const toDateString = (date) =>
    date
        ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
        : undefined;

const filter = reactive({
    user_id: props.filters.user_id ?? null,
    log_name: props.filters.log_name ?? null,
    dari: toDate(props.filters.dari),
    sampai: toDate(props.filters.sampai),
});

const expanded = ref({});

function reload(params = {}) {
    router.get(
        '/log-aktivitas',
        {
            user_id: filter.user_id || undefined,
            log_name: filter.log_name || undefined,
            dari: toDateString(filter.dari),
            sampai: toDateString(filter.sampai),
            per_page: props.items.per_page,
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(filter, () => reload({ page: 1 }));

function onPage(event) {
    reload({ page: event.page + 1, per_page: event.rows });
}

function reset() {
    Object.assign(filter, { user_id: null, log_name: null, dari: null, sampai: null });
}

const severity = { created: 'success', updated: 'info', deleted: 'danger', login: 'secondary', logout: 'secondary' };

function hasDetail(row) {
    return (row.changes && Object.keys(row.changes).length) || (row.properties && Object.keys(row.properties).length);
}
</script>

<template>
    <Head title="Log aktivitas" />

    <PageHeader title="Log aktivitas" subtitle="Siapa melakukan apa, kapan, beserta nilai lama dan baru" />

    <div class="card">
        <DataTable
            v-model:expanded-rows="expanded"
            :value="items.data"
            lazy
            paginator
            :rows="items.per_page"
            :total-records="items.total"
            :first="(items.current_page - 1) * items.per_page"
            :rows-per-page-options="[25, 50, 100]"
            data-key="id"
            @page="onPage"
        >
            <template #header>
                <div class="flex flex-wrap gap-2">
                    <Select
                        v-model="filter.user_id"
                        :options="users"
                        option-label="name"
                        option-value="id"
                        placeholder="Semua pengguna"
                        show-clear
                        filter
                    />
                    <Select v-model="filter.log_name" :options="logNames" placeholder="Semua kategori" show-clear />
                    <DatePicker v-model="filter.dari" placeholder="Dari tanggal" date-format="dd/mm/yy" show-icon />
                    <DatePicker v-model="filter.sampai" placeholder="Sampai tanggal" date-format="dd/mm/yy" show-icon />
                    <Button label="Reset" icon="pi pi-filter-slash" severity="secondary" text @click="reset" />
                </div>
            </template>
            <template #empty>Belum ada aktivitas.</template>

            <Column expander class="w-12" />
            <Column header="Waktu">
                <template #body="{ data }">
                    <DateText :value="data.created_at" jam />
                </template>
            </Column>
            <Column header="Pengguna">
                <template #body="{ data }">
                    <span v-if="data.causer">{{ data.causer.name }}</span>
                    <span v-else class="text-muted-color">Sistem</span>
                </template>
            </Column>
            <Column field="log_name" header="Kategori" />
            <Column header="Aktivitas">
                <template #body="{ data }">
                    <Tag
                        v-if="data.event"
                        :value="data.event"
                        :severity="severity[data.event] ?? 'secondary'"
                        class="mr-2"
                    />
                    {{ data.description }}
                    <span v-if="data.subject_type" class="text-muted-color">({{ data.subject_type }})</span>
                </template>
            </Column>

            <template #expansion="{ data }">
                <div v-if="hasDetail(data)" class="grid gap-4 md:grid-cols-2">
                    <div v-if="data.changes?.old">
                        <div class="mb-1 font-medium">Nilai lama</div>
                        <pre class="overflow-x-auto rounded bg-surface-100 p-3 text-xs dark:bg-surface-800">{{
                            JSON.stringify(data.changes.old, null, 2)
                        }}</pre>
                    </div>
                    <div v-if="data.changes?.attributes">
                        <div class="mb-1 font-medium">Nilai baru</div>
                        <pre class="overflow-x-auto rounded bg-surface-100 p-3 text-xs dark:bg-surface-800">{{
                            JSON.stringify(data.changes.attributes, null, 2)
                        }}</pre>
                    </div>
                    <div v-if="data.properties && Object.keys(data.properties).length" class="md:col-span-2">
                        <div class="mb-1 font-medium">Keterangan</div>
                        <pre class="overflow-x-auto rounded bg-surface-100 p-3 text-xs dark:bg-surface-800">{{
                            JSON.stringify(data.properties, null, 2)
                        }}</pre>
                    </div>
                </div>
                <div v-else class="text-muted-color">Tidak ada detail.</div>
            </template>
        </DataTable>
    </div>
</template>
