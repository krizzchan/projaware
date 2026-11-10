<?php

use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\SmsRecipient;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createReportRecipient(string $status): SmsRecipient
{
    $broadcast = Broadcast::create(['request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('b', 64), 'title' => 'Flood advisory', 'message' => 'Proceed to the designated evacuation center.', 'target' => 'Citywide', 'recipient_count' => 1, 'is_test' => $status === 'simulated']);

    return $broadcast->recipients()->create(['phone_number' => '09171234567', 'status' => $status, 'error' => in_array($status, ['failed', 'rejected']) ? 'Gateway rejected the request.' : null, 'processed_at' => in_array($status, ['queued', 'processing']) ? null : now()]);
}

test('SMS report totals distinguish sent failed pending and test results', function () {
    foreach (['accepted', 'failed', 'rejected', 'queued', 'processing', 'unknown', 'simulated', 'skipped'] as $status) {
        createReportRecipient($status);
    }
    $this->actingAs(User::factory()->create())->get('/reports')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('menu/reports')->where('smsSummary', ['sent' => 1, 'failed' => 2, 'pending' => 2, 'unknown' => 1, 'simulated' => 1, 'skipped' => 1, 'cancelled' => 0])
        ->has('smsRecipients.data', 8)->where('smsRecipients.data.0.phone', '*******4567')
        ->where('smsRecipients.data.0.contact_name', 'Removed contact')->missing('smsRecipients.data.0.phone_number'));
});

test('SMS report filters return only the selected outcomes', function (string $filter, array $statuses) {
    foreach (['accepted', 'failed', 'rejected', 'queued', 'processing', 'unknown', 'simulated', 'skipped'] as $status) {
        createReportRecipient($status);
    }
    $this->actingAs(User::factory()->create())->get('/reports?sms_status='.$filter)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('smsStatus', $filter)->has('smsRecipients.data', count($statuses))
        ->where('smsRecipients.data', fn ($rows) => collect($rows)->pluck('status')->sort()->values()->all() === collect($statuses)->sort()->values()->all()));
})->with([
    ['sent', ['accepted']], ['failed', ['failed', 'rejected']], ['pending', ['queued', 'processing']],
    ['unknown', ['unknown']], ['simulated', ['simulated']], ['skipped', ['skipped']],
]);

test('invalid SMS report filters are rejected', function () {
    $this->actingAs(User::factory()->create())->get('/reports?sms_status=invalid')->assertSessionHasErrors('sms_status');
});

test('SMS recipient pagination preserves the status filter', function () {
    for ($index = 0; $index < 21; $index++) {
        createReportRecipient('failed');
    }
    $this->actingAs(User::factory()->create())->get('/reports?sms_status=failed')->assertInertia(fn (Assert $page) => $page
        ->has('smsRecipients.data', 20)->where('smsRecipients.next_page_url', fn ($url) => str_contains($url, 'sms_status=failed') && str_contains($url, 'sms_page=2')));
});

test('Excel SMS report exports every matching row as safe text', function () {
    createReportRecipient('accepted');
    for ($index = 0; $index < 21; $index++) {
        $recipient = createReportRecipient('failed');
        $recipient->broadcast->update(['message' => '=HYPERLINK("https://example.com","click") & <alert>']);
    }
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/reports/sms/export?sms_status=failed')
        ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $archive = new PharData($path);
        expect(isset($archive['[Content_Types].xml']))->toBeTrue();
        expect(isset($archive['xl/_rels/workbook.xml.rels']))->toBeTrue();
        $xml = simplexml_load_string($archive['xl/worksheets/sheet1.xml']->getContent());
        expect($xml)->not->toBeFalse();
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect(count($xml->xpath('//s:row')))->toBe(22);
        expect($xml->xpath('//s:f'))->toBe([]);
        expect((string) $xml->xpath('//s:row[@r="2"]/s:c[@r="E2"]/s:is/s:t')[0])->toBe('=HYPERLINK("https://example.com","click") & <alert>');
        expect((string) $xml->xpath('//s:row[@r="2"]/s:c[@r="B2"]/s:is/s:t')[0])->toBe('*******4567');
        expect($archive['xl/worksheets/sheet1.xml']->getContent())->not->toContain('Sent (provider accepted)');
        expect(AuditLog::where('action', 'sms.report_exported')->exists())->toBeTrue();
    } finally {
        unset($archive);
        unlink($path);
    }
});

test('Excel SMS report requires active administrator access', function () {
    $this->get('/reports/sms/export')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))->get('/reports/sms/export')->assertForbidden();
});

test('staff may view reports but cannot download them even with a direct export URL', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/reports')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.administer', false));
    $this->get('/reports/sms/export?sms_status=all&date_from=2026-10-01')->assertForbidden();
    expect(AuditLog::where('action', 'sms.report_exported')->exists())->toBeFalse();
});

test('empty Excel report still has a valid header row', function () {
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/reports/sms/export')->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $archive = new PharData($path);
        $xml = simplexml_load_string($archive['xl/worksheets/sheet1.xml']->getContent());
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect(count($xml->xpath('//s:row')))->toBe(1);
    } finally {
        unset($archive);
        unlink($path);
    }
});

test('SMS report workbook has compatible ZIP headers and a valid frozen workbook view', function (int $contacts) {
    for ($index = 0; $index < $contacts; $index++) {
        createReportRecipient('accepted');
    }
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/reports/sms/export')->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $data = file_get_contents($path);
        $end = strrpos($data, "PK\x05\x06");
        $entries = unpack('ventries/Vsize/Voffset', substr($data, $end + 10, 10));
        $position = $entries['offset'];
        expect($entries['entries'])->toBe(5);
        for ($index = 0; $index < $entries['entries']; $index++) {
            expect(substr($data, $position, 4))->toBe("PK\x01\x02");
            expect(unpack('vversion', substr($data, $position + 6, 2))['version'])->toBe(20);
            $local = unpack('Voffset', substr($data, $position + 42, 4))['offset'];
            expect(unpack('vversion', substr($data, $local + 4, 2))['version'])->toBe(20);
            $lengths = unpack('vname/vextra/vcomment', substr($data, $position + 28, 6));
            $position += 46 + array_sum($lengths);
        }
        $archive = new PharData($path);
        $workbook = simplexml_load_string($archive['xl/workbook.xml']->getContent());
        $workbook->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect($workbook->xpath('/s:workbook/s:bookViews/s:workbookView'))->toHaveCount(1);
        $sheet = simplexml_load_string($archive['xl/worksheets/sheet1.xml']->getContent());
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect((string) $sheet->xpath('/s:worksheet/s:sheetViews/s:sheetView')[0]['workbookViewId'])->toBe('0');
        expect((string) $sheet->xpath('/s:worksheet/s:sheetViews/s:sheetView/s:pane')[0]['state'])->toBe('frozen');
        expect($sheet->xpath('/s:worksheet/s:sheetData/s:row'))->toHaveCount($contacts + 1);
    } finally {
        unset($archive);
        unlink($path);
    }
})->with([0, 201]);
