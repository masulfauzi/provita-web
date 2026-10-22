<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun awal
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh Database\Seeders\UserSeeder. Isi di .env, jangan di sini.
    |
    */

    'users' => [
        'bendahara' => [
            'username' => env('SEED_BENDAHARA_USERNAME', 'bendahara'),
            'name' => env('SEED_BENDAHARA_NAME', 'Bendahara'),
            'password' => env('SEED_BENDAHARA_PASSWORD'),
        ],
        'teller' => [
            'username' => env('SEED_TELLER_USERNAME', 'teller'),
            'name' => env('SEED_TELLER_NAME', 'Teller'),
            'password' => env('SEED_TELLER_PASSWORD'),
        ],
    ],

];
