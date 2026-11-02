import { computed, toValue } from 'vue';

/**
 * Pratinjau jadwal angsuran flat, mengikuti PinjamanService::jadwal(): pokok per bulan
 * dibulatkan ke bawah (sisanya di angsuran terakhir), jasa = pokok × persen / 100 dibulatkan
 * ke rupiah, jatuh tempo = tanggal akad + n bulan tanpa melewati akhir bulan.
 * Hanya tampilan; angka yang tersimpan tetap dihitung server.
 */
export function useJadwalPinjaman(pokok, tenor, persen, tanggal) {
    return computed(() => {
        const p = Math.floor(Number(toValue(pokok)) || 0);
        const n = Math.floor(Number(toValue(tenor)) || 0);
        if (p <= 0 || n <= 0) return [];

        const perBulan = Math.floor(p / n);
        const jasa = Math.round((p * Number(toValue(persen) || 0)) / 100);
        const [y, m, d] = String(toValue(tanggal)).split('-').map(Number);

        return Array.from({ length: n }, (_, i) => {
            const ke = i + 1;
            const pokokKe = ke === n ? p - perBulan * (n - 1) : perBulan;

            return { ke, jatuh_tempo: tambahBulan(y, m, d, ke), pokok: pokokKe, jasa, total: pokokKe + jasa };
        });
    });
}

function tambahBulan(y, m, d, n) {
    const indeks = m - 1 + n;
    const tahun = y + Math.floor(indeks / 12);
    const bulan = indeks % 12;
    const hari = Math.min(d, new Date(tahun, bulan + 1, 0).getDate());

    return `${tahun}-${String(bulan + 1).padStart(2, '0')}-${String(hari).padStart(2, '0')}`;
}
