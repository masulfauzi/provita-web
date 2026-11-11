@php
    use App\Support\Money;
    $uang = fn ($v) => $v === null ? '' : Money::format($v);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekening koran {{ $rekening->nomor }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1f2937; }
        .kop { border-bottom: 2px solid #1f2937; padding-bottom: 6px; margin-bottom: 12px; }
        .kop h1 { font-size: 14pt; margin: 0; }
        .kop p { margin: 2px 0 0; color: #4b5563; }
        h2 { font-size: 11pt; margin: 0 0 8px; text-align: center; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 0; vertical-align: top; }
        .info td.label { width: 28%; color: #4b5563; }
        .mutasi { margin-top: 12px; }
        .mutasi th { background: #f3f4f6; border-bottom: 1px solid #9ca3af; padding: 5px 4px; text-align: left; font-size: 8pt; }
        .mutasi td { border-bottom: 1px solid #e5e7eb; padding: 4px; }
        .angka { text-align: right; white-space: nowrap; }
        .ringkasan { margin-top: 12px; width: 50%; margin-left: 50%; }
        .ringkasan td { padding: 3px 4px; }
        .ringkasan tr.akhir td { border-top: 1px solid #1f2937; font-weight: bold; }
        .kosong { text-align: center; color: #6b7280; padding: 16px; }
        .catatan { margin-top: 18px; font-size: 7.5pt; color: #6b7280; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $kop['nama_bank'] }}</h1>
        @if ($kop['nama_sekolah'])<p>Bank Mini {{ $kop['nama_sekolah'] }}</p>@endif
        @if ($kop['alamat_sekolah'])<p>{{ $kop['alamat_sekolah'] }}</p>@endif
    </div>

    <h2>REKENING KORAN</h2>

    <table class="info">
        <tr><td class="label">Nama</td><td>{{ $rekening->nasabah->nama }}</td></tr>
        <tr><td class="label">Nomor rekening</td><td>{{ $rekening->nomor }} ({{ $rekening->jenisRekening?->nama }})</td></tr>
        <tr><td class="label">Periode</td><td>{{ $dari->translatedFormat('j F Y') }} s.d. {{ $sampai->translatedFormat('j F Y') }}</td></tr>
        <tr><td class="label">Saldo awal periode</td><td>{{ $uang($ringkasan['saldo_awal']) }}</td></tr>
    </table>

    <table class="mutasi">
        <thead>
            <tr>
                <th style="width: 15%">Tanggal</th>
                <th>Keterangan</th>
                <th class="angka" style="width: 15%">Masuk</th>
                <th class="angka" style="width: 15%">Keluar</th>
                <th class="angka" style="width: 16%">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transaksi as $t)
                @php $keluar = Money::isNegative($t->mutasi); @endphp
                <tr>
                    <td>{{ $t->tanggal->format('d/m/Y') }} {{ $t->created_at->format('H:i') }}</td>
                    <td>{{ $t->keterangan ?: config('ib.label_kategori.'.config('ib.kategori.'.$t->jenis, 'koreksi')) }}</td>
                    <td class="angka">{{ $keluar ? '' : $uang($t->nominal) }}</td>
                    <td class="angka">{{ $keluar ? $uang($t->nominal) : '' }}</td>
                    <td class="angka">{{ $uang($t->saldo_sesudah) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="kosong">Tidak ada transaksi pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ringkasan">
        <tr><td>Saldo awal</td><td class="angka">{{ $uang($ringkasan['saldo_awal']) }}</td></tr>
        <tr><td>Total masuk ({{ $ringkasan['jumlah_masuk'] }})</td><td class="angka">{{ $uang($ringkasan['total_masuk']) }}</td></tr>
        <tr><td>Total keluar ({{ $ringkasan['jumlah_keluar'] }})</td><td class="angka">{{ $uang($ringkasan['total_keluar']) }}</td></tr>
        <tr class="akhir"><td>Saldo akhir</td><td class="angka">{{ $uang($ringkasan['saldo_akhir']) }}</td></tr>
    </table>

    <p class="catatan">
        Dicetak dari Internet Banking pada {{ $dicetak->translatedFormat('j F Y H:i') }} WIB.
        Dokumen ini dibuat otomatis dan sah tanpa tanda tangan.
    </p>
</body>
</html>
