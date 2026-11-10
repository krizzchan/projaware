<?php

namespace App;

use App\Http\Requests\StoreContactRequest;
use App\Jobs\SendTextBlastJob;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Broadcast;
use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BroadcastService
{
    public function __construct(private SmsGateway $gateway) {}

    public function create(User $user, array $data): Broadcast
    {
        $hash = hash('sha256', json_encode(collect($data)->except('request_id')->sortKeys()->all(), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $data, $hash) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Broadcast::where('request_id', $data['request_id'])->first();
            if ($existing) {
                if ($existing->user_id !== $user->id || $existing->payload_hash !== $hash) {
                    throw ValidationException::withMessages(['message' => 'This request identifier has already been used. Refresh before sending again.']);
                }

                return $existing;
            }
            $isTest = $this->gateway->dryRun();
            if (! $isTest && ! $this->gateway->ready()) {
                throw ValidationException::withMessages(['message' => 'Live SMS is unavailable until the '.$this->gateway->label().' API credentials are configured.']);
            }
            if (! $isTest && $this->gateway->provider() === 'semaphore' && preg_match('/^\s*TEST\b/i', $data['message'])) {
                throw ValidationException::withMessages(['message' => 'Semaphore does not deliver messages starting with TEST. Use simulation mode for tests, or change the message before sending.']);
            }
            $query = Contact::where('is_active', true);
            $target = 'All active contacts';
            $mode = $data['audience_mode'] ?? 'filters';
            if (in_array($mode, ['single', 'multiple'], true)) {
                $query->whereIn('id', $data['selected_contact_ids']);
                $target = $mode === 'single' ? 'Single contact' : 'Selected contacts';
            }
            if (in_array($mode, ['filters', 'barangay'], true) && ! empty($data['barangay_id'])) {
                $query->where('barangay_id', $data['barangay_id']);
                $target = Barangay::findOrFail($data['barangay_id'])->name;
            }
            if (in_array($mode, ['filters', 'category'], true) && ! empty($data['category_id'])) {
                $query->where('category_id', $data['category_id']);
                $target .= ' / '.Category::findOrFail($data['category_id'])->name;
            }
            if (($data['target_scope'] ?? null) === 'specific') {
                $query->whereIn('barangay_id', $data['selected_barangays']);
                $target = 'Selected barangays';
            }
            $contacts = $query->orderBy('id')->limit(config('operations.max_recipients') + 1)->get();
            if ($contacts->isEmpty() || $contacts->count() > config('operations.max_recipients')) {
                throw ValidationException::withMessages(['message' => 'Choose an audience of 1 to '.config('operations.max_recipients').' active contacts.']);
            }
            $dayStart = now('Asia/Manila')->startOfDay()->utc();
            $used = Broadcast::where('user_id', $user->id)->where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayStart->copy()->addDay())->sum('recipient_count');
            if ($used + $contacts->count() > config('operations.daily_recipient_limit')) {
                throw ValidationException::withMessages(['message' => 'Your daily recipient limit has been reached.']);
            }
            foreach ($contacts as $contact) {
                if (! preg_match('/^09\d{9}$/', StoreContactRequest::normalizePhone($contact->phone_number))) {
                    throw ValidationException::withMessages(['message' => 'The selected audience contains invalid phone numbers. Correct the directory first.']);
                }
            }
            $broadcast = Broadcast::create([
                'user_id' => $user->id, 'request_id' => $data['request_id'], 'payload_hash' => $hash,
                'title' => $data['title'] ?? 'Emergency SMS alert', 'severity' => $data['severity'] ?? 'info',
                'message' => $data['message'], 'target' => $target, 'recipient_count' => $contacts->count(), 'is_test' => $isTest,
            ]);
            foreach ($contacts as $contact) {
                $broadcast->recipients()->create(['contact_id' => $contact->id, 'phone_number' => StoreContactRequest::normalizePhone($contact->phone_number)]);
            }
            AuditLog::record('broadcast.created', 'broadcast', $broadcast->id, ['recipient_count' => $contacts->count(), 'is_test' => $isTest]);
            $queue = config('queue.connections.'.config('queue.default'));
            if (($queue['driver'] ?? null) === 'database'
                && (($queue['connection'] ?? null) === null || $queue['connection'] === config('database.default'))) {
                foreach ($broadcast->recipients()->pluck('id') as $id) {
                    SendTextBlastJob::dispatch($id)->beforeCommit();
                }

                return $broadcast;
            }
            DB::afterCommit(function () use ($broadcast) {
                foreach ($broadcast->recipients()->pluck('id') as $id) {
                    try {
                        SendTextBlastJob::dispatch($id);
                    } catch (Throwable) {
                        $broadcast->recipients()->whereKey($id)->where('status', 'queued')->update(['status' => 'failed', 'error' => 'Could not enqueue SMS.']);
                    }
                }
            });

            return $broadcast;
        });
    }
}
