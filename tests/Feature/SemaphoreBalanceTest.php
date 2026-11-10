<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['operations.sms_provider' => 'semaphore', 'operations.semaphore.api_key' => 'fake-private-key']);
    Http::preventStrayRequests();
});

test('administrator sees only safe cached Semaphore balance information', function () {
    $this->freezeTime();
    Http::fake(['https://api.semaphore.co/api/v4/account*' => Http::response(['credit_balance' => '1010', 'account' => 'Private account', 'apikey' => 'fake-private-key'])]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard/sms-balance')->assertOk()
        ->assertExactJson(['status' => 'ok', 'credit_balance' => 1010, 'checked_at' => now()->toIso8601String()])
        ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('fake-private-key')->assertDontSee('Private account');
    $this->get('/dashboard/sms-balance')->assertJsonPath('credit_balance', 1010);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request['apikey'] === 'fake-private-key');
    $this->travel(301)->seconds();
    $this->get('/dashboard/sms-balance')->assertOk();
    Http::assertSentCount(2);
});

test('zero Semaphore credits is a real balance rather than an error', function () {
    Http::fake(['https://api.semaphore.co/api/v4/account*' => Http::response(['credit_balance' => 0])]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard/sms-balance')->assertOk()->assertJsonPath('credit_balance', 0)->assertJsonPath('status', 'ok');
});

test('balance failures never display invented credit counts or provider secrets', function (mixed $body, int $status) {
    Http::fake(['https://api.semaphore.co/api/v4/account*' => Http::response($body, $status)]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard/sms-balance')->assertOk()
        ->assertExactJson(['status' => 'unavailable', 'credit_balance' => null, 'checked_at' => null])->assertDontSee('fake-private-key');
    $this->get('/dashboard/sms-balance')->assertOk();
    Http::assertSentCount(1);
})->with([[['credit_balance' => -1], 200], [['credit_balance' => 'invalid'], 200], [['apikey' => 'fake-private-key'], 401], ['not json', 200], ['down', 503]]);

test('balance lookup handles network failures without leaking the credential URL', function () {
    Http::fake(['https://api.semaphore.co/api/v4/account*' => Http::failedConnection()]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard/sms-balance')->assertOk()
        ->assertJsonPath('status', 'unavailable')->assertJsonPath('credit_balance', null)->assertDontSee('fake-private-key');
});

test('balance lookup is restricted to active administrators', function (string $role, bool $active) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]))->get('/dashboard/sms-balance')->assertForbidden();
    Http::assertNothingSent();
})->with([['staff', true], ['admin', false]]);

test('balance lookup requires authentication', function () {
    $this->get('/dashboard/sms-balance')->assertRedirect('/login');
    Http::assertNothingSent();
});

test('missing Semaphore configuration makes no external account request', function () {
    config(['operations.semaphore.api_key' => null]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard/sms-balance')->assertOk()->assertJsonPath('status', 'unconfigured');
    Http::assertNothingSent();
});

test('concurrent balance lookups do not duplicate the external request', function () {
    $lock = Cache::lock('sms:semaphore:balance:'.hash('sha256', 'fake-private-key').':lock', 15);
    $lock->get();
    try {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard/sms-balance')->assertOk()->assertJsonPath('status', 'loading');
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});
