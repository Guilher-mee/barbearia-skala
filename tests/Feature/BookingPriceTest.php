<?php

use App\BookingSchedule;
use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingSeeder;

test('captures the service price on booking and ignores client supplied prices', function (string $price) {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'UTC'));
    $service = Service::factory()->create(['price' => $price, 'duration_minutes' => 30]);

    $booking = app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '09:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
        'price_at_booking' => '0.01', 'price' => '0.01',
    ]);

    expect($booking->fresh()->price_at_booking)->toBe($price);
})->with(['0.00', '35.50', '99999999.99']);

test('preserves the booked price when the catalog changes and captures the new price for later bookings', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'UTC'));
    $service = Service::factory()->create(['price' => '35.50', 'duration_minutes' => 30]);
    $data = [
        'service_id' => $service->id, 'date' => '2026-10-23',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ];
    $first = app(BookingSchedule::class)->create([...$data, 'time' => '09:00']);

    $service->update(['price' => '55.90']);
    $first->update(['customer_name' => 'Ana Silva']);
    $second = app(BookingSchedule::class)->create([...$data, 'time' => '10:00']);

    expect($first->fresh()->price_at_booking)->toBe('35.50');
    expect($second->fresh()->price_at_booking)->toBe('55.90');
});

test('uses the associated service price in the booking factory', function () {
    $service = Service::factory()->create(['price' => '12.34']);

    $booking = Booking::factory()->for($service)->create();

    expect($booking->fresh()->price_at_booking)->toBe('12.34');
});

test('records seeded prices even when model events are disabled', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'UTC'));

    Booking::withoutEvents(function (): void {
        $this->seed(BookingSeeder::class);
    });

    $this->assertDatabaseCount('bookings', 3);
    expect(Booking::with('service')->orderBy('start_at')->get()->pluck('price_at_booking')->all())
        ->toBe(['35.00', '25.00', '55.00']);
});

test('leaves unknown historical prices null when services or contact details change', function () {
    $service = Service::factory()->create(['price' => '35.50']);
    $booking = Booking::factory()->for($service)->create(['price_at_booking' => null]);

    $service->update(['price' => '55.90']);
    $booking->update(['customer_name' => 'Ana Silva']);

    expect($booking->fresh()->price_at_booking)->toBeNull();
});
