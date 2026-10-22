<?php

use App\Models\User;

it('menerbitkan token sanctum untuk aplikasi nasabah', function () {
    User::factory()->create(['username' => 'nasabah1']);

    $token = $this->postJson('/api/v1/auth/token', [
        'username' => 'nasabah1',
        'password' => 'password',
        'device_name' => 'android',
    ])->assertCreated()->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/user')
        ->assertOk()
        ->assertJsonPath('username', 'nasabah1');
});

it('menolak kredensial salah pada endpoint token', function () {
    User::factory()->create(['username' => 'nasabah1']);

    $this->postJson('/api/v1/auth/token', [
        'username' => 'nasabah1',
        'password' => 'salah',
        'device_name' => 'android',
    ])->assertUnprocessable();
});

it('mencabut token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('android')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
