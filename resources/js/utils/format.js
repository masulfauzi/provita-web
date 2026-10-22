/**
 * Helper format tampilan. Nominal uang dari server berupa string desimal
 * ("1250000.00"); jangan dipakai untuk perhitungan di sisi JS.
 */

const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const BULAN = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/**
 * rupiah(1250000) => "Rp 1.250.000"
 */
export function rupiah(value) {
    if (value === null || value === undefined || value === '') return 'Rp 0';

    // Intl memakai spasi tak terputus setelah "Rp"; diseragamkan menjadi spasi biasa.
    return rupiahFormatter.format(Number(value)).replace(/\s/g, ' ');
}

function toDate(value) {
    if (value instanceof Date) return value;
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        // Tanggal tanpa jam dibaca sebagai tanggal lokal, bukan UTC.
        const [y, m, d] = value.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    return new Date(value);
}

/**
 * tanggal('2026-10-22') => "22 Oktober 2026"
 * tanggal('2026-10-22', { hari: true }) => "Kamis, 22 Oktober 2026"
 * tanggal('2026-10-22T08:30:00Z', { jam: true }) => "22 Oktober 2026 15:30"
 */
export function tanggal(value, { hari = false, jam = false } = {}) {
    if (!value) return '-';

    const date = toDate(value);
    if (Number.isNaN(date.getTime())) return '-';

    let text = `${String(date.getDate()).padStart(2, '0')} ${BULAN[date.getMonth()]} ${date.getFullYear()}`;

    if (hari) text = `${HARI[date.getDay()]}, ${text}`;
    if (jam) {
        text += ` ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    return text;
}

const SATUAN = [
    '',
    'satu',
    'dua',
    'tiga',
    'empat',
    'lima',
    'enam',
    'tujuh',
    'delapan',
    'sembilan',
    'sepuluh',
    'sebelas',
];

const SKALA = [
    [1_000_000_000_000n, 'triliun'],
    [1_000_000_000n, 'miliar'],
    [1_000_000n, 'juta'],
    [1_000n, 'ribu'],
];

function ejaan(n) {
    if (n < 12n) return SATUAN[Number(n)];
    if (n < 20n) return `${ejaan(n - 10n)} belas`;
    if (n < 100n) return `${ejaan(n / 10n)} puluh ${ejaan(n % 10n)}`;
    if (n < 200n) return `seratus ${ejaan(n - 100n)}`;
    if (n < 1000n) return `${ejaan(n / 100n)} ratus ${ejaan(n % 100n)}`;

    for (const [nilai, nama] of SKALA) {
        if (n >= nilai) {
            const depan = n / nilai;
            const sisa = n % nilai;
            const awal = nilai === 1000n && depan === 1n ? 'seribu' : `${ejaan(depan)} ${nama}`;
            return `${awal} ${ejaan(sisa)}`;
        }
    }

    return '';
}

/**
 * Setara fungsi `terbilang` di VBA lama.
 * terbilang(1250000) => "satu juta dua ratus lima puluh ribu rupiah"
 * terbilang('1500.50', { mataUang: false }) => "seribu lima ratus koma lima puluh"
 */
export function terbilang(value, { mataUang = true } = {}) {
    const [bulat = '0', pecahan = ''] = String(value ?? 0)
        .trim()
        .replace(/^-/, '')
        .split('.');
    const negatif = String(value ?? '')
        .trim()
        .startsWith('-');

    const angka = BigInt(bulat || '0');
    let text = angka === 0n ? 'nol' : ejaan(angka);

    const sen = pecahan.padEnd(2, '0').slice(0, 2);
    if (sen !== '00') {
        text += mataUang ? ` rupiah ${ejaan(BigInt(sen))} sen` : ` koma ${ejaan(BigInt(sen))}`;
    } else if (mataUang) {
        text += ' rupiah';
    }

    if (negatif) text = `minus ${text}`;

    return text.replace(/\s+/g, ' ').trim();
}
