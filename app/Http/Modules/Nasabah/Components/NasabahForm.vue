<script setup>
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
import FieldError from '@/Components/FieldError.vue';

const props = defineProps({
    form: { type: Object, required: true },
    kelas: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    jenisKelaminOptions: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Simpan' },
});

defineEmits(['submit']);

const saranKelas = ref([]);
function cariKelas(event) {
    const q = event.query.toUpperCase();
    saranKelas.value = props.kelas.filter((k) => k.includes(q));
}

const hariIni = new Date();
const isSiswa = computed(() => props.form.jenis === 'siswa');
</script>

<template>
    <form class="card flex max-w-4xl flex-col gap-6" @submit.prevent="$emit('submit')">
        <section>
            <h2 class="mb-4 text-lg font-semibold">Identitas</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label for="jenis" class="font-medium">Jenis nasabah</label>
                    <Select
                        v-model="form.jenis"
                        input-id="jenis"
                        :options="jenisOptions"
                        option-label="label"
                        option-value="value"
                        :invalid="!!form.errors.jenis"
                    />
                    <FieldError :message="form.errors.jenis" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="nama" class="font-medium">Nama lengkap</label>
                    <InputText id="nama" v-model="form.nama" :invalid="!!form.errors.nama" autofocus />
                    <FieldError :message="form.errors.nama" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="nis" class="font-medium">{{ isSiswa ? 'NIS' : 'NIP / No. pegawai' }}</label>
                    <InputText id="nis" v-model="form.nis" :invalid="!!form.errors.nis" />
                    <FieldError :message="form.errors.nis" />
                </div>
                <div v-if="isSiswa" class="flex flex-col gap-2">
                    <label for="nisn" class="font-medium">NISN</label>
                    <InputText
                        id="nisn"
                        v-model="form.nisn"
                        inputmode="numeric"
                        maxlength="10"
                        placeholder="10 digit"
                        :invalid="!!form.errors.nisn"
                    />
                    <FieldError :message="form.errors.nisn" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="jenis_kelamin" class="font-medium">Jenis kelamin</label>
                    <Select
                        v-model="form.jenis_kelamin"
                        input-id="jenis_kelamin"
                        :options="jenisKelaminOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Pilih"
                        :invalid="!!form.errors.jenis_kelamin"
                    />
                    <FieldError :message="form.errors.jenis_kelamin" />
                </div>
                <div v-if="isSiswa" class="flex flex-col gap-2">
                    <label for="kelas" class="font-medium">Kelas</label>
                    <AutoComplete
                        v-model="form.kelas"
                        input-id="kelas"
                        :suggestions="saranKelas"
                        placeholder="mis. XI AKL 2"
                        :invalid="!!form.errors.kelas"
                        fluid
                        @complete="cariKelas"
                    />
                    <FieldError :message="form.errors.kelas" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="tempat_lahir" class="font-medium">Tempat lahir</label>
                    <InputText id="tempat_lahir" v-model="form.tempat_lahir" :invalid="!!form.errors.tempat_lahir" />
                    <FieldError :message="form.errors.tempat_lahir" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="tanggal_lahir" class="font-medium">Tanggal lahir</label>
                    <DatePicker
                        v-model="form.tanggal_lahir"
                        input-id="tanggal_lahir"
                        date-format="dd MM yy"
                        :max-date="hariIni"
                        show-icon
                        :invalid="!!form.errors.tanggal_lahir"
                        fluid
                    />
                    <FieldError :message="form.errors.tanggal_lahir" />
                </div>
            </div>
        </section>

        <section>
            <h2 class="mb-4 text-lg font-semibold">Kontak</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label for="no_hp" class="font-medium">Nomor HP</label>
                    <InputText id="no_hp" v-model="form.no_hp" inputmode="tel" :invalid="!!form.errors.no_hp" />
                    <FieldError :message="form.errors.no_hp" />
                </div>
                <div v-if="isSiswa" class="flex flex-col gap-2">
                    <label for="nama_orang_tua" class="font-medium">Nama orang tua / wali</label>
                    <InputText
                        id="nama_orang_tua"
                        v-model="form.nama_orang_tua"
                        :invalid="!!form.errors.nama_orang_tua"
                    />
                    <FieldError :message="form.errors.nama_orang_tua" />
                </div>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="alamat" class="font-medium">Alamat</label>
                    <Textarea id="alamat" v-model="form.alamat" rows="2" :invalid="!!form.errors.alamat" />
                    <FieldError :message="form.errors.alamat" />
                </div>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="keterangan" class="font-medium">Keterangan</label>
                    <Textarea id="keterangan" v-model="form.keterangan" rows="2" :invalid="!!form.errors.keterangan" />
                    <FieldError :message="form.errors.keterangan" />
                </div>
            </div>
        </section>

        <div class="flex gap-2">
            <Button type="submit" :label="submitLabel" icon="pi pi-check" :loading="form.processing" />
            <slot name="actions" />
        </div>
    </form>
</template>
