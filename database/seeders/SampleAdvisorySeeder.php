<?php

namespace Database\Seeders;

use App\Models\Advisory;
use App\Models\AuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleAdvisorySeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['title' => 'Flood advisory', 'severity' => 'warning', 'message' => 'CDRRMO ILAGAN FLOOD ADVISORY: As of [DATE/TIME], [VERIFIED RIVER LEVEL/FLOOD UPDATE]. Affected barangays: [BARANGAYS]. Residents are advised to [AUTHORIZED SAFETY INSTRUCTIONS]. Monitor official CDRRMO updates. For assistance: [VERIFIED HOTLINE].'],
            ['title' => 'Weather update', 'severity' => 'info', 'message' => 'CDRRMO ILAGAN WEATHER UPDATE: [VERIFIED PAGASA BULLETIN] as of [DATE/TIME]. Affected areas: [BARANGAYS]. Expected conditions: [VERIFIED FORECAST]. Please follow [LOCAL SAFETY INSTRUCTIONS]. Next update: [TIME]. For assistance: [VERIFIED HOTLINE].'],
            ['title' => 'Evacuation notice', 'severity' => 'critical', 'message' => 'CDRRMO ILAGAN EVACUATION NOTICE: [AUTHORIZED EVACUATION INSTRUCTION] for [BARANGAYS/AREAS], effective [DATE/TIME], due to [VERIFIED HAZARD]. Proceed to [DESIGNATED EVACUATION CENTER] via [APPROVED ROUTE]. Follow instructions from authorized responders. Assistance: [VERIFIED HOTLINE].'],
            ['title' => 'All-clear notice', 'severity' => 'info', 'message' => 'CDRRMO ILAGAN ALL-CLEAR NOTICE: As of [DATE/TIME], [AUTHORIZED ALL-CLEAR UPDATE] for [BARANGAYS/AREAS]. Residents may [APPROVED RETURN/RESUMPTION INSTRUCTIONS]. Continue observing [REMAINING PRECAUTIONS]. For assistance: [VERIFIED HOTLINE].'],
        ];
        $added = DB::transaction(function () use ($samples) {
            $added = 0;
            foreach ($samples as $sample) {
                $advisory = Advisory::firstOrCreate(['title' => $sample['title']], $sample);
                $added += (int) $advisory->wasRecentlyCreated;
            }
            if ($added > 0) {
                AuditLog::record('advisory.samples_added', 'advisory', null, ['added' => $added]);
            }

            return $added;
        });
        $this->command?->info($added.' sample advisory templates added. Existing templates preserved. No SMS dispatched.');
    }
}
