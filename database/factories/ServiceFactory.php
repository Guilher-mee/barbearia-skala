<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Corte de cabelo', 'Barba', 'Corte e barba']),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'price' => fake()->randomElement(['25.00', '35.00', '50.00', '70.00']),
        ];
    }
}
