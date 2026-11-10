<?php

use App\Models\Advisory;
use App\Models\Barangay;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;


beforeEach(fn () => config(['operations.sms_provider' => 'itexmo']));

test('account management is paginated searchable and does not expose hashes', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Administrator']);
    User::factory()->count(30)->create();
    $target = User::factory()->create(['name' => 'Unique Search Target']);
    $this->actingAs($admin)->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users.data', 25)->where('users.total', 32)->missing('users.data.0.password'));
    $this->get('/users?search=Unique%20Search%20Target')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users.data', 1)->where('users.data.0.id', $target->id));
    $this->get('/users?page=0')->assertSessionHasErrors('page');
});

test('recipient picker pages and searches the complete active directory', function () {
    $category = Category::create(['name' => 'Responders']);
    $barangay = Barangay::create(['name' => 'Alibagu']);
    for ($index = 0; $index < 31; $index++) {
        Contact::create(['first_name' => 'Responder', 'last_name' => sprintf('%03d', $index), 'phone_number' => '09'.sprintf('%09d', 123456789 + $index), 'category_id' => $category->id, 'barangay_id' => $barangay->id, 'is_active' => $index !== 30]);
    }
    $this->actingAs(User::factory()->create())->get('/menu/textblast')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('selectableContacts.data', 25)->where('selectableContacts.total', 30));
    $this->get('/menu/textblast?contact_page=2')->assertInertia(fn (AssertableInertia $page) => $page->has('selectableContacts.data', 5));
    $this->get('/menu/textblast?contact_search=029')->assertInertia(fn (AssertableInertia $page) => $page->has('selectableContacts.data', 1)->where('selectableContacts.data.0.last_name', '029'));
    $this->get('/menu/textblast?contact_search=030')->assertInertia(fn (AssertableInertia $page) => $page->has('selectableContacts.data', 0));
});

test('a selected advisory outside the first template page remains available', function () {
    for ($index = 0; $index < 25; $index++) {
        $selected = Advisory::create(['title' => sprintf('Template %03d', $index), 'message' => 'Saved draft', 'severity' => 'info']);
    }
    $this->actingAs(User::factory()->create())->get('/menu/textblast?advisory='.$selected->id)
        ->assertInertia(fn (AssertableInertia $page) => $page->has('templates.data', 20)->where('templates.total', 25)->where('initialTemplate.id', $selected->id)->where('initialTemplate.message', 'Saved draft'));
    $this->get('/menu/textblast?template_search=024')->assertInertia(fn (AssertableInertia $page) => $page->has('templates.data', 1));
});

test('dashboard attributes UTC timestamps to Philippine calendar days', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-08 01:00:00', 'Asia/Manila'));
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Test', 'message' => 'Test notice', 'target' => 'All', 'recipient_count' => 1, 'is_test' => true]);
    $broadcast->created_at = '2026-10-07 16:30:00';
    $broadcast->save();
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('analytics.daily.6.date', '2026-10-08')->where('analytics.daily.6.test', 1)->where('analytics.daily.5.test', 0));
});

test('operations health reports stalled SMS without contacting the provider or exposing contents', function () {
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Private', 'message' => 'Private notice', 'target' => 'All', 'recipient_count' => 1, 'is_test' => true]);
    $recipient = $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => 'processing']);
    $recipient->updated_at = now()->subMinutes(10);
    $recipient->save();
    $this->artisan('operations:health')->expectsOutputToContain('attention_required')->assertExitCode(1);
    expect($recipient->fresh()->status)->toBe('processing');
    \Illuminate\Support\Facades\Http::assertNothingSent();
});

test('daily SMS quota resets at Philippine midnight using UTC storage', function (bool $samePhilippineDay) {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-08 00:30:00', 'Asia/Manila'));
    \Illuminate\Support\Facades\Queue::fake();
    config(['operations.itexmo.dry_run' => true, 'operations.daily_recipient_limit' => 1]);
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Responders']);
    $barangay = Barangay::create(['name' => 'Alibagu']);
    Contact::create(['first_name' => 'Test', 'last_name' => 'Recipient', 'phone_number' => '09171234567', 'category_id' => $category->id, 'barangay_id' => $barangay->id, 'is_active' => true]);
    $history = Broadcast::create(['user_id' => $user->id, 'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Earlier', 'message' => 'Test notice', 'target' => 'All', 'recipient_count' => 1, 'is_test' => true]);
    $history->created_at = $samePhilippineDay ? '2026-10-07 16:10:00' : '2026-10-07 15:59:00';
    $history->save();
    $response = $this->actingAs($user)->post('/menu/textblast', ['request_id' => (string) Str::uuid(), 'message' => 'New notice']);
    if ($samePhilippineDay) {
        $response->assertSessionHasErrors('message');
    } else {
        $response->assertSessionHasNoErrors();
    }
})->with([true, false]);

test('template generation is rate limited before repeated export work', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get('/menu/contacts/template')->assertOk();
    $this->get('/menu/contacts/template')->assertOk();
    $this->get('/menu/contacts/template')->assertTooManyRequests();
});
