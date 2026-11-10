<?php

use App\Jobs\SendTextBlastJob;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\SmsRecipient;
use App\Models\User;
use App\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function cancellationBroadcast(User $owner): Broadcast
{
    return Broadcast::create(['user_id' => $owner->id, 'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Queued notice', 'message' => 'Fixture', 'target' => 'Citywide', 'recipient_count' => 1, 'is_test' => false]);
}

beforeEach(fn () => config(['operations.sms_provider' => 'itexmo']));

test('owner cancels a queued SMS and repeat job execution never calls the provider', function () {
    Http::preventStrayRequests();
    config(['operations.itexmo.dry_run' => false, 'operations.itexmo.email' => 'fake@example.com', 'operations.itexmo.password' => 'fake', 'operations.itexmo.api_code' => 'fake']);
    $owner = User::factory()->create();
    $category = Category::create(['name' => 'Responders']);
    $contact = Contact::create(['first_name' => 'Test', 'last_name' => 'Contact', 'phone_number' => '09171234567', 'category_id' => $category->id, 'is_active' => true]);
    $recipient = cancellationBroadcast($owner)->recipients()->create(['contact_id' => $contact->id, 'phone_number' => $contact->phone_number, 'status' => 'queued']);
    $this->actingAs($owner)->from('/reports')->post('/reports/sms/'.$recipient->id.'/cancel')->assertRedirect('/reports')->assertSessionHas('success');
    $job = new SendTextBlastJob($recipient->id);
    $job->handle(new SmsGateway);
    $job->handle(new SmsGateway);
    $job->failed(null);
    expect($recipient->fresh()->status)->toBe('cancelled');
    expect($recipient->fresh()->processed_at)->not->toBeNull();
    expect(AuditLog::where('action', 'sms.cancelled')->count())->toBe(1);
    Http::assertNothingSent();
    $this->post('/reports/sms/'.$recipient->id.'/cancel')->assertSessionHas('error');
    expect(AuditLog::where('action', 'sms.cancelled')->count())->toBe(1);
    $this->get('/reports?sms_status=cancelled')->assertOk()->assertInertia(fn (Assert $page) => $page->where('smsSummary.cancelled', 1)->has('smsRecipients.data', 1)->where('smsRecipients.data.0.can_cancel', false));
});

test('broadcast cancellation only changes queued recipients', function () {
    $owner = User::factory()->create();
    $broadcast = cancellationBroadcast($owner);
    foreach (['queued', 'queued', 'processing', 'accepted', 'unknown', 'failed', 'simulated', 'skipped', 'cancelled'] as $status) {
        $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => $status]);
    }
    $this->actingAs($owner)->post('/reports/broadcasts/'.$broadcast->id.'/cancel')->assertSessionHas('success');
    expect($broadcast->recipients()->where('status', 'queued')->count())->toBe(0);
    expect($broadcast->recipients()->where('status', 'cancelled')->count())->toBe(3);
    expect($broadcast->recipients()->where('status', 'processing')->count())->toBe(1);
    expect($broadcast->recipients()->where('status', 'accepted')->count())->toBe(1);
    expect(AuditLog::where('action', 'sms.cancelled')->first()->metadata)->toBe(['cancelled_count' => 2]);
});

test('messages already claimed by the worker or finalized cannot be cancelled', function (string $status) {
    $owner = User::factory()->create();
    $recipient = cancellationBroadcast($owner)->recipients()->create(['phone_number' => '09171234567', 'status' => $status]);
    $this->actingAs($owner)->post('/reports/sms/'.$recipient->id.'/cancel')->assertSessionHas('error');
    expect($recipient->fresh()->status)->toBe($status);
    expect(AuditLog::where('action', 'sms.cancelled')->exists())->toBeFalse();
})->with(['processing', 'accepted', 'unknown', 'failed', 'rejected', 'simulated', 'skipped', 'cancelled']);

test('staff cannot cancel another senders messages but administrators can', function (string $target) {
    $owner = User::factory()->create();
    $broadcast = cancellationBroadcast($owner);
    $recipient = $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => 'queued']);
    $url = $target === 'recipient' ? '/reports/sms/'.$recipient->id.'/cancel' : '/reports/broadcasts/'.$broadcast->id.'/cancel';
    $this->actingAs(User::factory()->create(['role' => 'staff']))->post($url)->assertForbidden();
    expect($recipient->fresh()->status)->toBe('queued');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post($url)->assertSessionHas('success');
    expect($recipient->fresh()->status)->toBe('cancelled');
})->with(['recipient', 'broadcast']);

test('cancellation requires active authenticated access', function (string $target, bool $signedIn) {
    $owner = User::factory()->create(['is_active' => false]);
    $broadcast = cancellationBroadcast($owner);
    $recipient = $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => 'queued']);
    $url = $target === 'recipient' ? '/reports/sms/'.$recipient->id.'/cancel' : '/reports/broadcasts/'.$broadcast->id.'/cancel';
    if ($signedIn) {
        $this->actingAs($owner)->post($url)->assertForbidden();
    } else {
        $this->post($url)->assertRedirect('/login');
    }
    expect($recipient->fresh()->status)->toBe('queued');
})->with(['recipient', 'broadcast'])->with([true, false]);
