<?php

namespace App;

use App\Http\Requests\StoreContactRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class SmsGateway
{
    private ?string $failureReason = null;

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }
    public function provider(): string
    {
        return (string) config('operations.sms_provider', 'semaphore');
    }

    public function label(): string
    {
        return $this->provider() === 'semaphore' ? 'Semaphore' : ($this->provider() === 'itexmo' ? 'iTexMo' : 'SMS gateway');
    }

    public function dryRun(): bool
    {
        return ! in_array($this->provider(), ['semaphore', 'itexmo'], true) || (bool) config('operations.'.$this->provider().'.dry_run', true);
    }

    public function ready(): bool
    {
        if ($this->provider() === 'semaphore') {
            return filled(config('operations.semaphore.api_key'));
        }
        if ($this->provider() !== 'itexmo') {
            return false;
        }
        return filled(config('operations.itexmo.email'))
            && filled(config('operations.itexmo.api_code'))
            && filled(config('operations.itexmo.password'));
    }

    /** iTexMo broadcast API: https://itexmo.com/apidocs */
    public function send(string $phone, string $message): string
    {
        $this->failureReason = null;
        if (! $this->ready()) {
            return 'unconfigured';
        }
        if ($this->provider() === 'semaphore') {
            return $this->sendSemaphore($phone, $message);
        }
        $response = Http::asForm()->withoutRedirecting()->connectTimeout(5)->timeout(20)
            ->post('https://api.itexmo.com/api/broadcast', [
                'Recipients' => json_encode([$phone], JSON_THROW_ON_ERROR),
                'Message' => $message,
                'ApiCode' => config('operations.itexmo.api_code'),
                'Email' => config('operations.itexmo.email'),
                'Password' => config('operations.itexmo.password'),
            ]);
        if (! $response->successful()) {
            return 'unknown';
        }
        $result = $response->json();
        if (is_array($result) && ($result['Error'] ?? null) === false && (int) ($result['Accepted'] ?? 0) === 1 && (int) ($result['Failed'] ?? -1) === 0) {
            return 'accepted';
        }

        return is_array($result) && ($result['Error'] ?? null) === true ? 'rejected' : 'unknown';
    }

    /** Semaphore v4: https://semaphore.co/docs */
    private function sendSemaphore(string $phone, string $message): string
    {
        if (preg_match('/^\s*TEST\b/i', $message)) {
            return 'rejected';
        }
        if (RateLimiter::tooManyAttempts('sms:semaphore-outbound', 110)) {
            return 'rate_limited';
        }
        RateLimiter::hit('sms:semaphore-outbound', 60);
        $parameters = ['apikey' => config('operations.semaphore.api_key'), 'number' => $phone, 'message' => $message];
        if (filled(config('operations.semaphore.sender_name'))) {
            $parameters['sendername'] = config('operations.semaphore.sender_name');
        }
        $response = Http::asForm()->withoutRedirecting()->connectTimeout(5)->timeout(20)
            ->post('https://api.semaphore.co/api/v4/messages', $parameters);
        $result = $response->json();
        if (is_array($result) && ($response->successful() || in_array($response->status(), [400, 401, 402, 403, 422], true))) {
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : $result;
            foreach ([
                'sendername' => 'Semaphore rejected the sender name. Select an approved active sender in your Semaphore account.',
                'apikey' => 'Semaphore rejected the API credentials. Verify the server API key and account permissions.',
                'number' => 'Semaphore rejected the recipient number. Verify the saved mobile number.',
                'message' => 'Semaphore rejected the message content. Review the provider message requirements.',
            ] as $field => $reason) {
                if (array_key_exists($field, $errors) && ! array_is_list($errors)) {
                    $this->failureReason = $reason;

                    return 'rejected';
                }
            }
        }
        if (! $response->successful()) {
            $this->failureReason = match ($response->status()) {
                401, 403 => 'Semaphore denied access. Verify the API key and account permissions.',
                402 => 'Semaphore requires account credits before sending.',
                429 => 'Semaphore request limit reached. Wait before submitting another message.',
                400, 422 => 'Semaphore rejected the request. Check the account sender name and message requirements.',
                default => 'Semaphore returned an unexpected HTTP response. Acceptance is unknown; check the provider before resending.',
            };
            return in_array($response->status(), [400, 401, 402, 403, 422, 429], true) ? 'rejected' : 'unknown';
        }
        if (! is_array($result) || ! array_is_list($result) || count($result) !== 1 || ! is_array($result[0])) {
            $this->failureReason = 'Semaphore returned an unrecognized response. Acceptance is unknown; check the provider before resending.';
            return 'unknown';
        }
        $item = $result[0];
        if (! filter_var($item['message_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || StoreContactRequest::normalizePhone((string) ($item['recipient'] ?? '')) !== StoreContactRequest::normalizePhone($phone)) {
            return 'unknown';
        }

        return match (strtolower((string) ($item['status'] ?? ''))) {
            'queued', 'pending', 'sent' => 'accepted',
            'failed', 'refunded' => 'rejected',
            default => 'unknown',
        };
    }
}
