<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import DateText from '@/Components/DateText.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';
import AutodebetForm from '../Components/AutodebetForm.vue';
import ProgresTarget from '../Components/ProgresTarget.vue';
import StatusAutodebet from '../Components/StatusAutodebet.vue';

const props = defineProps({
    autodebet: { type: Object, required: true },
    riwayat: { type: Array, default: () => [] },
    opsi: { type: Object, required: true },
});

const { can } = usePermission();
const confirm = useConfirm();
const formTampil = ref(false);

function hentikan() {
    confirm.require({
        header: 'Hentikan autodebet',
        message:
            'Autodebet tidak akan diproses sampai dilanjutkan kembali. Jadwal yang terlewat selama berhenti tidak ditagih. Lanjutkan?',
        icon: 'pi pi-pause',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya, hentikan', severity: 'warn' },
        accept: () => router.post(`/autodebet/${props.autodebet.id}/hentikan`, {}, { preserveScroll: true }),
    });
}

const lanjutkan = () => router.post(`/autodebet/${props.autodebet.id}/lanjutkan`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Autodebet" />

    <PageHeader
        :title="`Autodebet ${autodebet.rekening.nomor}`"
        :subtitle="`${autodebet.nasabah?.nama ?? ''} · ${autodebet.rekening.jenis}`"
    >
        <StatusAutodebet :status="autodebet.status" />
        <Link href="/autodebet"><Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" text /></Link>
        <template v-if="can('autodebet.kelola')">
            <Button
                v-if="autodebet.status !== 'selesai'"
                label="Ubah"
                icon="pi pi-pencil"
                severity="secondary"
                outlined
                @click="formTampil = true"
            />
            <Button
                v-if="autodebet.status === 'aktif'"
                label="Hentikan"
                icon="pi pi-pause"
                severity="warn"
                outlined
                @click="hentikan"
            />
            <Button
                v-if="autodebet.status === 'berhenti'"
                label="Lanjutkan"
                icon="pi pi-play"
                severity="success"
                @click="lanjutkan"
            />
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card flex flex-col gap-3">
            <div class="text-sm text-muted-color">Autodebet</div>
            <div class="text-2xl font-semibold"><MoneyText :value="autodebet.nominal" /></div>
            <div>
                {{ autodebet.jadwal }} <span class="text-muted-color">({{ autodebet.periode }})</span>
            </div>
            <div class="text-sm">
                <span class="text-muted-color">Jatuh tempo berikutnya:&nbsp;</span>
                <DateText v-if="autodebet.status === 'aktif'" :value="autodebet.jatuh_tempo" hari />
                <span v-else>-</span>
            </div>
            <div class="text-sm text-muted-color">
                Mulai <DateText :value="autodebet.mulai" />, dibuat oleh {{ autodebet.pembuat ?? '-' }}.
            </div>
            <p v-if="autodebet.keterangan" class="text-sm">{{ autodebet.keterangan }}</p>
        </div>

        <div class="card flex flex-col gap-3">
            <div class="text-sm text-muted-color">Target</div>
            <ProgresTarget
                :saldo="autodebet.rekening?.saldo"
                :target="autodebet.target"
                :terkumpul="autodebet.terkumpul"
            />
            <div v-if="autodebet.status === 'selesai'" class="text-sm text-muted-color">
                Target tercapai; autodebet berhenti otomatis.
            </div>
        </div>

        <div class="card flex flex-col gap-3 text-sm">
            <div class="text-muted-color">Rekening</div>
            <div>
                <span class="text-muted-color">Dari&nbsp;</span>
                <Link :href="`/rekening/${autodebet.sumber?.id}`" class="font-medium text-primary hover:underline">{{
                    autodebet.sumber?.nomor
                }}</Link>
                (Tabungan Pelajar) · saldo <MoneyText :value="autodebet.sumber?.saldo" />
            </div>
            <div>
                <span class="text-muted-color">Ke&nbsp;</span>
                <Link :href="`/rekening/${autodebet.rekening.id}`" class="font-medium text-primary hover:underline">{{
                    autodebet.rekening.nomor
                }}</Link>
                ({{ autodebet.rekening.jenis }}) · saldo <MoneyText :value="autodebet.rekening.saldo" />
            </div>
        </div>
    </div>

    <div class="card mt-5">
        <h2 class="mb-3 font-semibold">Riwayat proses</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-surface-200 text-left dark:border-surface-700">
                        <th class="py-2 pr-3">Jadwal</th>
                        <th class="py-2 pr-3">Diproses</th>
                        <th class="py-2 pr-3 text-right">Nominal</th>
                        <th class="py-2">Hasil</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in riwayat"
                        :key="item.id"
                        class="border-b border-surface-100 dark:border-surface-800"
                    >
                        <td class="py-2 pr-3"><DateText :value="item.jadwal" /></td>
                        <td class="py-2 pr-3"><DateText :value="item.tanggal" /></td>
                        <td class="py-2 pr-3 text-right"><MoneyText :value="item.nominal" /></td>
                        <td class="py-2">
                            <Tag v-if="item.status === 'berhasil'" value="Berhasil" severity="success" />
                            <template v-else>
                                <Tag value="Gagal" severity="danger" />
                                <span class="ml-2 text-muted-color">{{ item.alasan }}</span>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!riwayat.length">
                        <td colspan="4" class="py-4 text-center text-muted-color italic">Belum pernah diproses.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <AutodebetForm v-model:visible="formTampil" :opsi="opsi" :autodebet="autodebet" />
</template>
