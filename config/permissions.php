<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Peran
    |--------------------------------------------------------------------------
    |
    | Peran yang selalu dibuat oleh `php artisan permission:sync-modules`.
    |
    */

    'roles' => [
        'bendahara' => 'Bendahara',
        'teller' => 'Teller',
        'admin' => 'Admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Peran dengan semua permission
    |--------------------------------------------------------------------------
    |
    | Peran ini otomatis mendapat setiap permission yang dideklarasikan,
    | termasuk permission modul baru.
    |
    */

    'all_permissions_roles' => ['bendahara'],

    /*
    |--------------------------------------------------------------------------
    | Permission tambahan
    |--------------------------------------------------------------------------
    |
    | Permission normalnya dideklarasikan di module.json setiap modul. Gunakan
    | daftar ini hanya untuk permission lintas modul atau modul yang belum ada.
    | Format: 'modul.aksi' => ['label' => '...', 'roles' => ['teller']].
    |
    */

    'permissions' => [
        //
    ],

];
