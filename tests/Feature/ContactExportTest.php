<?php

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;

function exportContact(array $attributes = []): Contact
{
    return Contact::create([
        'first_name' => 'Ana', 'last_name' => 'Cruz', 'phone_number' => '09171234567',
        'category_id' => Category::firstOrCreate(['name' => 'Responders'])->id,
        'barangay_id' => Barangay::firstOrCreate(['name' => 'Alibagu'])->id,
        'is_active' => true, ...$attributes,
    ]);
}

function exportedContactRows(string $csv): array
{
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

test('administrator exports all contacts beyond the current page with readable directory names', function () {
    for ($index = 0; $index < 12; $index++) {
        exportContact(['phone_number' => '0917'.str_pad((string) $index, 7, '0', STR_PAD_LEFT)]);
    }
    exportContact(['first_name' => 'Inactive', 'phone_number' => '09179999999', 'barangay_id' => null, 'is_active' => false]);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/menu/contacts/export?page=2')
        ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");
    $rows = exportedContactRows($csv);
    expect($rows)->toHaveCount(14);
    expect($rows[1][8])->toBe('Responders');
    expect($rows[1][9])->toBe('Alibagu');
    expect($rows[13][9])->toBe('Not applicable');
    expect($rows[13][10])->toBe('No');
    expect(AuditLog::where('action', 'contacts.exported')->count())->toBe(1);
});

test('contact export applies search category and barangay filters together', function () {
    $match = exportContact(['office' => 'Response Office']);
    exportContact(['phone_number' => '09171234568', 'office' => 'Other office']);
    exportContact(['phone_number' => '09171234569', 'office' => 'Response Office', 'barangay_id' => Barangay::create(['name' => 'Other barangay'])->id]);
    exportContact(['phone_number' => '09171234570', 'office' => 'Response Office', 'category_id' => Category::create(['name' => 'Other category'])->id]);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/menu/contacts/export?'.http_build_query([
        'search' => 'Response', 'category_id' => $match->category_id, 'barangay_id' => $match->barangay_id,
    ]))->assertOk();
    $rows = exportedContactRows($response->streamedContent());
    expect($rows)->toHaveCount(2);
    expect($rows[1][5])->toBe("'09171234567");
});

test('contact CSV neutralizes spreadsheet formulas and preserves text phone numbers', function () {
    exportContact(['first_name' => '=1+1', 'office' => "  @SUM(1,2)", 'position' => '+123']);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/menu/contacts/export')->assertOk();
    $rows = exportedContactRows($response->streamedContent());
    expect($rows[1][0])->toBe("'=1+1");
    expect($rows[1][3])->toBe("'  @SUM(1,2)");
    expect($rows[1][4])->toBe("'+123");
    expect($rows[1][5])->toBe("'09171234567");
});

test('only active administrators may export contacts', function (string $role, bool $active) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]))->get('/menu/contacts/export')->assertForbidden();
    expect(AuditLog::where('action', 'contacts.exported')->exists())->toBeFalse();
})->with([['staff', true], ['admin', false]]);

test('contact export requires login and validates filters', function () {
    $this->get('/menu/contacts/export')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/menu/contacts/export?category_id=invalid')->assertSessionHasErrors('category_id');
});
