<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ConfirmDelete from '@/Components/ConfirmDelete.vue';
import DateText from '@/Components/DateText.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    item: { type: Object, required: true },
    rekening: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    jenisKelaminOptions: { type: Array, default: () => [] },
});

const { can } = usePermission();
const confirm = useConfirm();

const jenisRekening = { tabungan: 'Tabungan' };

const label = (options, value) => options.find((o) => o.value === value)?.label ?? value ?? '-';
const isSiswa = computed(() => props.item.jenis === 'siswa');

const rincian = computed(() => [
    ['Nomor nasabah', props.item.nomor],
    [isSiswa.value ? 'NIS' : 'NIP / No. pegawai', props.item.nis],
    ['Jenis nasabah', label(props.jenisOptions, props.item.jenis)],
    ['Jenis kelamin', label(props.jenisKelaminOptions, props.item.jenis_kelamin)],
    ...(isSiswa.value ? [['Kelas', props.item.kelas]] : []),
    ['Tempat lahir', props.item.tempat_lahir],
    ['Nomor HP', props.item.no_hp],
    ...(isSiswa.value ? [['Orang tua / wali', props.item.nama_orang_tua]] : []),
    ['Alamat', props.item.alamat],
    ['Keterangan', props.item.keterangan],
]);

function toggleActive() {
    confirm.require({
        header: props.item.is_active ? 'Nonaktifkan nasabah' : 'Aktifkan nasabah',
        message: props.item.is_active
            ? `Nasabah ${props.item.nama} tidak akan bisa bertransaksi sampai diaktifkan kembali. Lanjutkan?`
            : `Aktifkan kembali ${props.item.nama}?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya', severity: props.item.is_active ? 'danger' : 'primary' },
        accept: () => router.patch(`/nasabah/${props.item.id}/aktif`, {}, { preserveScroll: true }),
    });
}
</script>

<template>
    <Head :title="item.nama" />

    <PageHeader :title="item.nama" :subtitle="item.nomor">
        <Tag :value="item.is_active ? 'Aktif' : 'Nonaktif'" :severity="item.is_active ? 'success' : 'secondary'" />
        <Link href="/nasabah">
            <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" text />
        </Link>
        <Link v-if="can('nasabah.ubah')" :href="`/nasabah/${item.id}/edit`">
            <Button label="Ubah" icon="pi pi-pencil" />
        </Link>
        <Button
            v-if="can('nasabah.hapus')"
            :label="item.is_active ? 'Nonaktifkan' : 'Aktifkan'"
            :icon="item.is_active ? 'pi pi-ban' : 'pi pi-check-circle'"
            :severity="item.is_active ? 'danger' : 'success'"
            outlined
            @click="toggleActive"
        />
        <ConfirmDelete
            v-if="can('nasabah.hapus')"
            :url="`/nasabah/${item.id}`"
            label="Hapus"
            message="Data nasabah akan dihapus permanen. Untuk nasabah yang sudah bertransaksi, gunakan Nonaktifkan."
        />
    </PageHeader>

    <dl class="card grid max-w-3xl grid-cols-[11rem_1fr] gap-x-4 gap-y-3">
        <template v-for="[judul, nilai] in rincian" :key="judul">
            <dt class="text-muted-color">{{ judul }}</dt>
            <dd class="whitespace-pre-line">{{ nilai || '-' }}</dd>
        </template>
        <dt class="text-muted-color">Tanggal lahir</dt>
        <dd><DateText :value="item.tanggal_lahir" /></dd>
        <dt class="text-muted-color">Terdaftar</dt>
        <dd><DateText :value="item.created_at" /></dd>
    </dl>

    <div v-if="can('rekening.lihat')" class="card mt-5 max-w-3xl">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Rekening ({{ rekening.length }})</h2>
            <Link v-if="item.is_active && can('rekening.buka')" :href="`/rekening/create?nasabah=${item.id}`">
                <Button label="Buka rekening" icon="pi pi-plus" size="small" />
            </Link>
        </div>
        <DataTable
            :value="rekening"
            data-key="id"
            size="small"
            row-hover
            class="cursor-pointer"
            @row-click="(e) => router.visit(`/rekening/${e.data.id}`)"
        >
            <template #empty>Nasabah ini belum memiliki rekening.</template>
            <Column field="nomor" header="Nomor rekening" />
            <Column header="Jenis">
                <template #body="{ data }">{{ jenisRekening[data.jenis] ?? data.jenis }}</template>
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
