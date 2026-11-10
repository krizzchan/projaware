<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Barangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IlaganBarangaySeeder extends Seeder
{
    // PSA City of Ilagan: https://psa.gov.ph/classification/psgc/barangays/0203114000
    // The source lists 91 barangays; retrieved 7 October 2026.
    public const NAMES = [
        'Cabeseria 27', 'Aggasian', 'Alibagu', 'Allinguigan 1st', 'Allinguigan 2nd', 'Allinguigan 3rd',
        'Arusip', 'Baculod', 'Bagumbayan', 'Baligatan', 'Ballacong', 'Bangag', 'Cabeseria 5',
        'Batong-Labang', 'Bigao', 'Cabeseria 4', 'Cabannungan 1st', 'Cabannungan 2nd',
        'Cabeseria 6 & 24', 'Cabeseria 19', 'Cabeseria 25', 'Cabeseria 3', 'Cabeseria 23',
        'Cadu', 'Calamagui 1st', 'Calamagui 2nd', 'Camunatan', 'Capellan', 'Capo',
        'Cabeseria 9 and 11', 'Carikkikan Norte', 'Carikkikan Sur', 'Cabeseria 14 and 16',
        'Cabeseria 2', 'Fugu', 'Fuyo', 'Gayong-Gayong Norte', 'Gayong-Gayong Sur', 'Guinatan',
        'Lullutan', 'Cabeseria 10', 'Malalam', 'Malasin', 'Manaring', 'Mangcuram', 'Villa Imelda',
        'Marana I', 'Marana II', 'Marana III', 'Minabang', 'Morado', 'Naguilian Norte',
        'Naguilian Sur', 'Namnama', 'Nanaguan', 'Cabeseria 7', 'Osmeña', 'Paliueg', 'Pasa',
        'Pilar', 'Quimalabasa', 'Rang-ayan', 'Rugao', 'Cabeseria 22', 'Salindingan',
        'San Andres', 'Centro - San Antonio', 'San Felipe', 'San Ignacio', 'San Isidro',
        'San Juan', 'San Lorenzo', 'San Pablo', 'Cabeseria 17 and 21', 'San Vicente',
        'Santa Barbara', 'Santa Catalina', 'Santa Isabel Norte', 'Santa Isabel Sur',
        'Santa Victoria', 'Santo Tomas', 'Siffu', 'Sindon Bayabo', 'Sindon Maride', 'Sipay',
        'Tangcul', 'Centro Poblacion', 'Bagong Silang', 'Imelda Bliss Village', 'San Rodrigo', 'Santa Maria',
    ];

    public function run(): void
    {
        if (count(self::NAMES) !== 91 || count(array_unique(self::NAMES)) !== 91) {
            throw new RuntimeException('The Ilagan barangay reference list must contain 91 unique names.');
        }
        $added = DB::transaction(function () {
            $added = 0;
            foreach (self::NAMES as $name) {
                $record = Barangay::firstOrCreate(['name' => $name]);
                $added += (int) $record->wasRecentlyCreated;
            }
            if ($added > 0) {
                AuditLog::record('barangay.reference_imported', 'barangay', null, ['added' => $added, 'reference_count' => 91, 'source' => 'PSA PSGC City of Ilagan']);
            }

            return $added;
        });
        $missing = Barangay::whereIn('name', self::NAMES)->count();
        if ($missing !== 91) {
            throw new RuntimeException('Barangay reference verification failed.');
        }
        $this->command?->info($added.' barangays added; all 91 official Ilagan barangays are registered.');
    }
}
