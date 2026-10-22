<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('+55119########'),
            'start_at' => Carbon::tomorrow('America/Sao_Paulo')->setTime(9, 0)->utc(),
            'end_at' => function (array $attributes): Carbon {
                $service = Service::whereKey($attributes['service_id'])->firstOrFail();

                return Carbon::parse($attributes['start_at'])->addMinutes($service->duration_minutes);
            },
        ];
    }
}
