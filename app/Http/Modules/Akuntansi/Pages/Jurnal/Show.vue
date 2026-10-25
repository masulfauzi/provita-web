<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import DateText from '@/Components/DateText.vue';
import FieldError from '@/Components/FieldError.vue';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    jurnal: { type: Object, required: true },
    kas: { type: Object, default: null },
});

const { can } = usePermission();
const dialog = ref(false);
const form = useForm({ alasan: '' });

const totalDebit = computed(() => props.jurnal.detail.reduce((sum, d) => sum + Number(d.debit), 0));
const totalKredit = computed(() => props.jurnal.detail.reduce((sum, d) => sum + Number(d.kredit), 0));
const bisaDikoreksi = computed(
    () =>
        can('jurnal.balik') &&
        props.jurnal.sumber === 'manual' &&
        !props.jurnal.pembalik &&
        !props.jurnal.pembalik_dari_id,
);

function koreksi() {
    form.post(`/jurnal/${props.jurnal.id}/balik`, { onSuccess: () => (dialog.value = false) });
}
</script>

<template>
    <Head :title="`Jurnal ${jurnal.nomor}`" />

    <PageHeader :title="jurnal.nomor" :subtitle="jurnal.keterangan">
        <Link href="/jurnal"><Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" text /></Link>
        <Button
            v-if="bisaDikoreksi"
            label="Koreksi"
            icon="pi pi-undo"
            severity="warn"
            outlined
            @click="dialog = true"
        />
    </PageHeader>

    <Message v-if="jurnal.pembalik" severity="warn" class="mb-4 max-w-4xl">
        Jurnal ini sudah dikoreksi dengan
        <Link :href="`/jurnal/${jurnal.pembalik.id}`" class="font-semibold underline">{{ jurnal.pembalik.nomor }}</Link
        >.
    </Message>
    <Message v-if="jurnal.pembalik_dari" severity="info" class="mb-4 max-w-4xl">
        Jurnal pembalik untuk
        <Link :href="`/jurnal/${jurnal.pembalik_dari.id}`" class="font-semibold underline">{{
            jurnal.pembalik_dari.nomor
        }}</Link
        >.
    </Message>

    <div class="card max-w-4xl">
        <dl class="mb-5 grid gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-sm text-muted-color">Tanggal</dt>
                <dd class="font-medium"><DateText :value="jurnal.tanggal" hari /></dd>
            </div>
            <div>
                <dt class="text-sm text-muted-color">Sumber</dt>
                <dd>
                    <Tag v-if="jurnal.sumber === 'manual'" value="Jurnal umum" severity="info" />
                    <Link v-else-if="kas" :href="`/kas/${kas.id}`" class="text-primary hover:underline"
                        >Bukti kas {{ kas.nomor }} <i class="pi pi-external-link text-xs"
                    /></Link>
                    <Link
                        v-else-if="jurnal.transaksi && can('rekening.lihat')"
                        :href="`/rekening/${jurnal.transaksi.rekening_id}`"
                        class="text-primary hover:underline"
                        >Transaksi tabungan <i class="pi pi-external-link text-xs"
                    /></Link>
                    <span v-else>Transaksi tabungan</span>
                </dd>
            </div>
            <div>
                <dt class="text-sm text-muted-color">Dicatat oleh</dt>
                <dd class="font-medium">{{ jurnal.user?.name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-color">Waktu catat</dt>
                <dd class="font-medium"><DateText :value="jurnal.created_at" jam /></dd>
            </div>
        </dl>

        <table class="w-full">
            <thead>
                <tr class="border-b-2 border-surface-300 text-left dark:border-surface-700">
                    <th class="py-2 pr-3">Akun</th>
                    <th class="py-2 pr-3">Keterangan</th>
                    <th class="py-2 pr-3 text-right">Debit</th>
                    <th class="py-2 text-right">Kredit</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="d in jurnal.detail" :key="d.id" class="border-b border-surface-100 dark:border-surface-800">
                    <td class="py-2 pr-3" :class="{ 'pl-8': Number(d.kredit) > 0 }">
                        <span class="text-muted-color tabular-nums">{{ d.akun.kode }}</span> {{ d.akun.nama }}
                    </td>
                    <td class="py-2 pr-3 text-muted-color">{{ d.keterangan }}</td>
                    <td class="py-2 pr-3 text-right"><MoneyText v-if="Number(d.debit)" :value="d.debit" /></td>
                    <td class="py-2 text-right"><MoneyText v-if="Number(d.kredit)" :value="d.kredit" /></td>
                </tr>
                <tr class="font-semibold">
                    <td colspan="2" class="py-2">Total</td>
                    <td class="py-2 pr-3 text-right"><MoneyText :value="totalDebit" /></td>
                    <td class="py-2 text-right"><MoneyText :value="totalKredit" /></td>
                </tr>
            </tbody>
        </table>
    </div>

    <Dialog v-model:visible="dialog" modal header="Koreksi jurnal" class="w-full max-w-md">
        <p class="mb-4">
            Jurnal pembalik (debit & kredit ditukar) akan dicatat pada tanggal transaksi aktif. Jurnal asli tetap
            tersimpan.
        </p>
        <div class="flex flex-col gap-2">
            <label for="alasan" class="font-medium">Alasan koreksi</label>
            <InputText id="alasan" v-model="form.alasan" autofocus :invalid="!!form.errors.alasan" />
            <FieldError :message="form.errors.alasan" />
        </div>
        <template #footer>
            <Button label="Batal" severity="secondary" text @click="dialog = false" />
            <Button label="Buat jurnal pembalik" severity="warn" :loading="form.processing" @click="koreksi" />
        </template>
    </Dialog>
</template>
