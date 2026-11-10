<?php

use App\Jobs\SendTextBlastJob;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function operationsAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => 'admin'])->save();

    return $user;
}

beforeEach(fn () => config(['operations.sms_provider' => 'itexmo']));

test('staff can view contacts but cannot download CSV or Excel templates', function (string $format) {
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/menu/contacts')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('permissions.administer', false));
    $this->get('/menu/contacts/template?format='.$format)->assertForbidden();
})->with(['csv', 'xlsx']);

test('disabled administrators cannot download contact templates', function (string $format) {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))
        ->get('/menu/contacts/template?format='.$format)->assertForbidden();
})->with(['csv', 'xlsx']);

function operationsContact(array $attributes = []): Contact
{
    $category = Category::create(['name' => 'Responders '.Str::uuid()]);
    $barangay = Barangay::create(['name' => 'Sector '.Str::uuid()]);

    return Contact::create([...['first_name' => 'Ana', 'last_name' => 'Cruz', 'phone_number' => '09171234567', 'category_id' => $category->id, 'barangay_id' => $barangay->id, 'is_active' => true], ...$attributes]);
}

test('guests cannot read operational pages', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/menu/contacts', '/menu/textblast', '/menu/quick-advisory', '/barangays', '/hotlines', '/reports', '/users']);

test('disabled accounts cannot log in or use an existing session', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_active' => false])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->actingAs($user)->get('/dashboard')->assertForbidden();
});

test('staff cannot perform administrator actions', function (string $method, string $path) {
    $user = User::factory()->create();

    $this->actingAs($user)->{$method}($path, [])->assertForbidden();
    $this->assertDatabaseCount('audit_logs', 0);
})->with([['post', '/menu/categories'], ['post', '/menu/contacts/import'], ['post', '/barangays'], ['post', '/hotlines'], ['get', '/users'], ['post', '/users']]);

test('administrator can visit every operations module without exposing SMS credentials', function (string $path, string $component) {
    config(['operations.itexmo.password' => 'Private-provider-secret']);
    $user = operationsAdmin();

    $this->actingAs($user)->get($path)->assertInertia(fn (AssertableInertia $page) => $page->component($component)->where('permissions.administer', true))->assertDontSee('Private-provider-secret');
})->with([['/menu/contacts', 'menu/contact'], ['/menu/textblast', 'menu/textblast'], ['/menu/quick-advisory', 'menu/quickadvisory'], ['/menu/categories', 'menu/categories'], ['/barangays', 'menu/directory'], ['/hotlines', 'menu/directory'], ['/reports', 'menu/reports'], ['/users', 'menu/users']]);

test('contact creation normalizes a mobile number and ignores privilege fields', function () {
    $user = User::factory()->create();
    $existing = operationsContact();
    $payload = ['first_name' => 'Ben', 'last_name' => 'Reyes', 'phone_number' => '+63 917 123 4568', 'category_id' => $existing->category_id, 'barangay_id' => $existing->barangay_id, 'is_active' => true, 'role' => 'admin'];

    $this->actingAs($user)->post('/menu/contacts', $payload)->assertSessionHasNoErrors()->assertRedirect(route('menu.contacts.index'));
    $this->assertDatabaseHas('contacts', ['phone_number' => '09171234568']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'contact.created', 'user_id' => $user->id]);
    expect($user->fresh()->role)->toBe('staff');
});

test('invalid or duplicate contact data creates no record', function (string $phone, int $categoryId) {
    $user = User::factory()->create();
    $contact = operationsContact();
    $payload = ['first_name' => 'Ben', 'last_name' => 'Reyes', 'phone_number' => $phone, 'category_id' => $categoryId ?: $contact->category_id, 'barangay_id' => $contact->barangay_id, 'is_active' => true];

    $this->actingAs($user)->post('/menu/contacts', $payload)->assertSessionHasErrors();
    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseCount('audit_logs', 0);
})->with([['09171234567', 0], ['javascript:alert(1)', 0], ['09171234568', 999]]);

test('a category or barangay with contacts cannot be deleted', function (string $resource) {
    $user = operationsAdmin();
    $contact = operationsContact();
    $id = $resource === 'categories' ? $contact->category_id : $contact->barangay_id;
    $path = $resource === 'categories' ? '/menu/categories/' : '/barangays/';

    $this->actingAs($user)->delete($path.$id)->assertSessionHasErrors('delete');
    $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    $this->assertDatabaseHas($resource, ['id' => $id]);
})->with(['categories', 'barangays']);

