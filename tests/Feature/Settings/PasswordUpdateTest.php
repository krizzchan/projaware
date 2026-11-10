<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'Strong-Updated-Pass123!',
            'password_confirmation' => 'Strong-Updated-Pass123!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    expect(Hash::check('Strong-Updated-Pass123!', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'Strong-Updated-Pass123!',
            'password_confirmation' => 'Strong-Updated-Pass123!',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/settings/password');
});

test('weak replacement passwords are rejected', function () {
    $this->actingAs(User::factory()->create())
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'weak-password',
            'password_confirmation' => 'weak-password',
        ])->assertSessionHasErrors('password');
});

test('a password change invalidates an established older session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk();
    $oldSessionHash = session('password_hash_web');
    expect($oldSessionHash)->not->toBeNull();
    $user->update(['password' => Hash::make('Strong-Changed-Pass123!')]);
    $this->withSession(['password_hash_web' => $oldSessionHash])
        ->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});
