<?php

use App\ContactExcelTemplate;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function contactWorkbook(string $mode = 'inline'): UploadedFile
{
    $category = Category::firstOrCreate(['name' => 'Responders']);
    $barangay = Barangay::firstOrCreate(['name' => 'Alibagu']);
    $columns = ['first_name', 'middle_initial', 'last_name', 'office', 'position', 'phone_number', 'category_id', 'barangay_id'];
    $response = app(ContactExcelTemplate::class)->download($columns);
    $path = $response->getFile()->getPathname();
    try {
        $archive = new PharData($path);
        $row = function (int $number, array $values): string {
            $xml = '<row r="'.$number.'">';
            foreach ($values as $index => $value) {
                $xml .= '<c r="'.chr(65 + $index).$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1, 'UTF-8').'</t></is></c>';
            }

            return $xml.'</row>';
        };
        $body = $row(1, $columns);
        if ($mode !== 'empty') {
            $body .= $row(2, ['Ana', '', 'Cruz', 'Office', '', '09171234567', $category->id.' - '.$category->name, 'Not applicable']);
        }
        if ($mode === 'shared') {
            $body = str_replace('<c r="A2" t="inlineStr"><is><t>Ana</t></is></c>', '<c r="A2" t="s"><v>0</v></c>', $body);
            $archive['xl/sharedStrings.xml'] = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><r><t>A</t></r><r><t>na</t></r></si></sst>';
        }
        if ($mode === 'numeric') {
            $body = str_replace('<c r="F2" t="inlineStr"><is><t>09171234567</t></is></c>', '<c r="F2"><v>9171234567</v></c>', $body);
        }
        if ($mode === 'barangay') {
            $body = str_replace('Not applicable', $barangay->id.' - '.$barangay->name, $body);
        }
        if ($mode === 'formula') {
            $body = str_replace('<c r="A2" t="inlineStr">', '<c r="A2" t="inlineStr"><f>1+1</f>', $body);
        }
        if ($mode === 'invalid-row') {
            $body .= $row(3, ['Bad', '', 'Row', '', '', 'invalid', (string) $category->id, '']);
        }
        if ($mode === 'headers') {
            $body = str_replace('first_name', 'wrong_header', $body);
        }
        if ($mode === 'too-many') {
            for ($number = 3; $number <= 1002; $number++) {
                $body .= $row($number, ['Ana', '', 'Cruz', '', '', '0917'.str_pad((string) $number, 7, '0', STR_PAD_LEFT), (string) $category->id, '']);
            }
        }
        if ($mode === 'compressed') {
            for ($number = 3; $number <= 1001; $number++) {
                $body .= $row($number, array_fill(0, 8, ''));
            }
        }
        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$body.'</sheetData></worksheet>';
        if ($mode === 'entity') {
            $sheet = '<!DOCTYPE worksheet [<!ENTITY secret SYSTEM "file:///does-not-exist">]>'.$sheet;
        }
        if ($mode === 'broken') {
            $sheet = '<invalid';
        }
        $archive['xl/worksheets/sheet1.xml'] = $sheet;
        if ($mode === 'compressed') {
            $archive->compressFiles(Phar::GZ);
        }
        if ($mode === 'macro') {
            $archive['xl/vbaProject.bin'] = 'not-a-real-macro';
        }
        if ($mode === 'missing-sheet') {
            $archive['xl/workbook.xml'] = str_replace('name="Contacts"', 'name="Other"', $archive['xl/workbook.xml']->getContent());
        }
        if ($mode === 'external-sheet') {
            $archive['xl/_rels/workbook.xml.rels'] = str_replace('Target="worksheets/sheet1.xml"', 'Target="https://example.com/sheet.xml" TargetMode="External"', $archive['xl/_rels/workbook.xml.rels']->getContent());
        }
        if ($mode === 'oversized-xml') {
            $archive['xl/sharedStrings.xml'] = str_repeat('x', 5 * 1024 * 1024 + 1);
            $archive->compressFiles(Phar::GZ);
        }
        unset($archive);

        return UploadedFile::fake()->createWithContent('contacts.xlsx', file_get_contents($path));
    } finally {
        unlink($path);
    }
}

test('downloaded Excel template has valid ZIP versions and matching workbook views', function () {
    Category::create(['name' => 'Responders']);
    $columns = ['first_name', 'middle_initial', 'last_name', 'office', 'position', 'phone_number', 'category_id', 'barangay_id'];
    $response = app(ContactExcelTemplate::class)->download($columns);
    $path = $response->getFile()->getPathname();
    try {
        $data = file_get_contents($path);
        $end = strrpos($data, "PK\x05\x06");
        $offset = unpack('Voffset', substr($data, $end + 16, 4))['offset'];
        $entries = unpack('vcount', substr($data, $end + 10, 2))['count'];
        expect($entries)->toBe(7);
        for ($index = 0; $index < $entries; $index++) {
            expect(substr($data, $offset, 4))->toBe("PK\x01\x02");
            expect(unpack('vversion', substr($data, $offset + 6, 2))['version'])->toBe(20);
            $local = unpack('Voffset', substr($data, $offset + 42, 4))['offset'];
            expect(unpack('vversion', substr($data, $local + 4, 2))['version'])->toBe(20);
            $lengths = unpack('vname/vextra/vcomment', substr($data, $offset + 28, 6));
            $offset += 46 + array_sum($lengths);
        }
        $archive = new PharData($path);
        $workbook = simplexml_load_string($archive['xl/workbook.xml']->getContent());
        $workbook->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        expect($workbook->xpath('/s:workbook/s:bookViews/s:workbookView'))->toHaveCount(1);
        expect((string) $workbook->xpath('/s:workbook/s:definedNames/s:definedName[@name="CategoryOptions"]')[0])->toBe("'Lists and instructions'!\$A\$2:\$A\$2");
    } finally {
        unset($archive);
        unlink($path);
    }
});

test('administrator imports a filled Excel template directly', function (string $mode) {
    Http::preventStrayRequests();
    $file = contactWorkbook($mode);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/menu/contacts/import', ['csv_file' => $file])
        ->assertSessionHasNoErrors()->assertRedirect(route('menu.contacts.index'));
    expect(Contact::count())->toBe(1);
    $contact = Contact::first();
    expect($contact->first_name)->toBe('Ana');
    expect($contact->phone_number)->toBe('09171234567');
    expect($contact->category->name)->toBe('Responders');
    expect($contact->barangay_id)->toBe($mode === 'barangay' ? Barangay::first()->id : null);
    expect(AuditLog::where('action', 'contacts.imported')->count())->toBe(1);
    Http::assertNothingSent();
})->with(['inline', 'shared', 'numeric', 'barangay', 'compressed']);

test('invalid or unsafe Excel imports never partially insert contacts', function (string $mode) {
    $file = contactWorkbook($mode);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/menu/contacts/import', ['csv_file' => $file])->assertSessionHasErrors('csv_file');
    expect(Contact::count())->toBe(0);
    expect(AuditLog::where('action', 'contacts.imported')->exists())->toBeFalse();
})->with(['empty', 'headers', 'formula', 'entity', 'broken', 'macro', 'missing-sheet', 'external-sheet', 'invalid-row', 'too-many', 'oversized-xml']);

test('staff cannot import Excel contacts', function () {
    $file = contactWorkbook();
    $this->actingAs(User::factory()->create(['role' => 'staff']))->post('/menu/contacts/import', ['csv_file' => $file])->assertForbidden();
    expect(Contact::count())->toBe(0);
});
