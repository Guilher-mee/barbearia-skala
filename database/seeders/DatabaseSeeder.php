<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os dados de demonstração só podem ser preparados em local ou testing.');
        }

        DB::transaction(function (): void {
            $this->call([DemoStaffSeeder::class, ServiceSeeder::class]);
        });
    }
}
