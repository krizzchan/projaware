<?php

use App\Models\Barangay;
use App\Models\Category;
use App\Models\Contact;
use Database\Seeders\IlaganBarangaySeeder;

test('Ilagan barangay registration preserves existing records and contact links without duplicates', function () {
    $existing = Barangay::create(['name' => 'Alibagu']);
    $category = Category::create(['name' => 'Responders']);
    $contact = Contact::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'phone_number' => '09171234567', 'category_id' => $category->id, 'barangay_id' => $existing->id, 'is_active' => true]);
    $this->seed(IlaganBarangaySeeder::class);
    $this->seed(IlaganBarangaySeeder::class);
    $this->assertDatabaseCount('barangays', 91);
    expect(Barangay::where('name', 'Alibagu')->sole()->id)->toBe($existing->id);
    expect($contact->fresh()->barangay_id)->toBe($existing->id);
    foreach (['Osmeña', 'Cabeseria 6 & 24', 'Santa Maria', 'Centro - San Antonio', 'Allinguigan 3rd', 'Marana III'] as $name) {
        $this->assertDatabaseHas('barangays', ['name' => $name]);
    }
    $this->assertDatabaseCount('audit_logs', 1);
});