test('CSV import is atomic when a later row is invalid', function () {
    $user = operationsAdmin();
    $contact = operationsContact();
    $csv = "first_name,middle_initial,last_name,office,position,phone_number,category_id,barangay_id\nBen,,Reyes,,,09171234568,{$contact->category_id},{$contact->barangay_id}\nInvalid,,Person,,,not-a-number,{$contact->category_id},{$contact->barangay_id}\n";

    $this->actingAs($user)->post('/menu/contacts/import', ['csv_file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])->assertSessionHasErrors('csv_file');
    $this->assertDatabaseCount('contacts', 1);
});

test('valid CSV import records contacts and audit information', function () {
    $user = operationsAdmin();
    $contact = operationsContact();
    $csv = "first_name,middle_initial,last_name,office,position,phone_number,category_id,barangay_id\nBen,,Reyes,,,09171234568,{$contact->category_id},{$contact->barangay_id}\n";

    $this->actingAs($user)->post('/menu/contacts/import', ['csv_file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('contacts', ['phone_number' => '09171234568']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'contacts.imported']);
});

test('duplicate SMS submission creates one broadcast and one queued job', function () {
    Queue::fake([SendTextBlastJob::class]);
    Http::preventStrayRequests();
    config(['operations.itexmo.dry_run' => true]);
    $user = User::factory()->create();
    operationsContact();
    $payload = ['request_id' => (string) Str::uuid(), 'message' => 'Test emergency notice', 'barangay_id' => 'all', 'category_id' => 'all'];

    $this->actingAs($user)->post('/menu/textblast', $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/menu/textblast', $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('broadcasts', 1);
    $this->assertDatabaseCount('sms_recipients', 1);
    Queue::assertPushed(SendTextBlastJob::class, 1);
    Http::assertNothingSent();
    expect(DB::table('broadcasts')->value('message'))->not->toBe('Test emergency notice');
    expect(DB::table('sms_recipients')->value('phone_number'))->not->toBe('09171234567');
});

test('SMS submission rejects an empty audience and missing live configuration', function (bool $testMode, bool $hasContact) {
    Queue::fake([SendTextBlastJob::class]);
    config(['operations.itexmo.dry_run' => $testMode, 'operations.itexmo.email' => null, 'operations.itexmo.password' => null, 'operations.itexmo.api_code' => null]);
    $user = User::factory()->create();
    if ($hasContact) {
        operationsContact();
    }

    $this->actingAs($user)->post('/menu/textblast', ['request_id' => (string) Str::uuid(), 'message' => 'Test notice'])->assertSessionHasErrors('message');
    $this->assertDatabaseCount('broadcasts', 0);
    Queue::assertNothingPushed();
})->with([[true, false], [false, true]]);

test('SMS submissions are rate limited', function () {
    Queue::fake([SendTextBlastJob::class]);
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/menu/textblast', [])->assertRedirect();
    $this->post('/menu/textblast', [])->assertRedirect();
    $this->post('/menu/textblast', [])->assertTooManyRequests();
});

test('advisories reject unconnected channels and empty specific audiences', function (array $selected, bool $social) {
    Queue::fake([SendTextBlastJob::class]);
    $user = User::factory()->create();
    $contact = operationsContact();
    $payload = ['request_id' => (string) Str::uuid(), 'title' => 'Test advisory', 'message' => 'Test notice', 'severity' => 'warning', 'target_scope' => 'specific', 'selected_barangays' => $selected ? [$contact->barangay_id] : [], 'channels' => ['sms' => true, 'siren' => false, 'social' => $social, 'radio' => false]];

    $this->actingAs($user)->post('/menu/quick-advisory', $payload)->assertSessionHasErrors();
    $this->assertDatabaseCount('broadcasts', 0);
    Queue::assertNothingPushed();
})->with([[[], false], [[1], true]]);

test('staff can record incidents but cannot override the owner or initial status', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/reports', ['title' => 'Flooded road', 'description' => 'Reported standing water.', 'severity' => 'warning', 'user_id' => 999, 'status' => 'resolved'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('incident_reports', ['user_id' => $user->id, 'status' => 'open']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'incident.created']);
});

test('last administrator cannot delete their account or remove their own access', function () {
    $user = operationsAdmin();

    $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
    $this->actingAs($user)->patch('/users/'.$user->id, ['role' => 'staff', 'is_active' => false, 'current_password' => 'password'])->assertSessionHasErrors('role');
    expect($user->fresh()->role)->toBe('admin');
    expect($user->fresh()->is_active)->toBeTrue();
});

test('administrator access changes require a current password', function () {
    $admin = operationsAdmin();
    $staff = User::factory()->create();

    $this->actingAs($admin)->patch('/users/'.$staff->id, ['role' => 'admin', 'is_active' => true, 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    expect($staff->fresh()->role)->toBe('staff');
});

test('administrator can disable staff access and it is denied on the next request', function () {
    $admin = operationsAdmin();
    $staff = User::factory()->create();

    $this->actingAs($admin)->patch('/users/'.$staff->id, ['role' => 'staff', 'is_active' => false, 'current_password' => 'password'])->assertSessionHasNoErrors();
    $this->actingAs($staff->fresh())->get('/menu/contacts')->assertForbidden();
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.access_updated']);
});

test('operational responses prevent caching and framing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
});

test('administrator can create a strong-password staff account without leaking credentials', function () {
    $admin = operationsAdmin();
    $password = 'Strong-Staff-Pass123!';

    $this->actingAs($admin)->post('/users', ['name' => 'Response Staff', 'email' => 'staff@example.com', 'role' => 'staff', 'password' => $password, 'password_confirmation' => $password, 'current_password' => 'password'])->assertSessionHasNoErrors();
    $staff = User::where('email', 'staff@example.com')->firstOrFail();
    expect($staff->role)->toBe('staff');
    expect(Hash::check($password, $staff->password))->toBeTrue();
    expect($staff->toArray())->not->toHaveKey('password');
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $staff->id]);
});

test('weak passwords cannot be used for new staff accounts', function () {
    $admin = operationsAdmin();

    $this->actingAs($admin)->post('/users', ['name' => 'Response Staff', 'email' => 'staff@example.com', 'role' => 'staff', 'password' => 'password', 'password_confirmation' => 'password', 'current_password' => 'password'])->assertSessionHasErrors('password');
    $this->assertDatabaseCount('users', 1);
});

test('specific barangay advisory selects only active contacts in the chosen barangay', function () {
    Queue::fake([SendTextBlastJob::class]);
    $user = User::factory()->create();
    $selected = operationsContact();
    operationsContact(['phone_number' => '09171234568']);
    operationsContact(['phone_number' => '09171234569', 'barangay_id' => $selected->barangay_id, 'is_active' => false]);

    $this->actingAs($user)->post('/menu/quick-advisory', ['request_id' => (string) Str::uuid(), 'title' => 'Test advisory', 'message' => 'Test notice', 'severity' => 'warning', 'target_scope' => 'specific', 'selected_barangays' => [$selected->barangay_id], 'channels' => ['sms' => true, 'siren' => false, 'social' => false, 'radio' => false]])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('broadcasts', ['recipient_count' => 1, 'title' => 'Test advisory']);
    $this->assertDatabaseHas('sms_recipients', ['contact_id' => $selected->id]);
    $this->assertDatabaseCount('sms_recipients', 1);
    Queue::assertPushed(SendTextBlastJob::class, 1);
});

test('directory filters cannot change SQL query structure', function () {
    $user = User::factory()->create();
    operationsContact();

    $this->actingAs($user)->get('/menu/contacts?search='.urlencode("' OR 1=1 --"))->assertInertia(fn (AssertableInertia $page) => $page->has('contacts.data', 0));
});

test('first administrator setup is private and refuses to run once an administrator exists', function () {
    operationsAdmin();

    $this->artisan('operations:create-admin')->expectsOutput('An administrator already exists. Use Account Access to manage additional accounts.')->assertFailed();
    $this->assertDatabaseCount('users', 1);
});

test('first administrator setup uses supplied credentials and a hashed password', function () {
    $this->artisan('operations:create-admin')->expectsQuestion('Administrator name', 'System Administrator')->expectsQuestion('Administrator email', 'admin@example.com')->expectsQuestion('Password (12+ characters, mixed case, number, symbol)', 'Strong-Admin-Pass123!')->expectsQuestion('Confirm password', 'Strong-Admin-Pass123!')->expectsOutput('Administrator created. Sign in and open Account Access to manage staff.')->assertSuccessful();
    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    expect($admin->role)->toBe('admin');
    expect(Hash::check('Strong-Admin-Pass123!', $admin->password))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.initial_admin_created_cli']);
});

test('contacts can be created and updated with Not applicable barangay', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'External agencies']);
    $payload = ['first_name' => 'Agency', 'last_name' => 'Contact', 'office' => 'External office', 'phone_number' => '09171234568', 'category_id' => $category->id, 'barangay_id' => '', 'is_active' => true];
    $this->actingAs($user)->post('/menu/contacts', $payload)->assertSessionHasNoErrors();
    $contact = Contact::sole();
    expect($contact->barangay_id)->toBeNull();
    $barangay = Barangay::create(['name' => 'Alibagu']);
    $this->put('/menu/contacts/'.$contact->id, [...$payload, 'barangay_id' => $barangay->id])->assertSessionHasNoErrors();
    expect($contact->fresh()->barangay_id)->toBe($barangay->id);
    $this->put('/menu/contacts/'.$contact->id, $payload)->assertSessionHasNoErrors();
    expect($contact->fresh()->barangay_id)->toBeNull();
    $this->get('/menu/contacts')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('contacts.data.0.barangay', null)->where('directoryStats.barangays', 0));
    $this->put('/menu/contacts/'.$contact->id, [...$payload, 'barangay_id' => 99999])->assertSessionHasErrors('barangay_id');
});

