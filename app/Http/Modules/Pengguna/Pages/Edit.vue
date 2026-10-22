<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Password from 'primevue/password';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import FieldError from '@/Components/FieldError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    item: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
});

const { can } = usePermission();
const confirm = useConfirm();

const form = useForm({
    name: props.item.name,
    email: props.item.email ?? '',
    roles: props.item.roles,
});

function submit() {
    form.put(`/pengguna/${props.item.id}`);
}

const resetDialog = ref(false);
const resetForm = useForm({ password: '', password_confirmation: '' });

function submitReset() {
    resetForm.put(`/pengguna/${props.item.id}/password`, {
        preserveScroll: true,
        onSuccess: () => {
            resetDialog.value = false;
            resetForm.reset();
        },
    });
}

function toggleActive() {
    confirm.require({
        header: props.item.is_active ? 'Nonaktifkan pengguna' : 'Aktifkan pengguna',
        message: props.item.is_active
            ? `${props.item.username} tidak akan bisa login sampai diaktifkan kembali. Lanjutkan?`
            : `Aktifkan kembali ${props.item.username}?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Batal', severity: 'secondary', text: true },
        acceptProps: { label: 'Ya', severity: props.item.is_active ? 'danger' : 'primary' },
        accept: () => router.patch(`/pengguna/${props.item.id}/aktif`, {}, { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Ubah pengguna" />

    <PageHeader :title="item.name" :subtitle="`@${item.username}`">
        <Tag :value="item.is_active ? 'Aktif' : 'Nonaktif'" :severity="item.is_active ? 'success' : 'secondary'" />
        <Button
            v-if="can('pengguna.reset_password')"
            label="Reset kata sandi"
            icon="pi pi-key"
            severity="secondary"
            outlined
            @click="resetDialog = true"
        />
        <Button
            v-if="!item.is_self"
            :label="item.is_active ? 'Nonaktifkan' : 'Aktifkan'"
            :icon="item.is_active ? 'pi pi-ban' : 'pi pi-check-circle'"
            :severity="item.is_active ? 'danger' : 'success'"
            outlined
            @click="toggleActive"
        />
    </PageHeader>

    <form class="card flex max-w-2xl flex-col gap-5" @submit.prevent="submit">
        <div class="grid gap-5 md:grid-cols-2">
            <div class="flex flex-col gap-2">
                <label for="name" class="font-medium">Nama lengkap</label>
                <InputText id="name" v-model="form.name" :invalid="!!form.errors.name" />
                <FieldError :message="form.errors.name" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="email" class="font-medium">Email (opsional)</label>
                <InputText id="email" v-model="form.email" type="email" :invalid="!!form.errors.email" />
                <FieldError :message="form.errors.email" />
            </div>
            <div class="flex flex-col gap-2 md:col-span-2">
                <label for="roles" class="font-medium">Peran</label>
                <MultiSelect
                    v-model="form.roles"
                    input-id="roles"
                    :options="roles"
                    option-label="label"
                    option-value="value"
                    display="chip"
                    :invalid="!!form.errors.roles"
                />
                <FieldError :message="form.errors.roles" />
            </div>
        </div>

        <div class="flex gap-2">
            <Button type="submit" label="Simpan perubahan" icon="pi pi-check" :loading="form.processing" />
            <Link href="/pengguna">
                <Button label="Batal" severity="secondary" text />
            </Link>
        </div>
    </form>

    <Dialog v-model:visible="resetDialog" modal header="Reset kata sandi" class="w-full max-w-md">
        <form class="flex flex-col gap-4" @submit.prevent="submitReset">
            <p class="text-muted-color">Semua sesi login {{ item.username }} akan diakhiri.</p>
            <div class="flex flex-col gap-2">
                <label for="new_password" class="font-medium">Kata sandi baru</label>
                <Password
                    v-model="resetForm.password"
                    input-id="new_password"
                    toggle-mask
                    fluid
                    autocomplete="new-password"
                    :invalid="!!resetForm.errors.password"
                />
                <FieldError :message="resetForm.errors.password" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="new_password_confirmation" class="font-medium">Ulangi kata sandi baru</label>
                <Password
                    v-model="resetForm.password_confirmation"
                    input-id="new_password_confirmation"
                    :feedback="false"
                    toggle-mask
                    fluid
                    autocomplete="new-password"
                />
            </div>
            <div class="flex justify-end gap-2">
                <Button label="Batal" severity="secondary" text @click="resetDialog = false" />
                <Button type="submit" label="Reset" icon="pi pi-key" :loading="resetForm.processing" />
            </div>
        </form>
    </Dialog>
</template>
