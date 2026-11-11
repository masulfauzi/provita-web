<script setup>
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
import FieldError from '@/Components/FieldError.vue';
import PeriksaNisn from './PeriksaNisn.vue';

const props = defineProps({
    form: { type: Object, required: true },
    kelas: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    jenisKelaminOptions: { type: Array, default: () => [] },
    waliOptions: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Simpan' },
    // "create": siswa wajib memeriksa NISN dulu sebelum mengisi form; "edit": periksa opsional.
    mode: { type: String, default: 'create' },
    // NISN sudah terisi (mis. dari menu Daftar siswa): langsung diperiksa saat form dibuka.
    periksaOtomatis: { type: Boolean, default: false },
});

defineEmits(['submit']);

const saranKelas = ref([]);
function cariKelas(event) {
    const q = event.query.toUpperCase();
    saranKelas.value = props.kelas.filter((k) => k.includes(q));
}

const hariIni = new Date();
const isSiswa = computed(() => props.form.jenis === 'siswa');
// Lembaga bukan perorangan: isian identitas pribadi disembunyikan.
const isLembaga = computed(() => props.form.jenis === 'lembaga');

// Hasil pemeriksaan NISN terakhir; hanya berlaku selama NISN di form tidak diubah.
const hasil = ref(null);
const perluPeriksa = computed(() => props.mode === 'create' && isSiswa.value);
const terbuka = computed(
    () =>
        !perluPeriksa.value ||
        (hasil.value?.nisn === props.form.nisn &&
            ['ditemukan', 'tidak_ditemukan', 'gagal'].includes(hasil.value.status)),
);

// Field yang terakhir diisi dari API; dikosongkan lagi saat NISN lain diperiksa,
// supaya data siswa sebelumnya tidak ikut tersimpan dengan NISN yang baru.
let dariApi = [];

