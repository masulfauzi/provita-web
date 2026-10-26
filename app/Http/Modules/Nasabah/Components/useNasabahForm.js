import { useForm } from '@inertiajs/vue3';

const KOSONG = {
    nis: '',
    nisn: '',
    nik: '',
    nama: '',
    jenis: 'siswa',
    jenis_kelamin: null,
    kelas: '',
    tempat_lahir: '',
    tanggal_lahir: null,
    alamat: '',
    no_hp: '',
    email: '',
    nama_orang_tua: '',
    keterangan: '',
};

function toDate(value) {
    if (!value) return null;
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
}

function toDateString(date) {
    if (!date) return null;
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * Form nasabah (create/edit). Tanggal lahir di-convert Date <-> "YYYY-MM-DD",
 * dan kolom khusus siswa dikosongkan untuk guru/karyawan.
 */
export function useNasabahForm(item = null) {
    const initial = Object.fromEntries(Object.keys(KOSONG).map((key) => [key, item?.[key] ?? KOSONG[key]]));
    initial.tanggal_lahir = toDate(item?.tanggal_lahir);

    const form = useForm(initial);

    form.transform((data) => ({
        ...data,
        tanggal_lahir: toDateString(data.tanggal_lahir),
        nisn: data.jenis === 'siswa' ? data.nisn : null,
        kelas: data.jenis === 'siswa' ? data.kelas : null,
        nama_orang_tua: data.jenis === 'siswa' ? data.nama_orang_tua : null,
    }));

    return form;
}
