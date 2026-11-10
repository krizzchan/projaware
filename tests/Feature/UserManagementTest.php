<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function administratorCredentials(): array
{
    return ['current_password' => 'password'];
}

test('administrator edits a user identity and invalidates old email verification', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create();
    $this->actingAs($admin)->patch('/users/'.$staff->id, ['name' => 'Updated Staff', 'email' => 'updated@example.com', 'role' => 'staff', 'is_active' => true, ...administratorCredentials()])->assertSessionHasNoErrors();
    expect($staff->fresh()->name)->toBe('Updated Staff');
    expect($staff->fresh()->email)->toBe('updated@example.com');
    expect($staff->fresh()->email_verified_at)->toBeNull();
});

test('administrator user edit rejects duplicate email and incorrect confirmation password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create();
    $this->actingAs($admin)->patch('/users/'.$staff->id, ['name' => 'Updated', 'email' => $admin->email, 'role' => 'staff', 'is_active' => true, 'current_password' => 'wrong'])->assertSessionHasErrors(['email', 'current_password']);
    expect($staff->fresh()->email)->toBe($staff->email);
});

test('administrator resets a user password with hashed storage and invalidated recovery tokens', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['remember_token' => 'old-token']);
    DB::table('password_reset_tokens')->insert(['email' => $staff->email, 'token' => 'stale-recovery-token', 'created_at' => now()]);
    $this->actingAs($admin)->patch('/users/'.$staff->id.'/password', ['password' => 'New-Strong-Pass123!', 'password_confirmation' => 'New-Strong-Pass123!', ...administratorCredentials()])->assertSessionHasNoErrors();
    expect(Hash::check('New-Strong-Pass123!', $staff->fresh()->password))->toBeTrue();
    expect($staff->fresh()->remember_token)->toBeNull();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $staff->email]);
    expect(AuditLog::where('action', 'user.password_reset')->first()->metadata)->toBe([]);
    $this->post('/logout');
    $this->post('/login', ['email' => $staff->email, 'password' => 'New-Strong-Pass123!'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($staff);
});

test('administrator password reset rejects weak passwords and missing administrator confirmation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create();
    $oldHash = $staff->password;
    $this->actingAs($admin)->patch('/users/'.$staff->id.'/password', ['password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors(['password', 'current_password']);
    expect($staff->fresh()->password)->toBe($oldHash);
});

test('staff cannot edit or reset another user account', function () {
    $staff = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($staff)->patch('/users/'.$other->id, ['name' => 'Changed', 'role' => 'admin', 'is_active' => true, ...administratorCredentials()])->assertForbidden();
    $this->patch('/users/'.$other->id.'/password', ['password' => 'New-Strong-Pass123!', 'password_confirmation' => 'New-Strong-Pass123!', ...administratorCredentials()])->assertForbidden();
    expect($other->fresh()->role)->toBe('staff');
});

test('administrator reset invalidates the targets established session', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create();
    $this->actingAs($staff)->get('/dashboard')->assertOk();
    $oldSessionHash = session('password_hash_web');
    $this->withSession(['password_hash_web' => $admin->password])->actingAs($admin)
        ->patch('/users/'.$staff->id.'/password', ['password' => 'New-Strong-Pass123!', 'password_confirmation' => 'New-Strong-Pass123!', ...administratorCredentials()])->assertSessionHasNoErrors();
    $this->actingAs($staff->fresh())->withSession(['password_hash_web' => $oldSessionHash])->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});
