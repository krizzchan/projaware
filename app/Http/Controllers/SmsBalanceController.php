<?php

namespace App\Http\Controllers;

use App\SmsGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmsBalanceController extends Controller
{
    public function __invoke(SmsGateway $gateway): JsonResponse
    {
        $result = ['status' => 'unconfigured', 'credit_balance' => null, 'checked_at' => null];
        if ($gateway->provider() !== 'semaphore' || ! $gateway->ready()) {
            return response()->json($result)->header('Cache-Control', 'no-store, private');
        }
        $key = 'sms:semaphore:balance:'.hash('sha256', config('operations.semaphore.api_key'));
        try {
            $result = Cache::get($key);
            if ($result === null) {
                $lock = Cache::lock($key.':lock', 15);
                if (! $lock->get()) {
                    return response()->json(['status' => 'loading', 'credit_balance' => null, 'checked_at' => null])->header('Cache-Control', 'no-store, private');
                }
                try {
                    $result = Cache::get($key);
                    if ($result === null) {
                        $result = $this->fetchBalance();
                        Cache::put($key, $result, $result['status'] === 'ok' ? 300 : 60);
                    }
                } finally {
                    $lock->release();
                }
            }
        } catch (Throwable) {
            $result = ['status' => 'unavailable', 'credit_balance' => null, 'checked_at' => null];
        }

        return response()->json($result)->header('Cache-Control', 'no-store, private');
    }

    private function fetchBalance(): array
    {
        $unknown = ['status' => 'unavailable', 'credit_balance' => null, 'checked_at' => null];
        try {
            $response = Http::withoutRedirecting()->connectTimeout(3)->timeout(8)
                ->get('https://api.semaphore.co/api/v4/account', ['apikey' => config('operations.semaphore.api_key')]);
            $balance = $response->json('credit_balance');
            if (! $response->successful() || ! is_numeric($balance) || ! is_finite((float) $balance) || (float) $balance < 0) {
                return $unknown;
            }

            return ['status' => 'ok', 'credit_balance' => (float) $balance, 'checked_at' => now()->toIso8601String()];
        } catch (Throwable) {
            return $unknown;
        }
    }
}
