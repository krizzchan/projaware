<?php

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createDatedReport(string $createdAt, string $status = 'accepted'): Broadcast
{
    $broadcast = Broadcast::create([
        'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('b', 64),
        'title' => $createdAt, 'message' => 'Date-filter fixture', 'target' => 'Citywide',
        'recipient_count' => 1, 'is_test' => false,
    ]);
    $broadcast->forceFill(['created_at' => $createdAt])->save();
    $recipient = $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => $status]);
    $recipient->forceFill(['created_at' => $createdAt])->save();

    return $broadcast;
}

test('report dates include the entire Philippine day and filter totals and history', function () {
    foreach (['2026-10-17 15:59:59', '2026-10-17 16:00:00', '2026-10-18 15:59:59', '2026-10-18 16:00:00'] as $createdAt) {
        createDatedReport($createdAt);
    }
    $this->actingAs(User::factory()->create())->get('/reports?date_from=2026-10-18&date_to=2026-10-18')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportDates', ['date_from' => '2026-10-18', 'date_to' => '2026-10-18'])
            ->where('smsSummary.sent', 2)->has('smsRecipients.data', 2)->has('broadcasts.data', 2)
            ->where('smsRecipients.data.0.title', '2026-10-18 15:59:59')->where('smsRecipients.data.1.title', '2026-10-17 16:00:00'));
});

test('report dates support open bounds and compose with result filters', function (array $filters, int $count) {
    createDatedReport('2026-10-17 16:00:00');
    createDatedReport('2026-10-18 16:00:00', 'failed');
    $this->actingAs(User::factory()->create())->get('/reports?'.http_build_query($filters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('smsRecipients.data', $count));
})->with([
    [['date_from' => '2026-10-19'], 1],
    [['date_to' => '2026-10-18'], 1],
    [['date_from' => '2026-10-18', 'date_to' => '2026-10-19', 'sms_status' => 'failed'], 1],
    [['date_from' => '2026-10-20'], 0],
]);

test('invalid date ranges are rejected by reports and Excel export', function (string $path, array $filters, string $field) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path.'?'.http_build_query($filters))->assertSessionHasErrors($field);
})->with(['/reports', '/reports/sms/export'])->with([
    [['date_from' => '2026-02-30'], 'date_from'],
    [['date_to' => 'invalid'], 'date_to'],
    [['date_from' => '2026-10-19', 'date_to' => '2026-10-18'], 'date_to'],
]);

test('Excel export uses the same Philippine dates and selected outcome', function () {
    createDatedReport('2026-10-17 15:59:59', 'failed');
    createDatedReport('2026-10-17 16:00:00', 'failed');
    createDatedReport('2026-10-18 15:59:59');
    createDatedReport('2026-10-18 16:00:00', 'failed');
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/reports/sms/export?date_from=2026-10-18&date_to=2026-10-18&sms_status=failed')->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $archive = new PharData($path);
        $xml = simplexml_load_string($archive['xl/worksheets/sheet1.xml']->getContent());
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect(count($xml->xpath('//s:row')))->toBe(2);
        expect((string) $xml->xpath('//s:row[@r="2"]/s:c[@r="D2"]/s:is/s:t')[0])->toBe('2026-10-17 16:00:00');
    } finally {
        unset($archive);
        unlink($path);
    }
});

test('report pagination preserves date filters', function () {
    for ($index = 0; $index < 21; $index++) {
        createDatedReport('2026-10-17 16:00:00');
    }
    $this->actingAs(User::factory()->create())->get('/reports?date_from=2026-10-18&date_to=2026-10-18')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('smsRecipients.next_page_url', fn ($url) => str_contains($url, 'date_from=2026-10-18') && str_contains($url, 'date_to=2026-10-18')));
});
