<?php

namespace App\Http\Controllers;

use App\BroadcastService;
use App\ContactExcelTemplate;
use App\ContactSpreadsheetReader;
use App\Http\Requests\SendBroadcastRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Advisory;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactController extends Controller
{
    private const CSV_COLUMNS = ['first_name', 'middle_initial', 'last_name', 'office', 'position', 'phone_number', 'category_id', 'barangay_id'];

    public function index(Request $request): Response
    {
        $filters = $this->contactFilters($request);
        $query = $this->filteredContacts($filters);

        return Inertia::render('menu/contact', [
            'contacts' => $query->latest('id')->paginate(10)->withQueryString(),
            'categories' => Category::orderBy('name')->get(), 'barangays' => Barangay::orderBy('name')->get(), 'filters' => $filters,
            'directoryStats' => ['total' => Contact::count(), 'active' => Contact::where('is_active', true)->count(), 'barangays' => Contact::where('is_active', true)->distinct()->count('barangay_id')],
        ]);
    }

    private function contactFilters(Request $request): array
    {
        return $request->validate(['search' => ['nullable', 'string', 'max:100'], 'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'], 'category_id' => ['nullable', 'integer', 'exists:categories,id']]);
    }

    private function filteredContacts(array $filters): Builder
    {
        $query = Contact::with(['category', 'barangay']);
        if (isset($filters['search']) && $filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $q) => $q->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('phone_number', 'like', $term)->orWhere('office', 'like', $term)->orWhere('position', 'like', $term));
        }
        foreach (['barangay_id', 'category_id'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredContacts($this->contactFilters($request));
        AuditLog::record('contacts.exported', 'contact', null, ['format' => 'csv']);

        return response()->streamDownload(function () use ($query): void {
            $file = fopen('php://output', 'w');
            try {
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, [...self::CSV_COLUMNS, 'category', 'barangay', 'is_active'], ',', '"', '', "\r\n");
                foreach ($query->lazyById(200) as $contact) {
                    $row = [];
                    foreach (self::CSV_COLUMNS as $column) {
                        $row[] = (string) ($contact->{$column} ?? '');
                    }
                    $row = [...$row, $contact->category?->name ?? '', $contact->barangay?->name ?? 'Not applicable', $contact->is_active ? 'Yes' : 'No'];
                    foreach ($row as $index => $value) {
                        if ($index === 5 || preg_match('/^[\s\x{FEFF}]*[=+@\-\t\r\n]/u', $value)) {
                            $row[$index] = "'".$value;
                        }
                    }
                    fputcsv($file, $row, ',', '"', '', "\r\n");
                }
            } finally {
                fclose($file);
            }
        }, 'Project-AWARE-Contacts-'.now('Asia/Manila')->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function textblast(Request $request): Response
    {
        $data = $request->validate(['advisory' => ['nullable', 'integer', 'exists:advisories,id'], 'contact_search' => ['nullable', 'string', 'max:100'], 'contact_page' => ['nullable', 'integer', 'min:1'], 'template_search' => ['nullable', 'string', 'max:100'], 'template_page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('menu/textblast', [
            'selectableContacts' => Contact::where('is_active', true)
                ->when(! empty($data['contact_search']), fn (Builder $query) => $query->where(fn (Builder $match) => $match
                    ->where('first_name', 'like', '%'.$data['contact_search'].'%')->orWhere('last_name', 'like', '%'.$data['contact_search'].'%')->orWhere('phone_number', 'like', '%'.$data['contact_search'].'%')))
                ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
                ->paginate(25, ['id', 'first_name', 'last_name', 'phone_number'], 'contact_page')->withQueryString(),
            'templates' => Advisory::query()->when(! empty($data['template_search']), fn (Builder $query) => $query->where('title', 'like', '%'.$data['template_search'].'%'))
                ->orderBy('title')->orderBy('id')->paginate(20, ['id', 'title', 'message', 'severity'], 'template_page')->withQueryString(),
            'initialTemplate' => isset($data['advisory']) ? Advisory::find($data['advisory'], ['id', 'title', 'message', 'severity']) : null,
            'selectedAdvisoryId' => isset($data['advisory']) ? (int) $data['advisory'] : null,
            'categories' => Category::withCount(['contacts' => fn (Builder $query) => $query->where('is_active', true)])->orderBy('name')->get(),
            'barangays' => Barangay::orderBy('name')->get(),
            'totalContactsCount' => Contact::where('is_active', true)->count(),
            'audienceCounts' => Contact::where('is_active', true)->select('category_id', 'barangay_id')->selectRaw('COUNT(*) as total')->groupBy('category_id', 'barangay_id')->get(),
            'requestId' => (string) Str::uuid(),
            'recentBroadcasts' => Broadcast::withCount(['recipients as accepted_count' => fn (Builder $query) => $query->where('status', 'accepted'), 'recipients as simulated_count' => fn (Builder $query) => $query->where('status', 'simulated'), 'recipients as queued_count' => fn (Builder $query) => $query->whereIn('status', ['queued', 'processing']), 'recipients as failed_count' => fn (Builder $query) => $query->whereIn('status', ['failed', 'rejected', 'unknown'])])->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request) {
                $contact = Contact::create($request->validated());
                AuditLog::record('contact.created', 'contact', $contact->id);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['phone_number' => 'The contact could not be saved. Check for duplicate phone numbers.']);
        }

        return to_route('menu.contacts.index')->with('success', 'Contact registered successfully.');
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $contact) {
                $contact->update($request->validated());
                AuditLog::record('contact.updated', 'contact', $contact->id);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['phone_number' => 'The contact could not be saved. Check for duplicate phone numbers.']);
        }

        return to_route('menu.contacts.index')->with('success', 'Contact updated successfully.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        DB::transaction(function () use ($contact) {
            AuditLog::record('contact.deleted', 'contact', $contact->id);
            $contact->delete();
        });

        return to_route('menu.contacts.index')->with('success', 'Contact deleted successfully.');
    }

    public function sendTextBlast(SendBroadcastRequest $request, BroadcastService $service): RedirectResponse
    {
        $broadcast = $service->create($request->user(), $request->validated());

        return back()->with('success', ($broadcast->is_test ? 'TEST MODE: simulated broadcast' : 'Broadcast queued').' for '.$broadcast->recipient_count.' recipients. Check broadcast history for results.');
    }

    public function downloadTemplate(Request $request): StreamedResponse|BinaryFileResponse
    {
        $data = $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx'])]]);
        if (($data['format'] ?? 'csv') === 'xlsx') {
            return app(ContactExcelTemplate::class)->download(self::CSV_COLUMNS);
        }
        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, self::CSV_COLUMNS, ',', '"', '', "\r\n");
            fclose($file);
        }, 'cdrrmo_contacts_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt,xlsx,zip', 'extensions:csv,xlsx', 'max:1024']]);
        $handle = app(ContactSpreadsheetReader::class)->open($request->file('csv_file'));
        $columns = self::CSV_COLUMNS;
        $categoryLabels = Category::get(['id', 'name'])->mapWithKeys(fn ($item) => [$item->id.' - '.$item->name => $item->id])->all();
        $barangayLabels = Barangay::get(['id', 'name'])->mapWithKeys(fn ($item) => [$item->id.' - '.$item->name => $item->id])->all();
        $rows = [];
        $seen = [];
        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if (is_array($header)) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            }
            if ($header !== $columns) {
                throw ValidationException::withMessages(['csv_file' => 'CSV headers must match the downloadable template exactly.']);
            }
            $line = 1;
            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($row === [null] || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($row) !== count($columns) || count($rows) >= 1000) {
                    throw ValidationException::withMessages(['csv_file' => 'Invalid CSV row '.$line.' or the 1,000-row import limit was exceeded. No contacts were imported.']);
                }
                $record = array_combine($columns, array_map('trim', $row));
                $record['category_id'] = $categoryLabels[$record['category_id']] ?? $record['category_id'];
                $record['barangay_id'] = in_array($record['barangay_id'], ['', 'Not applicable'], true)
                    ? null : ($barangayLabels[$record['barangay_id']] ?? $record['barangay_id']);
                $record['phone_number'] = StoreContactRequest::normalizePhone($record['phone_number']);
                $record['is_active'] = true;
                $validator = Validator::make($record, StoreContactRequest::contactRules());
                if ($validator->fails() || isset($seen[$record['phone_number']])) {
                    throw ValidationException::withMessages(['csv_file' => 'Invalid or duplicate data on row '.$line.'. '.($validator->errors()->first() ?: 'Phone number is repeated in this file.').' No contacts were imported.']);
                }
                $seen[$record['phone_number']] = true;
                $rows[] = $validator->validated();
            }
        } finally {
            fclose($handle);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV contains no contacts.']);
        }
        try {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    Contact::create($row);
                }
                AuditLog::record('contacts.imported', 'contact', null, ['count' => count($rows)]);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['csv_file' => 'The import conflicted with existing records. No contacts were imported.']);
        }

        return to_route('menu.contacts.index')->with('success', count($rows).' contacts imported successfully.');
    }
}
