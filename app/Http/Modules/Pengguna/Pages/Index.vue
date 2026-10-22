<script setup>
import { ref, watch } from 'vue';
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
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
});

const { can } = usePermission();
const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? null);

function reload(params = {}) {
    router.get(
        '/pengguna',
        { search: search.value || undefined, role: role.value || undefined, per_page: props.items.per_page, ...params },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, () => reload({ page: 1 }), { debounce: 400 });
watch(role, () => reload({ page: 1 }));

function onPage(event) {
    reload({ page: event.page + 1, per_page: event.rows });
}
</script>

<template>
    <Head title="Pengguna" />

    <PageHeader title="Pengguna" subtitle="Akun petugas Bank Provita beserta perannya">
        <Link v-if="can('pengguna.tambah')" href="/pengguna/create">
            <Button label="Tambah pengguna" icon="pi pi-plus" />
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
            @page="onPage"
        >
            <template #header>
                <div class="flex flex-wrap gap-2">
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Cari nama / username..." />
                    </IconField>
                    <Select
                        v-model="role"
                        :options="roles"
                        option-label="label"
                        option-value="value"
                        placeholder="Semua peran"
                        show-clear
                    />
                </div>
            </template>
            <template #empty>Belum ada pengguna.</template>

            <Column field="username" header="Username" />
            <Column field="name" header="Nama" />
            <Column header="Peran">
                <template #body="{ data }">
                    <Tag v-for="r in data.roles" :key="r" :value="r" class="mr-1 capitalize" />
                </template>
            </Column>
            <Column header="Status">
                <template #body="{ data }">
                    <Tag
                        :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                        :severity="data.is_active ? 'success' : 'secondary'"
                    />
                </template>
            </Column>
            <Column header="Login terakhir">
                <template #body="{ data }">
                    <DateText :value="data.last_login_at" jam />
                </template>
            </Column>
            <Column header="" class="w-px">
                <template #body="{ data }">
                    <Link v-if="can('pengguna.ubah')" :href="`/pengguna/${data.id}/edit`">
                        <Button icon="pi pi-pencil" text rounded aria-label="Ubah" />
                    </Link>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
