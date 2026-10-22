<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Corte de cabelo', 'duration_minutes' => 30, 'price' => '35.00'],
            ['name' => 'Barba', 'duration_minutes' => 15, 'price' => '25.00'],
            ['name' => 'Corte e barba', 'duration_minutes' => 45, 'price' => '55.00'],
        ] as $service) {
            Service::firstOrCreate(['name' => $service['name']], $service);
        }
    }
}
