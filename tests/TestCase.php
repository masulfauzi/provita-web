<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Migration hanya berisi struktur; data wajib (bagan akun, rekening utama) dari seeder.
     */
    protected bool $seed = true;

    protected string $seeder = DataWajibSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Test tidak boleh memanggil API sungguhan (mis. API data siswa sekolah); pakai Http::fake().
        Http::preventStrayRequests();
    }
}
