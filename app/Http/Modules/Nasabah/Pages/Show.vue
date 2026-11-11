<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
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
    waliOptions: { type: Array, default: () => [] },
    akunIb: { type: Object, default: null },
    // Password sementara hasil reset; hanya ada sekali (flash).
    passwordIb: { type: String, default: null },
});

const { can } = usePermission();
const confirm = useConfirm();

const label = (options, value) => options.find((o) => o.value === value)?.label ?? value ?? '-';
const isSiswa = computed(() => props.item.jenis === 'siswa');
const isLembaga = computed(() => props.item.jenis === 'lembaga');

const rincian = computed(() => [
    ['Nomor nasabah', props.item.nomor],
    ...(isLembaga.value ? [] : [[isSiswa.value ? 'NIS' : 'NIP / No. pegawai', props.item.nis]]),
    ...(isSiswa.value ? [['NISN', props.item.nisn]] : []),
    ...(isLembaga.value ? [] : [['NIK', props.item.nik]]),
    ['Jenis nasabah', label(props.jenisOptions, props.item.jenis)],
    ...(isLembaga.value ? [] : [['Jenis kelamin', label(props.jenisKelaminOptions, props.item.jenis_kelamin)]]),
    ...(isSiswa.value ? [['Kelas', props.item.kelas]] : []),
    ...(isLembaga.value ? [] : [['Tempat lahir', props.item.tempat_lahir]]),
    ['Nomor HP', props.item.no_hp],
    ['Email', props.item.email],
    ...(isSiswa.value
        ? [
              [
                  'Orang tua / wali',
                  [
                      props.item.nama_orang_tua,
                      props.item.wali_hubungan ? `(${label(props.waliOptions, props.item.wali_hubungan)})` : null,
                      props.item.wali_hp,
                  ]
                      .filter(Boolean)
                      .join(' '),
              ],
          ]
        : []),
    ['Alamat', props.item.alamat],
    ['Keterangan', props.item.keterangan],
]);

function resetPasswordIb() {
    confirm.require({
        header: props.akunIb ? 'Reset password Internet Banking' : 'Buat akun Internet Banking',
        message: props.akunIb
            ? `Password ${props.item.nama} diganti password sementara, semua sesi Internet Banking-nya dikeluarkan, dan nasabah wajib mengganti password saat login. Lanjutkan?`
            : `Akun Internet Banking untuk ${props.item.nama} dibuat dengan password sementara. Lanjutkan?`,
        icon: 'pi pi-key',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya', severity: 'warn' },
        accept: () => router.post(`/nasabah/${props.item.id}/reset-password-ib`, {}, { preserveScroll: true }),
    });
}

const tampilPassword = ref(!!props.passwordIb);
watch(
    () => props.passwordIb,
    (baru) => (tampilPassword.value = !!baru),
);

const cetakSlip = () => window.print();

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
        <template v-if="!isLembaga">
            <dt class="text-muted-color">Tanggal lahir</dt>
            <dd><DateText :value="item.tanggal_lahir" /></dd>
        </template>
        <dt class="text-muted-color">Terdaftar</dt>
        <dd><DateText :value="item.created_at" /></dd>
    </dl>

    <div class="card mt-5 max-w-3xl print:hidden">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Internet Banking</h2>
                <p v-if="!akunIb" class="text-sm text-muted-color">Nasabah ini belum punya akun Internet Banking.</p>
                <div v-else class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                    <Tag
                        :value="akunIb.is_active ? 'Akun aktif' : 'Akun nonaktif'"
                        :severity="akunIb.is_active ? 'success' : 'secondary'"
                    />
                    <Tag v-if="akunIb.wajib_ganti_password" value="Wajib ganti password" severity="warn" />
                    <Tag v-else-if="!akunIb.profil_lengkap" value="Data pribadi belum lengkap" severity="warn" />
                    <span class="text-muted-color">
                        Login terakhir:
                        <DateText v-if="akunIb.login_terakhir" :value="akunIb.login_terakhir" jam />
                        <span v-else>belum pernah</span>
                    </span>
                </div>
            </div>
            <Button
                v-if="can('nasabah.reset_password_ib') && item.is_active"
                :label="akunIb ? 'Reset password IB' : 'Buat akun IB'"
                icon="pi pi-key"
                severity="warn"
                outlined
                @click="resetPasswordIb"
            />
        </div>
    </div>

    <Dialog v-model:visible="tampilPassword" modal header="Password sementara Internet Banking" class="w-full max-w-md">
        <div class="slip-ib flex flex-col gap-3">
            <p class="text-sm text-muted-color">
                Berikan ke nasabah sekarang. Password ini <strong>tidak bisa ditampilkan lagi</strong>; nasabah wajib
                menggantinya saat login pertama.
            </p>
            <dl class="grid grid-cols-[9rem_1fr] gap-x-3 gap-y-2">
                <dt class="text-muted-color">Nama</dt>
                <dd class="font-medium">{{ item.nama }}</dd>
                <dt class="text-muted-color">Login (NIS / No. rek.)</dt>
                <dd class="font-medium">{{ item.nis || item.nomor }}</dd>
                <dt class="text-muted-color">Password</dt>
                <dd class="font-mono text-2xl font-semibold tracking-widest">{{ passwordIb }}</dd>
            </dl>
            <div class="mt-2 flex justify-end gap-2 print:hidden">
                <Button label="Cetak slip" icon="pi pi-print" severity="secondary" outlined @click="cetakSlip" />
                <Button label="Tutup" @click="tampilPassword = false" />
            </div>
        </div>
    </Dialog>

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
