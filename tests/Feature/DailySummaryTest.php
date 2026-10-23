<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'UTC'));
});

test('shows an empty daily summary as predicted value rather than received payments', function () {
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertSee('Agendamentos do dia')->assertSee('Valor agendado (previsto)')->assertSee('R$ 0,00')
        ->assertSee('O valor previsto não representa pagamentos recebidos.')
        ->assertSet('summary', ['booking_count' => 0, 'unpriced_count' => 0, 'total_cents' => 0, 'total_formatted' => '0,00']);
});

test('adds snapshot prices in cents and excludes unknown amounts from the total', function () {
    $this->actingAs(User::factory()->staff()->create());
    foreach ([['12:00', '0.10'], ['13:00', '0.20'], ['14:00', '1234.56'], ['15:00', null], ['16:00', '0.00']] as [$time, $price]) {
        Booking::factory()->create([
            'start_at' => '2026-10-23 '.$time.':00',
            'end_at' => CarbonImmutable::parse('2026-10-23 '.$time.':00', 'UTC')->addMinutes(15),
            'price_at_booking' => $price,
        ]);
    }

    Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertSee('R$ 1.234,86')->assertSee('Agendamentos sem valor registrado: 1.')
        ->assertSet('summary', ['booking_count' => 5, 'unpriced_count' => 1, 'total_cents' => 123486, 'total_formatted' => '1.234,86']);
});

test('includes only bookings within the selected local day and updates after date changes', function () {
    $this->actingAs(User::factory()->staff()->create());
    foreach ([
        ['2026-10-23 02:30:00', '2026-10-23 02:45:00', '10.00'],
        ['2026-10-23 03:00:00', '2026-10-23 03:15:00', '20.00'],
        ['2026-10-24 02:30:00', '2026-10-24 02:45:00', '30.00'],
        ['2026-10-24 03:00:00', '2026-10-24 03:15:00', '40.00'],
    ] as [$startAt, $endAt, $price]) {
        Booking::factory()->create(['start_at' => $startAt, 'end_at' => $endAt, 'price_at_booking' => $price]);
    }

    $component = Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertSee('R$ 50,00')->assertSet('summary.booking_count', 2)->assertSet('summary.total_cents', 5000);

    $component->set('date', '2026-10-24')->assertSee('R$ 40,00')
        ->assertSet('summary.booking_count', 1)->assertSet('summary.total_cents', 4000);
});

test('refreshes the predicted total without substituting current catalog prices', function () {
    $this->actingAs(User::factory()->staff()->create());
    $service = Service::factory()->create(['price' => '35.50', 'duration_minutes' => 30]);
    Booking::factory()->for($service)->create(['price_at_booking' => '35.50']);
    $component = Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')->assertSee('R$ 35,50');

    $service->update(['price' => '99.99']);
    Booking::factory()->for($service)->create(['price_at_booking' => '10.25', 'start_at' => '2026-10-23 13:00:00']);

    $component->call('refreshAgenda')->assertSee('R$ 45,75')->assertSet('summary.booking_count', 2);
});

test('keeps days with only unpriced bookings distinct from an empty day', function () {
    $this->actingAs(User::factory()->staff()->create());
    Booking::factory()->create(['price_at_booking' => null]);

    Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertSee('R$ 0,00')->assertSee('Agendamentos sem valor registrado: 1.')
        ->assertSet('summary.booking_count', 1)->assertSet('summary.unpriced_count', 1);
});

test('does not display a previous summary after an invalid date is submitted', function () {
    $this->actingAs(User::factory()->staff()->create());
    Booking::factory()->create(['price_at_booking' => '35.50']);

    Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')->assertSee('R$ 35,50')
        ->set('date', 'invalid')->assertHasErrors(['date'])->assertDontSee('R$ 35,50')->assertDontSee('Resumo do dia');
});
