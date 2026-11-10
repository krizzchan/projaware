<?php

use App\BroadcastService;
use App\Jobs\SendTextBlastJob;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;


beforeEach(fn () => config(['operations.sms_provider' => 'itexmo']));

test('password change revokes remember access and outstanding reset tokens', function () {
    $user = User::factory()->create(['remember_token' => 'old-remember-token']);
    Password::createToken($user);

    $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'New-Strong-Password123!',
        'password_confirmation' => 'New-Strong-Password123!',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->remember_token)->toBeNull();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed', 'subject_id' => $user->id]);
    expect(AuditLog::where('action', 'user.password_changed')->first()->metadata)->toBe([]);
});

test('repeated password confirmations are rate limited', function () {
    $this->actingAs(User::factory()->create());
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/confirm-password', ['password' => 'incorrect'])->assertSessionHasErrors('password');
    }
    $this->post('/confirm-password', ['password' => 'incorrect'])->assertTooManyRequests();
});

test('database queue insertion rolls back with its broadcast transaction', function () {
    Http::preventStrayRequests();
    config(['queue.default' => 'database', 'queue.connections.database.connection' => null, 'operations.itexmo.dry_run' => true]);
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Responders']);
    $barangay = Barangay::create(['name' => 'Alibagu']);
    Contact::create(['first_name' => 'Test', 'last_name' => 'Responder', 'phone_number' => '09171234567', 'category_id' => $category->id, 'barangay_id' => $barangay->id, 'is_active' => true]);
    $this->actingAs($user);
    $broadcast = null;
    DB::beginTransaction();
    try {
        $broadcast = app(BroadcastService::class)->create($user, ['request_id' => (string) Str::uuid(), 'message' => 'Test notice']);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('broadcasts', ['id' => $broadcast->id]);
    } finally {
        DB::rollBack();
    }
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseMissing('broadcasts', ['id' => $broadcast->id]);
    Http::assertNothingSent();
});
