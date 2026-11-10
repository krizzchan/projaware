<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('dashboard summarizes active contacts and barangay coverage', function () {
    $user = User::factory()->create();
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Responders']);
    $barangayId = DB::table('barangays')->insertGetId(['name' => 'Alibagu']);
    DB::table('barangays')->insert(['name' => 'San Vicente']);
    Contact::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'phone_number' => '09171234567', 'category_id' => $categoryId, 'barangay_id' => $barangayId, 'is_active' => true]);
    Contact::create(['first_name' => 'Ben', 'last_name' => 'Reyes', 'phone_number' => '09171234568', 'category_id' => $categoryId, 'barangay_id' => $barangayId, 'is_active' => false]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('dashboard')
        ->where('stats.totalContacts', 2)
        ->where('stats.activeContacts', 1)
        ->where('stats.totalBarangays', 2)
        ->where('stats.coveredBarangays', 1)
        ->where('stats.totalCategories', 1)
        ->where('categories.0.active_contacts_count', 1)
        ->where('categories.0.contacts_count', 2)
        ->where('barangays.1.active_contacts_count', 0)
        ->has('recentContacts', 2)
        ->where('recentContacts.0.first_name', 'Ben'));
});

test('dashboard returns zero counts for an empty directory', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('dashboard')
        ->where('stats.totalContacts', 0)
        ->where('stats.activeContacts', 0)
        ->where('stats.totalBarangays', 0)
        ->where('stats.coveredBarangays', 0)
        ->where('stats.totalCategories', 0)
        ->has('categories', 0)
        ->has('barangays', 0)
        ->has('recentContacts', 0));
});

test('dashboard analytics separates test broadcasts and aggregates recipient results within seven days', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 12:00:00', 'Asia/Manila'));
    $user = User::factory()->create();
    foreach ([['2026-10-07 10:00:00', true, ['simulated']], ['2026-10-06 10:00:00', false, ['accepted', 'rejected', 'failed', 'queued', 'processing', 'unknown', 'skipped']], ['2026-09-30 23:59:59', false, ['accepted']]] as [$date, $test, $statuses]) {
        $broadcast = \App\Models\Broadcast::create(['user_id' => $user->id, 'request_id' => (string) \Illuminate\Support\Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Test', 'severity' => 'info', 'message' => 'Test notice', 'target' => 'All', 'recipient_count' => count($statuses), 'is_test' => $test]);
        $broadcast->created_at = \Illuminate\Support\Carbon::parse($date, 'Asia/Manila')->utc();
        $broadcast->save();
        foreach ($statuses as $status) {
            $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => $status]);
        }
    }
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('analytics.daily', 7)
        ->where('analytics.daily.0.date', '2026-10-01')
        ->where('analytics.daily.0.live', 0)
        ->where('analytics.daily.5.live', 1)
        ->where('analytics.daily.6.test', 1)
        ->where('analytics.liveBroadcasts', 1)
        ->where('analytics.testBroadcasts', 1)
        ->where('analytics.outcomes', ['accepted' => 1, 'failed' => 2, 'pending' => 2, 'unknown' => 1, 'simulated' => 1, 'skipped' => 1, 'cancelled' => 0]));
});