function terimaHasil(value) {
    hasil.value = value;

    for (const kunci of dariApi) {
        if (kunci !== 'nisn') props.form[kunci] = '';
    }
    dariApi = [];

    if (value.status !== 'ditemukan') return;

    // Semua data dari API menimpa isian form; field yang kosong di API dibiarkan.
    for (const [kunci, nilai] of Object.entries(value.data)) {
        if (!nilai) continue;
        props.form[kunci] = nilai;
        dariApi.push(kunci);
    }
    props.form.clearErrors();
}
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
                <PeriksaNisn
                    v-if="isSiswa"
                    v-model="form.nisn"
                    :error="form.errors.nisn"
                    :hasil="hasil"
                    :otomatis="periksaOtomatis"
                    @hasil="terimaHasil"
                />
            </div>

            <p v-if="!terbuka" class="mt-4 text-muted-color">
                <i class="pi pi-info-circle" /> Masukkan NISN siswa lalu klik <strong>Periksa</strong>. Data dari sistem
                sekolah akan diisikan otomatis.
            </p>

            <div v-if="terbuka" class="mt-5 grid gap-5 md:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label for="nama" class="font-medium">{{ isLembaga ? 'Nama lembaga' : 'Nama lengkap' }}</label>
                    <InputText
                        id="nama"
                        v-model="form.nama"
                        :placeholder="isLembaga ? 'mis. OSIS SMKN 2 Semarang' : undefined"
                        :invalid="!!form.errors.nama"
                    />
                    <FieldError :message="form.errors.nama" />
                </div>
                <p v-if="isLembaga" class="self-end text-sm text-muted-color">
                    <i class="pi pi-info-circle" /> Nomor rekening lembaga dibuat otomatis dari nomor nasabah.
                </p>
                <div v-if="!isLembaga" class="flex flex-col gap-2">
                    <label for="nis" class="font-medium">{{ isSiswa ? 'NIS' : 'NIP / No. pegawai' }}</label>
                    <InputText id="nis" v-model="form.nis" :invalid="!!form.errors.nis" />
                    <FieldError :message="form.errors.nis" />
                </div>
                <div v-if="!isLembaga" class="flex flex-col gap-2">
                    <label for="nik" class="font-medium">NIK</label>
                    <InputText
                        id="nik"
                        v-model="form.nik"
                        inputmode="numeric"
                        maxlength="16"
                        placeholder="16 digit"
                        :invalid="!!form.errors.nik"
                    />
                    <FieldError :message="form.errors.nik" />
                </div>
                <div v-if="!isLembaga" class="flex flex-col gap-2">
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
                <div v-if="!isLembaga" class="flex flex-col gap-2">
                    <label for="tempat_lahir" class="font-medium">Tempat lahir</label>
                    <InputText id="tempat_lahir" v-model="form.tempat_lahir" :invalid="!!form.errors.tempat_lahir" />
                    <FieldError :message="form.errors.tempat_lahir" />
                </div>
                <div v-if="!isLembaga" class="flex flex-col gap-2">
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

        <section v-if="terbuka">
            <h2 class="mb-4 text-lg font-semibold">Kontak</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <label for="no_hp" class="font-medium">Nomor HP</label>
                    <InputText id="no_hp" v-model="form.no_hp" inputmode="tel" :invalid="!!form.errors.no_hp" />
                    <FieldError :message="form.errors.no_hp" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="email" class="font-medium">Email</label>
                    <InputText id="email" v-model="form.email" type="email" :invalid="!!form.errors.email" />
                    <FieldError :message="form.errors.email" />
                </div>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="alamat_jalan" class="font-medium">Alamat (jalan, nomor rumah)</label>
                    <InputText id="alamat_jalan" v-model="form.alamat_jalan" :invalid="!!form.errors.alamat_jalan" />
                    <FieldError :message="form.errors.alamat_jalan" />
                </div>
                <div class="grid grid-cols-2 gap-5">
                    <div class="flex flex-col gap-2">
                        <label for="rt" class="font-medium">RT</label>
                        <InputText
                            id="rt"
                            v-model="form.rt"
                            inputmode="numeric"
                            maxlength="3"
                            :invalid="!!form.errors.rt"
                        />
                        <FieldError :message="form.errors.rt" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="rw" class="font-medium">RW</label>
                        <InputText
                            id="rw"
                            v-model="form.rw"
                            inputmode="numeric"
                            maxlength="3"
                            :invalid="!!form.errors.rw"
                        />
                        <FieldError :message="form.errors.rw" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="kelurahan" class="font-medium">Kelurahan / desa</label>
                    <InputText id="kelurahan" v-model="form.kelurahan" :invalid="!!form.errors.kelurahan" />
                    <FieldError :message="form.errors.kelurahan" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="kecamatan" class="font-medium">Kecamatan</label>
                    <InputText id="kecamatan" v-model="form.kecamatan" :invalid="!!form.errors.kecamatan" />
                    <FieldError :message="form.errors.kecamatan" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="kota" class="font-medium">Kota / kabupaten</label>
                    <InputText id="kota" v-model="form.kota" :invalid="!!form.errors.kota" />
                    <FieldError :message="form.errors.kota" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="kode_pos" class="font-medium">Kode pos</label>
                    <InputText
                        id="kode_pos"
                        v-model="form.kode_pos"
                        inputmode="numeric"
                        maxlength="5"
                        :invalid="!!form.errors.kode_pos"
                    />
                    <FieldError :message="form.errors.kode_pos" />
                </div>
                <template v-if="isSiswa">
                    <div class="flex flex-col gap-2">
                        <label for="nama_orang_tua" class="font-medium">Nama orang tua / wali</label>
                        <InputText
                            id="nama_orang_tua"
                            v-model="form.nama_orang_tua"
                            :invalid="!!form.errors.nama_orang_tua"
                        />
                        <FieldError :message="form.errors.nama_orang_tua" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="wali_hubungan" class="font-medium">Hubungan</label>
                        <Select
                            v-model="form.wali_hubungan"
                            input-id="wali_hubungan"
                            :options="waliOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Pilih"
                            show-clear
                            :invalid="!!form.errors.wali_hubungan"
                        />
                        <FieldError :message="form.errors.wali_hubungan" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="wali_hp" class="font-medium">Nomor HP orang tua / wali</label>
                        <InputText
                            id="wali_hp"
                            v-model="form.wali_hp"
                            inputmode="tel"
                            :invalid="!!form.errors.wali_hp"
                        />
                        <FieldError :message="form.errors.wali_hp" />
                    </div>
                </template>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="keterangan" class="font-medium">Keterangan</label>
                    <Textarea id="keterangan" v-model="form.keterangan" rows="2" :invalid="!!form.errors.keterangan" />
                    <FieldError :message="form.errors.keterangan" />
                </div>
            </div>
        </section>

        <div class="flex gap-2">
            <Button
                type="submit"
                :label="submitLabel"
                icon="pi pi-check"
                :loading="form.processing"
                :disabled="!terbuka"
            />
            <slot name="actions" />
        </div>
    </form>
</template>
