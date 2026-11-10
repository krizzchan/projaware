<?php

namespace App\Jobs;

use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use App\Models\SmsRecipient;
use App\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class SendTextBlastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public string $provider = 'itexmo';

    public function __construct(public int $recipientId)
    {
        $this->provider = (string) config('operations.sms_provider', 'semaphore');
    }

    public function handle(SmsGateway $gateway): void
    {
        $recipient = SmsRecipient::with('broadcast')->find($this->recipientId);
        if (! $recipient || SmsRecipient::whereKey($this->recipientId)->where('status', 'queued')->update(['status' => 'processing']) !== 1) {
            return;
        }
        $contact = Contact::find($recipient->contact_id);
        if (! $contact || ! $contact->is_active || StoreContactRequest::normalizePhone($contact->phone_number) !== $recipient->phone_number) {
            $recipient->update(['status' => 'skipped', 'error' => 'Contact removed, disabled, or changed.', 'processed_at' => now()]);

            return;
        }
        if ($recipient->broadcast->is_test) {
            $recipient->update(['status' => 'simulated', 'processed_at' => now()]);

            return;
        }
        if ($this->provider !== $gateway->provider()) {
            $recipient->update(['status' => 'failed', 'error' => 'SMS provider changed after this message was queued. Review the original broadcast before creating a new send.', 'processed_at' => now()]);

            return;
        }
        if ($gateway->dryRun() || ! $gateway->ready()) {
            $recipient->update(['status' => 'failed', 'error' => 'Live SMS configuration is disabled or incomplete.', 'processed_at' => now()]);

            return;
        }
        $error = null;
        try {
            $status = $gateway->send($recipient->phone_number, $recipient->broadcast->message);
        } catch (ConnectionException) {
            $status = 'unknown';
            $error = 'Connection to the SMS provider failed or timed out. Acceptance is unknown; check the provider before resending.';
        } catch (Throwable) {
            $status = 'unknown';
        }
        $recipient->update([
            'status' => in_array($status, ['unconfigured', 'rate_limited'], true) ? 'failed' : $status,
            'error' => $status === 'rate_limited' ? 'Semaphore request limit reached before transmission. Review before submitting again.' : (in_array($status, ['accepted', 'simulated'], true) ? null : ($error ?? $gateway->failureReason() ?? 'Gateway did not confirm acceptance. Check the provider before resending.')),
            'processed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        SmsRecipient::whereKey($this->recipientId)->whereIn('status', ['queued', 'processing'])
            ->update(['status' => 'unknown', 'error' => 'Processing interrupted; verify with the provider before resending.', 'processed_at' => now()]);
    }
}
