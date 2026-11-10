<?php

namespace App;

use Illuminate\Support\Facades\DB;

class DashboardAnalytics
{
    public function summary(): array
    {
        $start = now('Asia/Manila')->startOfDay()->subDays(6);
        $end = now('Asia/Manila')->endOfDay();
        $utcStart = $start->copy()->utc();
        $utcEnd = $end->copy()->utc();
        $dayExpression = DB::getDriverName() === 'sqlite'
            ? "DATE(datetime(created_at, '+8 hours'))"
            : 'DATE(DATE_ADD(created_at, INTERVAL 8 HOUR))';
        $activity = DB::table('broadcasts')->whereBetween('created_at', [$utcStart, $utcEnd])
            ->selectRaw($dayExpression.' as day, is_test, COUNT(*) as total')
            ->groupByRaw($dayExpression.', is_test')->get();
        $daily = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $start->copy()->addDays($offset);
            $rows = $activity->where('day', $date->toDateString());
            $daily[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'live' => (int) $rows->where('is_test', false)->sum('total'),
                'test' => (int) $rows->where('is_test', true)->sum('total'),
            ];
        }
        $counts = DB::table('sms_recipients')->join('broadcasts', 'broadcasts.id', '=', 'sms_recipients.broadcast_id')
            ->whereBetween('broadcasts.created_at', [$utcStart, $utcEnd])
            ->selectRaw('sms_recipients.status, COUNT(*) as total')->groupBy('sms_recipients.status')->pluck('total', 'status');

        return [
            'daily' => $daily,
            'liveBroadcasts' => array_sum(array_column($daily, 'live')),
            'testBroadcasts' => array_sum(array_column($daily, 'test')),
            'outcomes' => [
                'accepted' => (int) ($counts['accepted'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0) + (int) ($counts['rejected'] ?? 0),
                'pending' => (int) ($counts['queued'] ?? 0) + (int) ($counts['processing'] ?? 0),
                'unknown' => (int) ($counts['unknown'] ?? 0),
                'simulated' => (int) ($counts['simulated'] ?? 0),
                'skipped' => (int) ($counts['skipped'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
            ],
        ];
    }
}
