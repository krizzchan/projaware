<?php

use App\Models\Barangay;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function audienceReportContact(string $category = 'Responders', ?string $barangay = 'Alibagu', string $status = 'accepted'): Contact
{
    $contact = Contact::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'phone_number' => '0917'.str_pad((string) (Contact::count() + 1), 7, '0', STR_PAD_LEFT), 'category_id' => Category::firstOrCreate(['name' => $category])->id, 'barangay_id' => $barangay === null ? null : Barangay::firstOrCreate(['name' => $barangay])->id, 'is_active' => true]);
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => 'Flood advisory', 'message' => 'Safe fixture', 'target' => 'Directory', 'recipient_count' => 1, 'is_test' => false]);
    $broadcast->recipients()->create(['contact_id' => $contact->id, 'phone_number' => $contact->phone_number, 'status' => $status]);

    return $contact;
}

test('category and barangay filters restrict recipients totals and broadcast history', function (string $mode, int $count) {
    $match = audienceReportContact();
    audienceReportContact('Responders', 'Other barangay', 'failed');
    audienceReportContact('Other category', 'Alibagu');
    audienceReportContact('Other category', null);
    $filters = match ($mode) {
        'category' => ['category_id' => $match->category_id],
        'barangay' => ['barangay_id' => $match->barangay_id],
        'both' => ['category_id' => $match->category_id, 'barangay_id' => $match->barangay_id],
        'none' => ['barangay_id' => 'none'],
    };
    $this->actingAs(User::factory()->create())->get('/reports?'.http_build_query($filters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 2)->has('barangays', 2)->has('smsRecipients.data', $count)->has('broadcasts.data', $count)
            ->where('smsSummary.sent', $mode === 'category' ? 1 : $count)
            ->where('smsSummary.failed', $mode === 'category' ? 1 : 0));
})->with([['category', 2], ['barangay', 2], ['both', 1], ['none', 1]]);

test('report and Excel show category and barangay names for matching recipients', function () {
    $match = audienceReportContact();
    audienceReportContact('Other category', 'Alibagu');
    $filters = ['category_id' => $match->category_id, 'barangay_id' => $match->barangay_id, 'recipient_search' => 'Ana', 'message_search' => 'Flood', 'sms_status' => 'sent'];
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/reports?'.http_build_query($filters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('smsRecipients.data.0.category', 'Responders')->where('smsRecipients.data.0.barangay', 'Alibagu')
            ->where('reportAudience', ['category_id' => (string) $match->category_id, 'barangay_id' => (string) $match->barangay_id]));
    $response = $this->get('/reports/sms/export?'.http_build_query($filters))->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $archive = new PharData($path);
        $xml = simplexml_load_string($archive['xl/worksheets/sheet1.xml']->getContent());
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect($xml->xpath('//s:row'))->toHaveCount(2);
        expect((string) $xml->xpath('//s:c[@r="K1"]/s:is/s:t')[0])->toBe('Category');
        expect((string) $xml->xpath('//s:c[@r="L1"]/s:is/s:t')[0])->toBe('Barangay');
        expect((string) $xml->xpath('//s:c[@r="K2"]/s:is/s:t')[0])->toBe('Responders');
        expect((string) $xml->xpath('//s:c[@r="L2"]/s:is/s:t')[0])->toBe('Alibagu');
        expect((string) $xml->xpath('//s:autoFilter')[0]['ref'])->toBe('A1:L2');
    } finally {
        unset($archive);
        unlink($path);
    }
});

test('category and barangay filters remain in pagination links', function () {
    for ($index = 0; $index < 21; $index++) {
        $contact = audienceReportContact();
    }
    $this->actingAs(User::factory()->create())->get('/reports?category_id='.$contact->category_id.'&barangay_id='.$contact->barangay_id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('smsRecipients.next_page_url', fn ($url) => str_contains($url, 'category_id='.$contact->category_id) && str_contains($url, 'barangay_id='.$contact->barangay_id)));
});

test('invalid audience IDs are rejected for report viewing and downloading', function (string $path, string $field) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path.'?'.$field.'=invalid')->assertSessionHasErrors($field);
})->with(['/reports', '/reports/sms/export'])->with(['category_id', 'barangay_id']);
