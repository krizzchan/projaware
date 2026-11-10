<?php

use App\Models\Barangay;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function selectableAudience(): array
{
    $a = Barangay::create(['name' => 'Alibagu']);
    $b = Barangay::create(['name' => 'Baligatan']);
    $responders = Category::create(['name' => 'Responders']);
    $residents = Category::create(['name' => 'Residents']);
    $contacts = [];
    foreach ([[$a, $responders, true], [$b, $responders, true], [$a, $residents, true], [$a, $responders, false]] as $index => [$barangay, $category, $active]) {
        $contacts[] = Contact::create(['first_name' => 'Recipient', 'last_name' => (string) $index, 'phone_number' => '0917123456'.$index, 'barangay_id' => $barangay->id, 'category_id' => $category->id, 'is_active' => $active]);
    }

    return [$contacts, $a, $responders];
}

beforeEach(fn () => config(['operations.sms_provider' => 'itexmo']));

test('SMS audience modes select exactly the intended active contacts', function (string $mode, array $indices) {
    Queue::fake();
    config(['operations.itexmo.dry_run' => true]);
    [$contacts, $barangay, $category] = selectableAudience();
    $payload = ['request_id' => (string) Str::uuid(), 'message' => 'Test notice', 'audience_mode' => $mode];
    if (in_array($mode, ['single', 'multiple'])) {
        $payload['selected_contact_ids'] = collect($indices)->map(fn ($index) => $contacts[$index]->id)->all();
    }
    if ($mode === 'barangay') {
        $payload['barangay_id'] = $barangay->id;
    }
    if ($mode === 'category') {
        $payload['category_id'] = $category->id;
    }
    $this->actingAs(User::factory()->create())->post('/menu/textblast', $payload)->assertSessionHasNoErrors();
    $broadcast = Broadcast::sole();
    expect($broadcast->recipient_count)->toBe(count($indices));
    expect($broadcast->recipients()->orderBy('contact_id')->pluck('contact_id')->all())->toBe(collect($indices)->map(fn ($index) => $contacts[$index]->id)->sort()->values()->all());
})->with([['single', [0]], ['multiple', [0, 2]], ['barangay', [0, 2]], ['category', [0, 1]], ['all', [0, 1, 2]]]);

test('SMS individual selection rejects empty duplicate nonexistent and oversized single selections', function (string $case) {
    [$contacts] = selectableAudience();
    $payload = ['request_id' => (string) Str::uuid(), 'message' => 'Test notice', 'audience_mode' => $case === 'single' ? 'single' : 'multiple', 'selected_contact_ids' => match ($case) {
        'empty' => [], 'duplicate' => [$contacts[0]->id, $contacts[0]->id], 'nonexistent' => [99999], 'single' => [$contacts[0]->id, $contacts[1]->id],
    }];
    $response = $this->actingAs(User::factory()->create())->post('/menu/textblast', $payload);
    $response->assertSessionHasErrors();
    $this->assertDatabaseCount('broadcasts', 0);
})->with(['empty', 'duplicate', 'nonexistent', 'single']);

test('inactive contacts cannot be sent an individual SMS', function () {
    [$contacts] = selectableAudience();
    $this->actingAs(User::factory()->create())->post('/menu/textblast', ['request_id' => (string) Str::uuid(), 'message' => 'Test notice', 'audience_mode' => 'single', 'selected_contact_ids' => [$contacts[3]->id]])->assertSessionHasErrors('message');
    $this->assertDatabaseCount('broadcasts', 0);
});
