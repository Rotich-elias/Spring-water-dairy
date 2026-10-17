<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lookup;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'categories' => [
                ['name' => 'Milking Cows', 'sort_order' => 1],
                ['name' => 'Dry Cows', 'sort_order' => 2],
                ['name' => 'Heifers', 'sort_order' => 3],
                ['name' => 'Calves', 'sort_order' => 4],
                ['name' => 'Bulls', 'sort_order' => 5],
            ],
            'breeds' => [
                ['name' => 'Friesian / Holstein', 'sort_order' => 1],
                ['name' => 'Jersey', 'sort_order' => 2],
                ['name' => 'Ayrshire', 'sort_order' => 3],
                ['name' => 'Guernsey', 'sort_order' => 4],
                ['name' => 'Crossbreed', 'sort_order' => 5],
            ],
            'pens' => [
                ['name' => 'Parlour A', 'sort_order' => 1],
                ['name' => 'Parlour B', 'sort_order' => 2],
                ['name' => 'Maternity', 'sort_order' => 3],
                ['name' => 'Calf Nursery', 'sort_order' => 4],
                ['name' => 'Hospital Pen', 'sort_order' => 5],
            ],
            'animal-statuses' => [
                ['name' => 'Active', 'sort_order' => 1],
                ['name' => 'In Heat', 'sort_order' => 2],
                ['name' => 'Pregnant', 'sort_order' => 3],
                ['name' => 'Dry', 'sort_order' => 4],
                ['name' => 'Withholding', 'sort_order' => 5],
                ['name' => 'Exited', 'sort_order' => 6],
            ],
            'pd-results' => [
                ['name' => 'Pregnant', 'sort_order' => 1],
                ['name' => 'Open', 'sort_order' => 2],
                ['name' => 'Recheck', 'sort_order' => 3],
            ],
            'calving-ease-scores' => [
                ['name' => 'Unassisted', 'sort_order' => 1],
                ['name' => 'Easy Pull', 'sort_order' => 2],
                ['name' => 'Hard Pull', 'sort_order' => 3],
                ['name' => 'Veterinary Assistance', 'sort_order' => 4],
                ['name' => 'Cesarean', 'sort_order' => 5],
            ],
            'health-conditions' => [
                ['name' => 'Mastitis', 'sort_order' => 1],
                ['name' => 'Lameness', 'sort_order' => 2],
                ['name' => 'Milk Fever', 'sort_order' => 3],
                ['name' => 'Ketosis', 'sort_order' => 4],
                ['name' => 'Respiratory', 'sort_order' => 5],
                ['name' => 'Digestive', 'sort_order' => 6],
            ],
            'feed-groups' => [
                ['name' => 'Lactating', 'sort_order' => 1],
                ['name' => 'Dry', 'sort_order' => 2],
                ['name' => 'Heifer', 'sort_order' => 3],
                ['name' => 'Calf', 'sort_order' => 4],
            ],
        ];

        foreach ($data as $type => $rows) {
            foreach ($rows as $row) {
                Lookup::firstOrCreate(
                    ['type' => $type, 'name' => $row['name']],
                    ['sort_order' => $row['sort_order'], 'active' => true]
                );
            }
        }
    }
}
