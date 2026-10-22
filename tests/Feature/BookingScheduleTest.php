<?php

use App\BookingSchedule;
use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'UTC'));
});

test('creates bookings with the service duration in UTC and ignores injected end times', function () {
    $service = Service::factory()->create(['duration_minutes' => 40]);

    $booking = app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '09:40',
        'customer_name' => '  Ana Silva  ', 'customer_phone' => '  +55 (11) 91234-5678  ',
        'end_at' => '2026-10-23 23:00:00', 'duration_minutes' => 1,
    ]);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id, 'service_id' => $service->id,
        'customer_name' => 'Ana Silva', 'customer_phone' => '+55 (11) 91234-5678',
        'start_at' => '2026-10-23 12:40:00', 'end_at' => '2026-10-23 13:20:00',
    ]);
});

test('offers a grid based on service duration', function (int $duration, array $firstSlots, string $lastSlot) {
    $service = Service::factory()->create(['duration_minutes' => $duration]);

    $slots = app(BookingSchedule::class)->availableSlots($service->id, '2026-10-23');

    expect(array_slice($slots, 0, 3))->toBe($firstSlots);
    expect(end($slots))->toBe($lastSlot);
})->with([
    [20, ['09:00', '09:20', '09:40'], '17:40'],
    [40, ['09:00', '09:40', '10:20'], '17:00'],
]);

test('accepts opening and closing boundaries', function (string $time, string $endAt) {
    $service = Service::factory()->create(['duration_minutes' => 20]);

    $booking = app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => $time,
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ]);

    expect($booking->end_at->toDateTimeString())->toBe($endAt);
})->with([
    ['09:00', '2026-10-23 12:20:00'],
    ['17:40', '2026-10-23 21:00:00'],
]);

test('rejects invalid input without creating a booking', function (string $field, mixed $value, string $message) {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    $data = [
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '09:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
        $field => $value,
    ];

    try {
        app(BookingSchedule::class)->create($data);
        $this->fail('A reserva inválida foi aceita.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
        expect($exception->errors()[$field])->toContain($message);
    }

    $this->assertDatabaseCount('bookings', 0);
})->with([
    'missing service' => ['service_id', '', 'Selecione um serviço.'],
    'invalid service' => ['service_id', 'abc', 'Selecione um serviço válido.'],
    'nonexistent service' => ['service_id', 99999, 'Selecione um serviço disponível.'],
    'missing date' => ['date', '', 'Selecione uma data.'],
    'invalid date' => ['date', '2026-02-30', 'Informe uma data válida.'],
    'past date' => ['date', '2026-10-21', 'Escolha hoje ou uma data futura.'],
    'missing time' => ['time', '', 'Selecione um horário.'],
    'invalid time' => ['time', '24:00', 'Informe um horário válido.'],
    'before opening' => ['time', '08:40', 'O atendimento deve ocorrer entre 09:00 e 18:00.'],
    'after closing' => ['time', '18:00', 'O atendimento deve ocorrer entre 09:00 e 18:00.'],
    'ends after closing' => ['time', '17:50', 'O atendimento deve ocorrer entre 09:00 e 18:00.'],
    'outside grid' => ['time', '09:10', 'Escolha um horário da grade deste serviço.'],
    'missing name' => ['customer_name', '   ', 'Informe seu nome.'],
    'long name' => ['customer_name', str_repeat('a', 256), 'O nome deve ter no máximo 255 caracteres.'],
    'missing phone' => ['customer_phone', '', 'Informe seu telefone.'],
    'invalid phone' => ['customer_phone', 'abcdefghij', 'Informe um telefone válido.'],
    'short phone' => ['customer_phone', '123456789', 'Informe um telefone com 10 a 15 dígitos.'],
    'long phone' => ['customer_phone', '1234567890123456', 'Informe um telefone com 10 a 15 dígitos.'],
    'phone field too long' => ['customer_phone', str_repeat('1', 31), 'O telefone deve ter no máximo 30 caracteres.'],
]);

test('rejects nonpositive service durations', function (int $duration) {
    $service = Service::factory()->create(['duration_minutes' => $duration]);

    expect(fn () => app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '09:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ]))->toThrow(ValidationException::class, 'Este serviço está com a duração inválida.');

    $this->assertDatabaseCount('bookings', 0);
})->with([0, -20]);

test('rejects a service that cannot fit in the business day', function () {
    $service = Service::factory()->create(['duration_minutes' => 600]);

    expect(app(BookingSchedule::class)->availableSlots($service->id, '2026-10-23'))->toBe([]);
    expect(fn () => app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '09:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ]))->toThrow(ValidationException::class, 'O atendimento deve ocorrer entre 09:00 e 18:00.');
});

test('rejects elapsed times and uses the local date rather than the UTC date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 01:00:00', 'UTC'));
    $service = Service::factory()->create(['duration_minutes' => 20]);

    expect(app(BookingSchedule::class)->availableSlots($service->id, '2026-10-22'))->toBe([]);
    expect(fn () => app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-22', 'time' => '17:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ]))->toThrow(ValidationException::class, 'Escolha um horário futuro.');
});

test('removes times at or before the current instant', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);

    $slots = app(BookingSchedule::class)->availableSlots($service->id, '2026-10-22');

    expect($slots)->not->toContain('09:00');
    expect($slots[0])->toBe('09:20');
});

test('prevents all overlap shapes across services', function (string $start, string $end, bool $sameService) {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    $existingService = $sameService ? $service : Service::factory()->create();
    Booking::factory()->for($existingService)->create(['start_at' => $start, 'end_at' => $end]);

    expect(app(BookingSchedule::class)->availableSlots($service->id, '2026-10-23'))->not->toContain('10:00');
    expect(fn () => app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '10:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ]))->toThrow(ValidationException::class, 'Este horário acabou de ser ocupado. Escolha outro horário.');

    $this->assertDatabaseCount('bookings', 1);
})->with([
    ['2026-10-23 13:00:00', '2026-10-23 13:20:00'],
    ['2026-10-23 12:50:00', '2026-10-23 13:10:00'],
    ['2026-10-23 13:10:00', '2026-10-23 13:30:00'],
    ['2026-10-23 13:05:00', '2026-10-23 13:15:00'],
    ['2026-10-23 12:50:00', '2026-10-23 13:30:00'],
])->with([true, false]);

test('allows adjacent appointments across services', function (string $start, string $end) {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    Booking::factory()->create(['start_at' => $start, 'end_at' => $end]);

    expect(app(BookingSchedule::class)->availableSlots($service->id, '2026-10-23'))->toContain('10:00');
    app(BookingSchedule::class)->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '10:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ]);

    $this->assertDatabaseCount('bookings', 2);
})->with([
    ['2026-10-23 12:40:00', '2026-10-23 13:00:00'],
    ['2026-10-23 13:20:00', '2026-10-23 13:40:00'],
]);

test('reloads the service duration at confirmation without changing earlier bookings', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    $schedule = app(BookingSchedule::class);
    $first = $schedule->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '09:00',
        'customer_name' => 'Ana', 'customer_phone' => '11912345678',
    ]);
    expect($schedule->availableSlots($service->id, '2026-10-23'))->toContain('10:20');
    $service->update(['duration_minutes' => 40]);

    $second = $schedule->create([
        'service_id' => $service->id, 'date' => '2026-10-23', 'time' => '10:20',
        'customer_name' => 'Bruno', 'customer_phone' => '11912345679',
    ]);

    expect($second->end_at->toDateTimeString())->toBe('2026-10-23 14:00:00');
    expect($first->fresh()->end_at->toDateTimeString())->toBe('2026-10-23 12:20:00');
});
