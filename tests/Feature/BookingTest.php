<?php

use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

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
