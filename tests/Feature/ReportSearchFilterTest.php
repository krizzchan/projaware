<?php

use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function searchReportFixture(string $first, string $last, string $title, string $status = 'accepted'): Broadcast
{
    $contact = Contact::create(['first_name' => $first, 'last_name' => $last, 'phone_number' => '0917'.str_pad((string) (Contact::count() + 1), 7, '0', STR_PAD_LEFT), 'category_id' => Category::firstOrCreate(['name' => 'Responders'])->id, 'is_active' => true]);
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'title' => $title, 'message' => 'Private encrypted body', 'target' => 'Citywide', 'recipient_count' => 1, 'is_test' => false]);
    $broadcast->forceFill(['created_at' => '2026-10-18 02:00:00'])->save();
    $recipient = $broadcast->recipients()->create(['contact_id' => $contact->id, 'phone_number' => $contact->phone_number, 'status' => $status]);
    $recipient->forceFill(['created_at' => '2026-10-18 02:00:00'])->save();

    return $broadcast;
}

test('recipient and message searches combine with date and status filters', function () {
    searchReportFixture('Ana', 'Cruz', 'Flood advisory');
    searchReportFixture('Ana', 'Cruz', 'Weather update');
    searchReportFixture('Ben', 'Cruz', 'Flood advisory');
    searchReportFixture('Ana', 'Cruz', 'Flood advisory', 'failed');
    $this->actingAs(User::factory()->create())->get('/reports?recipient_search=Ana+Cruz&message_search=Flood&date_from=2026-10-18&date_to=2026-10-18&sms_status=sent')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reportSearch', ['recipient_search' => 'Ana Cruz', 'message_search' => 'Flood'])
            ->has('smsRecipients.data', 1)->where('smsRecipients.data.0.contact_name', 'Ana Cruz')
            ->where('smsSummary.sent', 1)->where('smsSummary.failed', 1)->has('broadcasts.data', 2));
});

test('report searches allow partial names and return no unrelated records', function (string $parameters, int $count) {
    searchReportFixture('Ana', 'Cruz', 'Flood advisory');
    searchReportFixture('Ben', 'Santos', 'Weather update');
    $this->actingAs(User::factory()->create())->get('/reports?'.$parameters)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('smsRecipients.data', $count));
})->with([['recipient_search=Cru', 1], ['message_search=Weather', 1], ['recipient_search=Missing', 0], ['message_search=Missing', 0]]);

test('search filters remain in pagination links', function () {
    for ($index = 0; $index < 21; $index++) {
        searchReportFixture('Ana', 'Cruz', 'Flood advisory');
    }
    $this->actingAs(User::factory()->create())->get('/reports?recipient_search=Ana&message_search=Flood')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('smsRecipients.next_page_url', fn ($url) => str_contains($url, 'recipient_search=Ana') && str_contains($url, 'message_search=Flood')));
});

test('Excel report uses the applied recipient and message filters without logging search text', function () {
    searchReportFixture('Ana', 'Cruz', 'Flood advisory');
    searchReportFixture('Ben', 'Santos', 'Flood advisory');
    searchReportFixture('Ana', 'Cruz', 'Weather update');
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/reports/sms/export?recipient_search=Ana+Cruz&message_search=Flood')->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $archive = new PharData($path);
        $xml = simplexml_load_string($archive['xl/worksheets/sheet1.xml']->getContent());
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect($xml->xpath('//s:row'))->toHaveCount(2);
        expect((string) $xml->xpath('//s:row[@r="2"]/s:c[@r="A2"]/s:is/s:t')[0])->toBe('Ana Cruz');
        expect((string) $xml->xpath('//s:row[@r="2"]/s:c[@r="D2"]/s:is/s:t')[0])->toBe('Flood advisory');
        expect(AuditLog::where('action', 'sms.report_exported')->first()->metadata)->not->toHaveKeys(['recipient_search', 'message_search']);
    } finally {
        unset($archive);
        unlink($path);
    }
});

test('invalid search inputs are rejected for reports and export', function (string $path, string $field) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path.'?'.http_build_query([$field => str_repeat('a', 151)]))->assertSessionHasErrors($field);
})->with(['/reports', '/reports/sms/export'])->with(['recipient_search', 'message_search']);
