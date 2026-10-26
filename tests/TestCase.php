<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Test tidak boleh memanggil API sungguhan (mis. API data siswa sekolah); pakai Http::fake().
        Http::preventStrayRequests();
    }
}
