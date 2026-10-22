<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('mengganti kata sandi sendiri', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/profil/password', [
            'current_password' => 'password',
            'password' => 'rahasia-baru',
            'password_confirmation' => 'rahasia-baru',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('rahasia-baru', $user->fresh()->password))->toBeTrue();
});

it('menolak kata sandi lama yang salah', function () {
    $this->actingAs(User::factory()->create())
        ->put('/profil/password', [
            'current_password' => 'salah',
            'password' => 'rahasia-baru',
            'password_confirmation' => 'rahasia-baru',
        ])
        ->assertSessionHasErrors('current_password');
});
