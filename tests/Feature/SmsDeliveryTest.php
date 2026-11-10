<?php

use App\Jobs\SendTextBlastJob;
use App\Models\Barangay;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\SmsRecipient;
use App\SmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function deliveryRecipient(bool $test = false): SmsRecipient
{
    $category = Category::create(['name' => 'Responders']);
    $barangay = Barangay::create(['name' => 'Alibagu']);
    $contact = Contact::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'phone_number' => '09171234567', 'category_id' => $category->id, 'barangay_id' => $barangay->id, 'is_active' => true]);
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Test alert', 'message' => 'Test message', 'target' => 'Alibagu', 'recipient_count' => 1, 'is_test' => $test]);

    return $broadcast->recipients()->create(['contact_id' => $contact->id, 'phone_number' => $contact->phone_number]);
}

function configureTestGateway(): void
{
    config(['operations.itexmo.dry_run' => false, 'operations.itexmo.email' => 'test@example.com', 'operations.itexmo.password' => 'fake-provider-secret', 'operations.itexmo.api_code' => 'FAKE-CODE']);
    Http::preventStrayRequests();
}

beforeEach(fn () => config(['operations.sms_provider' => 'itexmo']));

test('test broadcasts never call the SMS provider even if live mode is subsequently enabled', function () {
    configureTestGateway();
    $recipient = deliveryRecipient(true);

    (new SendTextBlastJob($recipient->id))->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('simulated');
    Http::assertNothingSent();
});

test('a disabled or changed contact is skipped before transmission', function (bool $disable) {
    configureTestGateway();
    $recipient = deliveryRecipient();
    Contact::whereKey($recipient->contact_id)->update($disable ? ['is_active' => false] : ['phone_number' => '09171234568']);

    (new SendTextBlastJob($recipient->id))->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('skipped');
    Http::assertNothingSent();
})->with([true, false]);

test('iTexMo acceptance is recorded once with the documented request format', function () {
    configureTestGateway();
    Http::fake(['https://api.itexmo.com/api/broadcast' => Http::response(['Error' => false, 'Accepted' => 1, 'Failed' => 0, 'ReferenceId' => 'fake-reference'])]);
    $recipient = deliveryRecipient();
    $job = new SendTextBlastJob($recipient->id);

    $job->handle(new SmsGateway);
    $job->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('accepted');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.itexmo.com/api/broadcast' && $request['Recipients'] === '["09171234567"]' && $request['Email'] === 'test@example.com' && $request['ApiCode'] === 'FAKE-CODE');
    expect($recipient->toArray())->not->toHaveKey('phone_number');
});

test('gateway failures are recorded without exposing provider secrets', function (array|string $body, int $code, string $expected) {
    configureTestGateway();
    Http::fake(['https://api.itexmo.com/api/broadcast' => Http::response($body, $code)]);
    $recipient = deliveryRecipient();

    (new SendTextBlastJob($recipient->id))->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe($expected);
    expect($recipient->fresh()->error)->not->toContain('fake-provider-secret');
    Http::assertSentCount(1);
})->with([
    [['Error' => true, 'Message' => 'fake-provider-secret'], 200, 'rejected'],
    ['not-json', 200, 'unknown'],
    [['Error' => false, 'Accepted' => 0, 'Failed' => 1], 200, 'unknown'],
    ['Server error', 500, 'unknown'],
]);

test('ambiguous network failure is not automatically resent', function () {
    configureTestGateway();
    Http::fake(['https://api.itexmo.com/api/broadcast' => Http::failedConnection()]);
    $recipient = deliveryRecipient();
    $job = new SendTextBlastJob($recipient->id);

    $job->handle(new SmsGateway);
    $job->handle(new SmsGateway);
    expect($recipient->fresh()->status)->toBe('unknown');
    expect($job->tries)->toBe(1);
});
