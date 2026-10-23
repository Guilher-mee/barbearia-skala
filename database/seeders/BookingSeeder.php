<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(ServiceSeeder::class);

        $startAt = Carbon::tomorrow('America/Sao_Paulo')->setTime(9, 0)->utc();

        foreach (['Corte de cabelo', 'Barba', 'Corte e barba'] as $serviceName) {
            $service = Service::where('name', $serviceName)->firstOrFail();
            $endAt = $startAt->copy()->addMinutes($service->duration_minutes);

            $hasOverlap = Booking::where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->exists();

            if (! $hasOverlap) {
                Booking::factory()->for($service)->create([
                    'start_at' => $startAt->copy(),
                    'end_at' => $endAt,
                    'price_at_booking' => $service->price,
                ]);
            }

            $startAt->addHour();
        }
    }
}
