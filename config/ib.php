<?php

/*
|--------------------------------------------------------------------------
| Internet Banking nasabah
|--------------------------------------------------------------------------
|
| API untuk frontend provita-ib (ib.<domain>), lihat docs/api-nasabah.md.
|
*/

return [

    // Origin frontend IB untuk CORS (config/cors.php), mis. https://ib.bankprovita.sch.id.
    'url' => env('IB_URL'),

    // Sesi nasabah memakai cookie sendiri agar terpisah dari sesi panel petugas.
    'session' => [
        'cookie' => env('IB_SESSION_COOKIE', 'provita_ib_session'),
        // Domain induk bersama ib.<domain> dan api.<domain>, mis. ".bankprovita.sch.id".
        'domain' => env('IB_SESSION_DOMAIN', env('SESSION_DOMAIN')),
        'lifetime' => (int) env('IB_SESSION_LIFETIME', 15),
    ],

    // Kode jenis rekening => produk di IB (reguler | berjangka | deposito). Kode yang tidak
    // tercantum dianggap reguler.
    'produk' => [
        'tabungan' => 'reguler',
        'wisata' => 'reguler',
        'wisuda' => 'reguler',
        'foto_katalog' => 'reguler',
    ],

    // transaksi.jenis => kategori mutasi di IB.
    'kategori' => [
        'setor' => 'setoran_tunai',
        'tarik' => 'penarikan_tunai',
        'biaya' => 'biaya_admin',
        'bunga' => 'bunga',
        'pindah_keluar' => 'pindah_buku',
        'pindah_masuk' => 'pindah_buku',
        'penutupan' => 'penutupan',
        'pembalik' => 'koreksi',
    ],

    'label_kategori' => [
        'setoran_tunai' => 'Setoran tunai',
        'penarikan_tunai' => 'Penarikan tunai',
        'biaya_admin' => 'Biaya administrasi',
        'bunga' => 'Bunga',
        'pindah_buku' => 'Pindah buku',
        'penutupan' => 'Penutupan rekening',
        'koreksi' => 'Koreksi',
    ],

    // Batas rentang mutasi & riwayat login.
    'mutasi_maks_bulan' => 3,
    'riwayat_login_disimpan' => 50,
];
