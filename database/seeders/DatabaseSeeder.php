<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            TestCategorySeeder::class,
            LabTestSeeder::class,
            RateListSeeder::class,
            //DemoDataSeeder::class, // local only — skipped in production
        ]);
    }
}