test('CSV contacts can use a blank barangay for external agencies', function () {
    $category = Category::create(['name' => 'External agencies']);
    $csv = "first_name,middle_initial,last_name,office,position,phone_number,category_id,barangay_id\nAgency,,Contact,External office,,09171234568,{$category->id},\n";
    $this->actingAs(operationsAdmin())->post('/menu/contacts/import', ['csv_file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])->assertSessionHasNoErrors();
    expect(Contact::sole()->barangay_id)->toBeNull();
});

test('external contacts are included by category but excluded from a barangay SMS audience', function () {
    Queue::fake();
    config(['operations.itexmo.dry_run' => true]);
    $user = User::factory()->create();
    $contact = operationsContact(['barangay_id' => null]);
    $barangay = Barangay::sole();
    $this->actingAs($user)->post('/menu/textblast', ['request_id' => (string) Str::uuid(), 'message' => 'Test only', 'audience_mode' => 'category', 'category_id' => $contact->category_id])->assertSessionHasNoErrors();
    expect(\App\Models\Broadcast::sole()->recipient_count)->toBe(1);
    $this->post('/menu/textblast', ['request_id' => (string) Str::uuid(), 'message' => 'Test only', 'audience_mode' => 'barangay', 'barangay_id' => $barangay->id])->assertSessionHasErrors();
    expect(\App\Models\Broadcast::count())->toBe(1);
});

test('contact CSV template has Excel encoding and exact import columns', function () {
    $response = $this->actingAs(operationsAdmin())->get('/menu/contacts/template')->assertOk()->assertDownload('cdrrmo_contacts_template.csv');
    $csv = $response->streamedContent();
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");
    expect(substr($csv, -2))->toBe("\r\n");
    expect(str_getcsv(rtrim(substr($csv, 3), "\r\n"), ',', '"', ''))->toBe(['first_name', 'middle_initial', 'last_name', 'office', 'position', 'phone_number', 'category_id', 'barangay_id']);
});

test('Excel contact template contains saved dropdown lists and text formatted phone cells', function () {
    Category::create(['name' => 'DepEd & Schools']);
    Barangay::create(['name' => 'Alibagu']);
    $response = $this->actingAs(operationsAdmin())->get('/menu/contacts/template?format=xlsx')->assertOk()->assertDownload('Project-AWARE-Contacts-Template.xlsx');
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $archive = new \PharData($path);
        $sheet = new \SimpleXMLElement($archive['xl/worksheets/sheet1.xml']->getContent());
        expect((string) $sheet->sheetViews->sheetView->pane['state'])->toBe('frozen');
        expect((string) $sheet->dataValidations['count'])->toBe('2');
        expect((string) $sheet->dataValidations->dataValidation[0]['sqref'])->toBe('G2:G1001');
        expect((string) $sheet->dataValidations->dataValidation[1]['sqref'])->toBe('H2:H1001');
        expect((string) $sheet->sheetData->row[1]->c[5]['s'])->toBe('2');
        expect($archive['xl/worksheets/sheet2.xml']->getContent())->toContain('DepEd &amp; Schools', 'Alibagu', 'Not applicable');
        $book = new \SimpleXMLElement($archive['xl/workbook.xml']->getContent());
        expect((string) $book->definedNames->definedName[0])->toStartWith("'Lists and instructions'!");
        $styles = new \SimpleXMLElement($archive['xl/styles.xml']->getContent());
        expect((string) $styles->cellXfs->xf[2]['numFmtId'])->toBe('49');
        unset($archive);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('CSV saved from Excel dropdowns imports directory IDs and ignores blank rows', function () {
    $category = Category::create(['name' => 'DepEd - SDO City of Ilagan']);
    $barangay = Barangay::create(['name' => 'Alibagu']);
    $csv = "first_name,middle_initial,last_name,office,position,phone_number,category_id,barangay_id\nAna,,Cruz,School,Teacher,09171234567,{$category->id} - {$category->name},{$barangay->id} - {$barangay->name}\nBen,,Reyes,DILG,Coordinator,09171234568,{$category->id} - {$category->name},Not applicable\n,,,,,,,\n";
    $this->actingAs(operationsAdmin())->post('/menu/contacts/import', ['csv_file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('contacts', 2);
    $this->assertDatabaseHas('contacts', ['first_name' => 'Ana', 'category_id' => $category->id, 'barangay_id' => $barangay->id]);
    $this->assertDatabaseHas('contacts', ['first_name' => 'Ben', 'barangay_id' => null]);
});
