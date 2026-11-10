<?php

use App\Models\Advisory;
use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function draftData(): array
{
    return ['title' => 'Flood monitoring', 'severity' => 'warning', 'message' => 'Monitor official river level updates.'];
}

test('operational users can create update and delete advisory drafts without dispatching SMS', function (string $role) {
    Queue::fake();
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->post('/menu/advisories', [...draftData(), 'user_id' => 999])->assertSessionHasNoErrors();
    $advisory = Advisory::sole();
    expect($advisory->user_id)->toBe($user->id);
    expect(DB::table('advisories')->where('id', $advisory->id)->value('message'))->not->toBe(draftData()['message']);
    $this->get('/menu/quick-advisory')->assertInertia(fn (Assert $page) => $page->has('advisories.data', 1)->where('advisories.data.0.message', draftData()['message']));
    $this->put('/menu/advisories/'.$advisory->id, ['title' => 'Updated notice', 'severity' => 'critical', 'message' => 'Follow verified evacuation instructions.'])->assertSessionHasNoErrors();
    expect($advisory->fresh()->title)->toBe('Updated notice');
    $this->delete('/menu/advisories/'.$advisory->id)->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('advisories', ['id' => $advisory->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'advisory.updated', 'subject_id' => $advisory->id]);
    Queue::assertNothingPushed();
})->with(['staff', 'admin']);

test('advisory deletion leaves dispatched message history intact', function () {
    $draft = Advisory::create(draftData());
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => $draft->title, 'severity' => $draft->severity, 'message' => $draft->message, 'target' => 'Citywide', 'recipient_count' => 1, 'is_test' => true]);
    $recipient = $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => 'simulated']);
    $this->actingAs(User::factory()->create())->delete('/menu/advisories/'.$draft->id)->assertSessionHasNoErrors();
    expect($broadcast->fresh()->message)->toBe(draftData()['message']);
    $this->assertDatabaseHas('sms_recipients', ['id' => $recipient->id, 'status' => 'simulated']);
});

test('invalid advisory drafts are rejected', function (array $changes, string $field) {
    $this->actingAs(User::factory()->create())->post('/menu/advisories', [...draftData(), ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('advisories', 0);
})->with([
    [['title' => '  '], 'title'], [['severity' => 'invalid'], 'severity'], [['message' => str_repeat('x', 671)], 'message'],
]);

test('guest and disabled users cannot alter saved advisories', function () {
    $draft = Advisory::create(draftData());
    $this->post('/menu/advisories', draftData())->assertRedirect('/login');
    $this->put('/menu/advisories/'.$draft->id, draftData())->assertRedirect('/login');
    $this->delete('/menu/advisories/'.$draft->id)->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['is_active' => false]))->delete('/menu/advisories/'.$draft->id)->assertForbidden();
    $this->assertDatabaseHas('advisories', ['id' => $draft->id]);
});

test('SMS Text Blast exposes all saved templates including those beyond the manager first page', function () {
    for ($index = 0; $index < 14; $index++) {
        Advisory::create(['title' => 'Saved draft '.$index, 'severity' => 'warning', 'message' => 'Verified draft message '.$index]);
    }
    $selected = Advisory::latest('id')->first();
    $this->actingAs(User::factory()->create())->get('/menu/textblast?advisory='.$selected->id)->assertInertia(fn (Assert $page) => $page
        ->component('menu/textblast')->has('templates.data', 14)->where('selectedAdvisoryId', $selected->id));
    $selected->update(['message' => 'Updated SMS draft']);
    $this->get('/menu/textblast')->assertInertia(fn (Assert $page) => $page->where('templates.data', fn ($templates) => collect($templates)->firstWhere('id', $selected->id)['message'] === 'Updated SMS draft'));
    $selected->delete();
    $this->get('/menu/textblast')->assertInertia(fn (Assert $page) => $page->has('templates', 13)->where('templates', fn ($templates) => ! collect($templates)->contains('id', $selected->id)));
});
