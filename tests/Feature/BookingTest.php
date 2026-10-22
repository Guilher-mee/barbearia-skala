<?php

use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonInterface;
use Database\Seeders\BookingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

test('persists bookings with their service and customer contact details', function () {
    $service = Service::factory()->create(['price' => '35.50']);
    $booking = Booking::factory()->for($service)->create([
        'customer_name' => 'Ana Silva',
        'customer_phone' => '+55011912345678',
        'start_at' => '2026-10-23 12:00:00',
        'end_at' => '2026-10-23 12:30:00',
    ]);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'service_id' => $service->id,
        'customer_name' => 'Ana Silva',
        'customer_phone' => '+55011912345678',
        'start_at' => '2026-10-23 12:00:00',
        'end_at' => '2026-10-23 12:30:00',
    ]);

    expect($booking->fresh()->service->is($service))->toBeTrue();
    expect($service->bookings->sole()->is($booking))->toBeTrue();
    expect($service->fresh()->price)->toBe('35.50');
});

test('calculates the end time when creating a booking without one', function () {
    $service = Service::factory()->create(['duration_minutes' => 45]);

    $booking = $service->bookings()->create([
        'customer_name' => 'Ana Silva',
        'customer_phone' => '+5511912345678',
        'start_at' => '2026-10-23 12:00:00',
    ]);

    expect($booking->fresh()->start_at)->toBeInstanceOf(CarbonInterface::class);
    expect($booking->fresh()->end_at->toDateTimeString())->toBe('2026-10-23 12:45:00');

    $service->update(['duration_minutes' => 60]);
    $booking->update(['customer_name' => 'Ana Souza']);

    expect($booking->fresh()->end_at->toDateTimeString())->toBe('2026-10-23 12:45:00');
});

test('builds factory booking end times using the associated service duration', function () {
    $service = Service::factory()->create(['duration_minutes' => 15]);

    $booking = Booking::factory()->for($service)->create([
        'start_at' => '2026-10-23 12:00:00',
    ]);

    expect($booking->fresh()->end_at->toDateTimeString())->toBe('2026-10-23 12:15:00');
});

test('prevents deleting a service that has bookings', function () {
    $booking = Booking::factory()->create();

    expect(fn () => $booking->service->delete())->toThrow(QueryException::class);

    $this->assertModelExists($booking->service);
    $this->assertModelExists($booking);
});

test('rejects bookings whose associated service does not exist', function () {
    expect(fn () => Booking::factory()->create(['service_id' => 999, 'end_at' => '2026-10-23 12:30:00']))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('bookings', 0);
});

test('seeds services and nonoverlapping bookings within business hours without duplicates', function () {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));

    $this->seed(BookingSeeder::class);
    $this->seed(BookingSeeder::class);

    $this->assertDatabaseCount('services', 3);
    $this->assertDatabaseCount('bookings', 3);

    $previousEnd = null;

    foreach (Booking::with('service')->orderBy('start_at')->get() as $booking) {
        $localStart = $booking->start_at->copy()->timezone('America/Sao_Paulo');
        $localEnd = $booking->end_at->copy()->timezone('America/Sao_Paulo');

        expect($localStart->toDateString())->toBe('2026-10-23');
        expect($localEnd->toDateString())->toBe('2026-10-23');
        expect($localStart->hour)->toBeGreaterThanOrEqual(9);
        expect($localEnd->lessThanOrEqualTo($localStart->copy()->setTime(18, 0)))->toBeTrue();
        expect((int) $booking->start_at->diffInMinutes($booking->end_at))->toBe($booking->service->duration_minutes);

        if ($previousEnd !== null) {
            expect($booking->start_at->greaterThanOrEqualTo($previousEnd))->toBeTrue();
        }

        $previousEnd = $booking->end_at;
    }
});

test('skips seeded time slots that overlap an existing booking for any service', function (string $startAt, string $endAt) {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));
    $service = Service::factory()->create([
        'name' => 'Serviço existente',
        'duration_minutes' => 30,
    ]);
    $booking = Booking::factory()->for($service)->create([
        'start_at' => $startAt,
        'end_at' => $endAt,
    ]);

    $this->seed(BookingSeeder::class);

    $this->assertDatabaseCount('bookings', 3);
    $this->assertModelExists($booking);

    expect(Booking::where('id', '!=', $booking->id)
        ->where('start_at', '<', $booking->end_at)
        ->where('end_at', '>', $booking->start_at)
        ->exists())->toBeFalse();
})->with([
    'existing booking overlaps the end' => ['2026-10-23 12:15:00', '2026-10-23 12:45:00'],
    'existing booking overlaps the start' => ['2026-10-23 11:45:00', '2026-10-23 12:15:00'],
    'existing booking is inside the slot' => ['2026-10-23 12:05:00', '2026-10-23 12:20:00'],
    'existing booking contains the slot' => ['2026-10-23 11:45:00', '2026-10-23 12:45:00'],
    'existing booking matches the slot' => ['2026-10-23 12:00:00', '2026-10-23 12:30:00'],
]);

test('seeds adjacent slots using the existing service duration without treating them as overlaps', function (string $startAt, string $endAt) {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));
    $service = Service::factory()->create([
        'name' => 'Corte de cabelo',
        'duration_minutes' => 15,
    ]);
    $booking = Booking::factory()->for($service)->create([
        'start_at' => $startAt,
        'end_at' => $endAt,
    ]);

    $this->seed(BookingSeeder::class);

    $this->assertDatabaseCount('bookings', 4);
    $this->assertModelExists($booking);
    $this->assertDatabaseHas('bookings', [
        'service_id' => $service->id,
        'start_at' => '2026-10-23 12:00:00',
        'end_at' => '2026-10-23 12:15:00',
    ]);
})->with([
    'existing booking ends when the slot starts' => ['2026-10-23 11:45:00', '2026-10-23 12:00:00'],
    'existing booking starts when the slot ends' => ['2026-10-23 12:15:00', '2026-10-23 12:45:00'],
]);
