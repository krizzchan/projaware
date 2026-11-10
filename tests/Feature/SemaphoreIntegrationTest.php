<?php

use App\Jobs\SendTextBlastJob;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\SmsRecipient;
use App\Models\User;
use App\SmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['operations.sms_provider' => 'semaphore', 'operations.semaphore.dry_run' => false, 'operations.semaphore.api_key' => 'fake-private-semaphore-key', 'operations.semaphore.sender_name' => 'AWARE']);
    Http::preventStrayRequests();
});

function semaphoreRecipient(bool $test = false): SmsRecipient
{
    $category = Category::create(['name' => 'Responders']);
    $contact = Contact::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'phone_number' => '09171234567', 'category_id' => $category->id, 'is_active' => true]);
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Alert', 'message' => 'CDRRMO ILAGAN: Exercise advisory.', 'target' => 'Selected contacts', 'recipient_count' => 1, 'is_test' => $test]);

    return $broadcast->recipients()->create(['contact_id' => $contact->id, 'phone_number' => $contact->phone_number]);
}

test('Semaphore acceptance uses the documented form and never sends a claimed recipient twice', function (string $status) {
    Http::fake(['https://api.semaphore.co/api/v4/messages' => Http::response([['message_id' => 123, 'recipient' => '639171234567', 'status' => $status]])]);
    $recipient = semaphoreRecipient();
    $job = new SendTextBlastJob($recipient->id);
    $job->handle(new SmsGateway);
    $job->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('accepted');
    expect($job->provider)->toBe('semaphore');
    expect($job->tries)->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.semaphore.co/api/v4/messages'
        && $request['apikey'] === 'fake-private-semaphore-key' && $request['number'] === '09171234567'
        && $request['sendername'] === 'AWARE' && $request['message'] === $recipient->broadcast->message);
})->with(['Queued', 'Pending', 'Sent']);

test('Semaphore uses the account default sender when no sender name is configured', function () {
    config(['operations.semaphore.sender_name' => null]);
    Http::fake(['https://api.semaphore.co/api/v4/messages' => Http::response([['message_id' => 123, 'recipient' => '09171234567', 'status' => 'Queued']])]);
    expect((new SmsGateway)->send('09171234567', 'CDRRMO advisory'))->toBe('accepted');
    Http::assertSent(fn (Request $request) => ! array_key_exists('sendername', $request->data()));
});

test('Semaphore rejects errors and does not treat malformed or mismatched responses as accepted', function (mixed $body, int $httpCode, string $expected) {
    Http::fake(['https://api.semaphore.co/api/v4/messages' => Http::response($body, $httpCode)]);
    $recipient = semaphoreRecipient();
    (new SendTextBlastJob($recipient->id))->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe($expected);
    expect($recipient->fresh()->error)->not->toContain('fake-private-semaphore-key');
    Http::assertSentCount(1);
})->with([
    [[['message_id' => 1, 'recipient' => '09171234567', 'status' => 'Failed']], 200, 'rejected'],
    [[['message_id' => 1, 'recipient' => '09171234567', 'status' => 'Refunded']], 200, 'rejected'],
    [[['message_id' => 1, 'recipient' => '09171234568', 'status' => 'Queued']], 200, 'unknown'],
    [[['recipient' => '09171234567', 'status' => 'Queued']], 200, 'unknown'],
    [['error' => 'fake-private-semaphore-key'], 401, 'rejected'],
    [['error' => 'rate limit'], 429, 'rejected'],
    ['not json', 200, 'unknown'],
    ['unavailable', 500, 'unknown'],
]);

test('Semaphore network ambiguity is recorded without retrying transmission', function () {
    Http::fake(['https://api.semaphore.co/api/v4/messages' => Http::failedConnection()]);
    $recipient = semaphoreRecipient();
    $job = new SendTextBlastJob($recipient->id);
    $job->handle(new SmsGateway);
    $job->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('unknown');
    Http::assertSentCount(1);
});

test('simulation and missing or disabled Semaphore credentials cannot transmit SMS', function (string $mode, string $expected) {
    $recipient = semaphoreRecipient($mode === 'simulation');
    if ($mode === 'missing') {
        config(['operations.semaphore.api_key' => null]);
    }
    if ($mode === 'disabled') {
        config(['operations.semaphore.dry_run' => true]);
    }
    (new SendTextBlastJob($recipient->id))->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe($expected);
    Http::assertNothingSent();
})->with([['simulation', 'simulated'], ['missing', 'failed'], ['disabled', 'failed']]);

test('legacy queued jobs retain iTexMo as their provider and cannot switch to Semaphore', function () {
    $recipient = semaphoreRecipient();
    $class = SendTextBlastJob::class;
    $job = unserialize('O:'.strlen($class).':"'.$class.'":1:{s:11:"recipientId";i:'.$recipient->id.';}');
    expect($job->provider)->toBe('itexmo');
    $job->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('failed');
    expect($recipient->fresh()->error)->toContain('provider changed');
    Http::assertNothingSent();
});

test('Semaphore request limiting stops before contacting the provider', function () {
    for ($index = 0; $index < 110; $index++) {
        RateLimiter::hit('sms:semaphore-outbound', 60);
    }
    $recipient = semaphoreRecipient();
    (new SendTextBlastJob($recipient->id))->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('failed');
    expect($recipient->fresh()->error)->toContain('request limit');
    Http::assertNothingSent();
});

test('new broadcasts capture Semaphore while test mode never exposes the API key', function () {
    Queue::fake([SendTextBlastJob::class]);
    config(['operations.semaphore.dry_run' => true]);
    semaphoreRecipient();
    $user = User::factory()->create();
    $this->actingAs($user)->get('/menu/textblast')->assertOk()->assertDontSee('fake-private-semaphore-key');
    $this->post('/menu/textblast', ['request_id' => (string) Str::uuid(), 'message' => 'Exercise advisory'])->assertSessionHasNoErrors();
    Queue::assertPushed(SendTextBlastJob::class, fn (SendTextBlastJob $job) => $job->provider === 'semaphore');
});

test('live messages beginning with TEST are rejected before creating a broadcast', function () {
    Queue::fake([SendTextBlastJob::class]);
    $this->actingAs(User::factory()->create())->post('/menu/textblast', ['request_id' => (string) Str::uuid(), 'message' => 'TEST emergency exercise'])->assertSessionHasErrors('message');
    expect(Broadcast::count())->toBe(0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('Semaphore validation errors show a safe actionable reason without provider secrets', function (string $field, string $expected) {
    Http::fake(['https://api.semaphore.co/api/v4/messages' => Http::response([$field => ['fake-private-semaphore-key']], 200)]);
    $recipient = semaphoreRecipient();
    (new SendTextBlastJob($recipient->id))->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('rejected');
    expect($recipient->fresh()->error)->toContain($expected)->not->toContain('fake-private-semaphore-key');
    Http::assertSentCount(1);
})->with([['sendername', 'sender name'], ['apikey', 'API credentials'], ['number', 'recipient number'], ['message', 'message content']]);

test('nested Semaphore validation errors are recognized without blindly retrying', function () {
    Http::fake(['https://api.semaphore.co/api/v4/messages' => Http::response(['errors' => ['sendername' => ['fake-private-semaphore-key']]], 422)]);
    $recipient = semaphoreRecipient();
    $job = new SendTextBlastJob($recipient->id);
    $job->handle(new SmsGateway);
    $job->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('rejected');
    expect($recipient->fresh()->error)->toContain('approved active sender')->not->toContain('fake-private-semaphore-key');
    Http::assertSentCount(1);
});
