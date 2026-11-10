<?php

namespace App\Http\Controllers;

use App\BroadcastService;
use App\Http\Requests\SendBroadcastRequest;
use App\Models\Advisory;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Hotline;
use App\Models\IncidentReport;
use App\Models\SmsRecipient;
use App\SmsReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationsController extends Controller
{
    public function barangays(Request $request): Response
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return Inertia::render('menu/directory', [
            'kind' => 'barangays', 'title' => 'Barangay sectors',
            'records' => Barangay::withCount('contacts')->when($data['search'] ?? null, fn (Builder $query, string $term) => $query->where('name', 'like', '%'.$term.'%'))->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $data,
        ]);
    }

    public function storeBarangay(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:barangays,name']]);
        DB::transaction(function () use ($data) {
            $record = Barangay::create($data);
            AuditLog::record('barangay.created', 'barangay', $record->id);
        });

        return back()->with('success', 'Barangay added.');
    }

    public function updateBarangay(Request $request, Barangay $barangay): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('barangays')->ignore($barangay->id)]]);
        DB::transaction(function () use ($barangay, $data) {
            $barangay->update($data);
            AuditLog::record('barangay.updated', 'barangay', $barangay->id);
        });

        return back()->with('success', 'Barangay updated.');
    }

    public function destroyBarangay(Barangay $barangay): RedirectResponse
    {
        DB::transaction(function () use ($barangay) {
            $record = Barangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            if ($record->contacts()->exists()) {
                throw ValidationException::withMessages(['delete' => 'Reassign or remove this barangay\'s contacts before deleting it.']);
            }
            AuditLog::record('barangay.deleted', 'barangay', $record->id);
            $record->delete();
        });

        return back()->with('success', 'Barangay deleted.');
    }

    public function hotlines(): Response
    {
        return Inertia::render('menu/directory', ['kind' => 'hotlines', 'title' => 'Emergency hotlines', 'records' => Hotline::orderBy('name')->paginate(20), 'filters' => []]);
    }

    private function hotlineData(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100'], 'phone_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9() -]{3,30}$/'], 'description' => ['nullable', 'string', 'max:255']]);
    }

    public function storeHotline(Request $request): RedirectResponse
    {
        $data = $this->hotlineData($request);
        DB::transaction(function () use ($data) {
            $record = Hotline::create($data);
            AuditLog::record('hotline.created', 'hotline', $record->id);
        });

        return back()->with('success', 'Hotline added.');
    }

    public function updateHotline(Request $request, Hotline $hotline): RedirectResponse
    {
        $data = $this->hotlineData($request);
        DB::transaction(function () use ($hotline, $data) {
            $hotline->update($data);
            AuditLog::record('hotline.updated', 'hotline', $hotline->id);
        });

        return back()->with('success', 'Hotline updated.');
    }

    public function destroyHotline(Hotline $hotline): RedirectResponse
    {
        DB::transaction(function () use ($hotline) {
            AuditLog::record('hotline.deleted', 'hotline', $hotline->id);
            $hotline->delete();
        });

        return back()->with('success', 'Hotline deleted.');
    }

    public function advisories(): Response
    {
        return Inertia::render('menu/quickadvisory', [
            'advisories' => Advisory::latest('id')->paginate(10, ['*'], 'advisory_page')->withQueryString(),
            'barangays' => Barangay::withCount(['contacts as contact_count' => fn (Builder $query) => $query->where('is_active', true)])->orderBy('name')->get(),
            'totalContactsCount' => Contact::where('is_active', true)->count(), 'requestId' => (string) Str::uuid(),
        ]);
    }

    public function dispatchAdvisory(SendBroadcastRequest $request, BroadcastService $service): RedirectResponse
    {
        $broadcast = $service->create($request->user(), $request->validated());

        return back()->with('success', ($broadcast->is_test ? 'TEST MODE: advisory simulation queued' : 'SMS advisory queued').' for '.$broadcast->recipient_count.' recipients.');
    }

    public function reports(Request $request): Response
    {
        $filters = $this->reportFilters($request);
        $status = $filters['sms_status'] ?? 'all';
        $statuses = match ($status) {
            'sent' => ['accepted'], 'failed' => ['failed', 'rejected'], 'pending' => ['queued', 'processing'],
            'unknown' => ['unknown'], 'simulated' => ['simulated'], 'skipped' => ['skipped'], 'cancelled' => ['cancelled'], default => [],
        };
        $counts = $this->filterReportRecipients(SmsRecipient::query(), $filters)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary = [
            'sent' => (int) ($counts['accepted'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0) + (int) ($counts['rejected'] ?? 0),
            'pending' => (int) ($counts['queued'] ?? 0) + (int) ($counts['processing'] ?? 0),
            'unknown' => (int) ($counts['unknown'] ?? 0), 'simulated' => (int) ($counts['simulated'] ?? 0), 'skipped' => (int) ($counts['skipped'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
        $recipients = SmsRecipient::with(['broadcast:id,user_id,title,message,is_test,created_at', 'contact:id,first_name,last_name,category_id,barangay_id', 'contact.category:id,name', 'contact.barangay:id,name'])
            ->tap(fn (Builder $query) => $this->filterReportRecipients($query, $filters))
            ->when($statuses, fn (Builder $query) => $query->whereIn('status', $statuses))
            ->latest('id')->paginate(20, ['*'], 'sms_page')->withQueryString()
            ->through(fn (SmsRecipient $recipient) => [
                'id' => $recipient->id, 'broadcast_id' => $recipient->broadcast_id,
                'contact_name' => $recipient->contact ? trim($recipient->contact->first_name.' '.$recipient->contact->last_name) : 'Removed contact',
                'category' => $recipient->contact?->category?->name ?? 'Not available',
                'barangay' => $recipient->contact ? ($recipient->contact->barangay?->name ?? 'Not applicable') : 'Not available',
                'phone' => '*******'.substr($recipient->phone_number, -4), 'status' => $recipient->status, 'error' => $recipient->error,
                'processed_at' => $recipient->processed_at?->toIso8601String(), 'created_at' => $recipient->created_at->toIso8601String(),
                'title' => $recipient->broadcast->title, 'message' => $recipient->broadcast->message, 'is_test' => $recipient->broadcast->is_test,
                'can_cancel' => $recipient->status === 'queued' && ($request->user()->can('administer') || $recipient->broadcast->user_id === $request->user()->id),
            ]);

        return Inertia::render('menu/reports', [
            'smsRecipients' => $recipients, 'smsStatus' => $status, 'smsSummary' => $summary,
            'reportDates' => ['date_from' => $filters['date_from'] ?? '', 'date_to' => $filters['date_to'] ?? ''],
            'reportSearch' => ['recipient_search' => $filters['recipient_search'] ?? '', 'message_search' => $filters['message_search'] ?? ''],
            'reportAudience' => ['category_id' => (string) ($filters['category_id'] ?? ''), 'barangay_id' => (string) ($filters['barangay_id'] ?? '')],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'incidents' => $this->filterReportDates(IncidentReport::with('barangay:id,name'), $filters)->latest('id')->paginate(15)->withQueryString(),
            'barangays' => Barangay::orderBy('name')->get(['id', 'name']),
            'broadcasts' => Broadcast::withCount([
                'recipients as accepted_count' => fn (Builder $q) => $q->where('status', 'accepted'),
                'recipients as simulated_count' => fn (Builder $q) => $q->where('status', 'simulated'),
                'recipients as queued_count' => fn (Builder $q) => $q->where('status', 'queued'),
                'recipients as processing_count' => fn (Builder $q) => $q->where('status', 'processing'),
                'recipients as unknown_count' => fn (Builder $q) => $q->where('status', 'unknown'),
                'recipients as failed_count' => fn (Builder $q) => $q->whereIn('status', ['failed', 'rejected']),
                'recipients as skipped_count' => fn (Builder $q) => $q->where('status', 'skipped'),
                'recipients as cancelled_count' => fn (Builder $q) => $q->where('status', 'cancelled'),
            ])->tap(fn (Builder $query) => $this->filterReportDates($query, $filters))
                ->when(! empty($filters['recipient_search']) || ! empty($filters['message_search']) || ! empty($filters['category_id']) || ! empty($filters['barangay_id']), fn (Builder $query) => $query->whereHas('recipients', fn (Builder $recipients) => $this->filterReportRecipients($recipients, $filters)))
                ->latest('id')->paginate(10, ['*'], 'broadcast_page')->withQueryString()
                ->through(fn (Broadcast $broadcast) => [...$broadcast->toArray(), 'can_cancel' => $broadcast->queued_count > 0 && ($request->user()->can('administer') || $broadcast->user_id === $request->user()->id)]),
            'auditLogs' => $request->user()->can('administer') ? $this->filterReportDates(AuditLog::query(), $filters)->latest('id')->limit(30)->get() : [],
        ]);
    }

    public function exportSms(Request $request, SmsReportExport $export): BinaryFileResponse
    {
        $filters = $this->reportFilters($request);
        $status = $filters['sms_status'] ?? 'all';
        $statuses = match ($status) {
            'sent' => ['accepted'], 'failed' => ['failed', 'rejected'], 'pending' => ['queued', 'processing'],
            'unknown' => ['unknown'], 'simulated' => ['simulated'], 'skipped' => ['skipped'], 'cancelled' => ['cancelled'], default => [],
        };
        $query = SmsRecipient::with(['broadcast:id,title,message,is_test', 'contact:id,first_name,last_name,category_id,barangay_id', 'contact.category:id,name', 'contact.barangay:id,name'])
            ->tap(fn (Builder $query) => $this->filterReportRecipients($query, $filters))
            ->when($statuses, fn (Builder $query) => $query->whereIn('status', $statuses))->latest('id');
        $response = $export->download($query, $status);
        AuditLog::record('sms.report_exported', 'sms_report', null, ['status' => $status, ...array_intersect_key($filters, array_flip(['date_from', 'date_to']))]);

        return $response;
    }

    private function reportFilters(Request $request): array
    {
        return $request->validate([
            'sms_status' => ['nullable', Rule::in(['all', 'sent', 'failed', 'pending', 'unknown', 'simulated', 'skipped', 'cancelled'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'recipient_search' => ['nullable', 'string', 'max:100'],
            'message_search' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'barangay_id' => ['nullable', Rule::when($request->input('barangay_id') === 'none', ['in:none'], ['integer', 'exists:barangays,id'])],
        ]);
    }

    private function filterReportRecipients(Builder $query, array $filters): Builder
    {
        $this->filterReportDates($query, $filters);
        if (! empty($filters['category_id']) || ! empty($filters['barangay_id'])) {
            $query->whereHas('contact', function (Builder $contact) use ($filters): void {
                if (! empty($filters['category_id'])) {
                    $contact->where('category_id', $filters['category_id']);
                }
                if (($filters['barangay_id'] ?? null) === 'none') {
                    $contact->whereNull('barangay_id');
                } elseif (! empty($filters['barangay_id'])) {
                    $contact->where('barangay_id', $filters['barangay_id']);
                }
            });
        }
        if (! empty(trim($filters['recipient_search'] ?? ''))) {
            $terms = preg_split('/\s+/u', trim($filters['recipient_search']));
            $query->whereHas('contact', function (Builder $contact) use ($terms): void {
                foreach ($terms as $term) {
                    $contact->where(fn (Builder $name) => $name->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%'));
                }
            });
        }
        if (! empty(trim($filters['message_search'] ?? ''))) {
            $query->whereHas('broadcast', fn (Builder $broadcast) => $broadcast->where('title', 'like', '%'.trim($filters['message_search']).'%'));
        }

        return $query;
    }

    private function filterReportDates(Builder $query, array $filters): Builder
    {
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'], 'Asia/Manila')->startOfDay()->utc());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['date_to'], 'Asia/Manila')->startOfDay()->addDay()->utc());
        }

        return $query;
    }

    public function cancelSms(Request $request, SmsRecipient $recipient): RedirectResponse
    {
        abort_unless($request->user()->can('administer') || $recipient->broadcast->user_id === $request->user()->id, 403);
        $cancelled = $this->cancelQueued(SmsRecipient::whereKey($recipient->id), 'sms_recipient', $recipient->id);

        return back()->with($cancelled ? 'success' : 'error', $cancelled ? 'Queued message cancelled. It will not be sent.' : 'This message is no longer queued and cannot be cancelled.');
    }

    public function cancelBroadcast(Request $request, Broadcast $broadcast): RedirectResponse
    {
        abort_unless($request->user()->can('administer') || $broadcast->user_id === $request->user()->id, 403);
        $cancelled = $this->cancelQueued(SmsRecipient::where('broadcast_id', $broadcast->id), 'broadcast', $broadcast->id);

        return back()->with($cancelled ? 'success' : 'error', $cancelled ? $cancelled.' queued messages cancelled. Messages already processing or sent cannot be recalled.' : 'No queued messages remain to cancel.');
    }

    private function cancelQueued(Builder $query, string $subjectType, int $subjectId): int
    {
        return DB::transaction(function () use ($query, $subjectType, $subjectId): int {
            $count = $query->where('status', 'queued')->update([
                'status' => 'cancelled', 'error' => 'Cancelled by an authorized operator before sending.', 'processed_at' => now(),
            ]);
            if ($count > 0) {
                AuditLog::record('sms.cancelled', $subjectType, $subjectId, ['cancelled_count' => $count]);
            }

            return $count;
        });
    }

    public function storeIncident(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'max:5000'], 'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'], 'severity' => ['required', Rule::in(['info', 'warning', 'critical'])]]);
        DB::transaction(function () use ($request, $data) {
            $incident = IncidentReport::create([...$data, 'user_id' => $request->user()->id, 'status' => 'open']);
            AuditLog::record('incident.created', 'incident', $incident->id);
        });

        return back()->with('success', 'Incident recorded.');
    }

    public function updateIncident(Request $request, IncidentReport $incident): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'responding', 'resolved'])]]);
        DB::transaction(function () use ($incident, $data) {
            $incident->update($data);
            AuditLog::record('incident.status_updated', 'incident', $incident->id, $data);
        });

        return back()->with('success', 'Incident status updated.');
    }
}
