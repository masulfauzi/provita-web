<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import FieldError from '@/Components/FieldError.vue';

/**
 * Input NISN + tombol Periksa ke API data siswa sekolah (lewat /nasabah/periksa-nisn).
 * Memancarkan "hasil" dengan { status, data?, nasabah?, message?, nisn }.
 */
const nisn = defineModel({ type: String, default: '' });

const props = defineProps({
    error: { type: String, default: null },
    hasil: { type: Object, default: null },
    // Periksa NISN yang sudah terisi begitu komponen tampil.
    otomatis: { type: Boolean, default: false },
});

const emit = defineEmits(['hasil']);

const memeriksa = ref(false);
const pesanLokal = ref(null);
const valid = computed(() => /^\d{10}$/.test(nisn.value ?? ''));

async function periksa() {
    pesanLokal.value = null;

    if (!valid.value) {
        pesanLokal.value = 'NISN harus 10 digit angka.';
        return;
    }

    memeriksa.value = true;

    try {
        const response = await fetch(`/nasabah/periksa-nisn?nisn=${encodeURIComponent(nisn.value)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const body = await response.json().catch(() => ({}));

        if (response.status === 422) {
            pesanLokal.value = body.errors?.nisn?.[0] ?? 'NISN tidak valid.';
            return;
        }

        if (response.status === 429) {
            emit('hasil', { status: 'gagal', message: 'Terlalu sering memeriksa. Tunggu sebentar.', nisn: nisn.value });
            return;
        }

        if (response.ok) {
            emit('hasil', { ...body, nisn: nisn.value });
            return;
        }

        emit('hasil', {
            status: 'gagal',
            message: body.message ?? 'API data siswa tidak dapat dihubungi.',
            nisn: nisn.value,
        });
    } catch {
        emit('hasil', { status: 'gagal', message: 'API data siswa tidak dapat dihubungi.', nisn: nisn.value });
    } finally {
        memeriksa.value = false;
    }
}

onMounted(() => props.otomatis && valid.value && periksa());
</script>

<template>
    <div class="flex flex-col gap-2">
        <label for="nisn" class="font-medium">NISN</label>
        <div class="flex gap-2">
            <InputText
                id="nisn"
                v-model="nisn"
                inputmode="numeric"
                maxlength="10"
                placeholder="10 digit"
                class="min-w-0 flex-1"
                :invalid="!!(error || pesanLokal)"
                @keydown.enter.prevent="periksa"
            />
            <Button
                type="button"
                label="Periksa"
                icon="pi pi-search"
                :loading="memeriksa"
                :disabled="!nisn"
                @click="periksa"
            />
        </div>
        <FieldError :message="pesanLokal || error" />

        <template v-if="hasil && hasil.nisn === nisn">
            <Message v-if="hasil.status === 'ditemukan'" severity="success" size="small">
                Data siswa ditemukan dan sudah diisikan. Lengkapi data lain yang masih kosong.
            </Message>
            <Message v-else-if="hasil.status === 'tidak_ditemukan'" severity="warn" size="small">
                NISN tidak ditemukan di data siswa sekolah. Isi data secara manual.
            </Message>
            <Message v-else-if="hasil.status === 'terdaftar'" severity="error" size="small">
                NISN ini sudah terdaftar sebagai nasabah
                <Link :href="`/nasabah/${hasil.nasabah.id}`" class="font-semibold underline">
                    {{ hasil.nasabah.nama }} ({{ hasil.nasabah.nomor }}) </Link
                >.
            </Message>
            <Message v-else severity="warn" size="small">{{ hasil.message }} Isi data secara manual.</Message>
        </template>
    </div>
</template>
