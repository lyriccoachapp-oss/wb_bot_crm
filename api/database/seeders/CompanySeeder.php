<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['name' => 'TrefyProperties', 'slug' => 'tp'],
            ['name' => 'WorkBangers', 'slug' => 'wb'],
        ];

        foreach ($companies as $c) {
            Company::updateOrCreate(
                ['slug' => $c['slug']],
                ['name' => $c['name']]
            );
        }
    }
}
