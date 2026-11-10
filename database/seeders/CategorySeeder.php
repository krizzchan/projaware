<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds for City of Ilagan DRRMO & Administrative Ops.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Ilagan Rescue 1124 & CDRRMO',
                'description' => 'Primary responders for medical emergencies, vehicular accidents, and disaster response across Ilagan.',
            ],
            [
                'name' => 'Barangay Captains & BDRRMC',
                'description' => 'Punong Barangays and Local Disaster Risk Reduction Committees across all 91 barangays.',
            ],
            [
                'name' => 'Cagayan & Abuan River Basin Flood Monitoring',
                'description' => 'Tasked with water-level advisories and evacuations for low-lying riverbank barangays (e.g., Baculud, Bagumbayan, Sta. Victoria).',
            ],
            [
                'name' => 'Maharlika Highway Traffic & Road Safety',
                'description' => 'First responders for vehicular collisions and debris clearing along the Pan-Philippine Highway stretch.',
            ],
            [
                'name' => 'Upland & Sierra Madre Landslide Monitoring',
                'description' => 'Monitors foothill and mountain barangays prone to soil erosion, landslides, and flash floods.',
            ],
            [
                'name' => 'Agricultural & Crop Damage Task Force',
                'description' => 'Advisories and relief support for corn and rice farming sectors during severe dry spells (El Niño) or flooding.',
            ],
            [
                'name' => 'Evacuation Center & Relief Operations (CSWDO)',
                'description' => 'City Social Welfare and Development team managing evacuation centers and relief distribution.',
            ],
            [
                'name' => 'Health & Hospital Network (CHO & GFNDSMH)',
                'description' => 'City Health Offices and Gov. Faustino N. Dy Sr. Memorial Hospital emergency contacts.',
            ],
            [
                'name' => 'Utilities & Infrastructure (ISELCO II & DPWH)',
                'description' => 'Power restoration, water utility management, and municipal bridge/road maintenance response.',
            ],
            [
                'name' => 'DepEd & School Heads',
                'description' => 'Principals and school administrators for class suspension notifications and school-turned-evacuation sites.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }
    }
}