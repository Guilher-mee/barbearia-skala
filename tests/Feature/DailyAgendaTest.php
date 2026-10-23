<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 01:00:00', 'UTC'));
});

test('opens the agenda on the current local day with an empty state', function () {
    $this->actingAs(User::factory()->staff()->create());
    $this->get(route('agenda'))->assertOk()->assertSee('Agenda do dia');

    Livewire::test('pages::daily-agenda')->assertSet('date', '2026-10-22')
        ->assertSee('Nenhum agendamento para este dia.');
});

test('lists the selected day in chronological order with service and contact details', function () {
    $this->actingAs(User::factory()->staff()->create());
    $service = Service::factory()->create(['name' => 'Corte']);
    Booking::factory()->for($service)->create([
        'customer_name' => 'Bruno', 'customer_phone' => '11988887777',
        'start_at' => '2026-10-23 14:00:00', 'end_at' => '2026-10-23 14:40:00',
    ]);
    Booking::factory()->for($service)->create([
        'customer_name' => 'Ana', 'start_at' => '2026-10-23 12:00:00', 'end_at' => '2026-10-23 12:40:00',
    ]);
    Booking::factory()->create([
        'customer_name' => 'Outro dia', 'start_at' => '2026-10-24 12:00:00', 'end_at' => '2026-10-24 12:40:00',
    ]);

    $component = Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertHasNoErrors()->assertSee('09:00')->assertSee('09:40')->assertSee('Corte')->assertSee('11988887777')
        ->assertSee('Ana')->assertSee('Bruno')->assertDontSee('Outro dia');

    expect($component->get('bookings')->pluck('customer_name')->all())->toBe(['Ana', 'Bruno']);

    $component->set('date', '2026-10-24')->assertSee('Outro dia')->assertDontSee('Bruno');
});

test('uses local midnight boundaries when filtering UTC timestamps', function () {
    $this->actingAs(User::factory()->staff()->create());
    foreach ([
        ['Antes', '2026-10-23 02:59:00', '2026-10-23 03:00:00'],
        ['Primeiro', '2026-10-23 03:00:00', '2026-10-23 03:20:00'],
        ['Ultimo', '2026-10-24 02:59:00', '2026-10-24 03:00:00'],
        ['Depois', '2026-10-24 03:00:00', '2026-10-24 03:20:00'],
    ] as [$name, $start, $end]) {
        Booking::factory()->create(['customer_name' => $name, 'start_at' => $start, 'end_at' => $end]);
    }

    Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertSee('Primeiro')->assertSee('Ultimo')->assertDontSee('Antes')->assertDontSee('Depois');
});

test('rejects malformed dates without revealing any bookings', function () {
    $this->actingAs(User::factory()->staff()->create());
    Booking::factory()->create(['customer_name' => 'Cliente privado']);

    Livewire::test('pages::daily-agenda')->set('date', 'invalid')
        ->assertHasErrors(['date'])->assertSee('Informe uma data válida.')->assertDontSee('Cliente privado');
});

test('refreshes the agenda after another customer books', function () {
    $this->actingAs(User::factory()->staff()->create());
    $component = Livewire::test('pages::daily-agenda')->set('date', '2026-10-23');
    Booking::factory()->create([
        'customer_name' => 'Novo cliente', 'start_at' => '2026-10-23 12:00:00', 'end_at' => '2026-10-23 12:20:00',
    ]);

    $component->call('refreshAgenda')->assertSee('Novo cliente');
});

test('escapes customer names in the agenda', function () {
    $this->actingAs(User::factory()->staff()->create());
    Booking::factory()->create([
        'customer_name' => '<script>alert(1)</script>',
        'start_at' => '2026-10-23 12:00:00', 'end_at' => '2026-10-23 12:20:00',
    ]);

    Livewire::test('pages::daily-agenda')->set('date', '2026-10-23')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('denies agenda access and livewire updates after permission is revoked', function () {
    $this->actingAs(User::factory()->staff()->create());
    $component = Livewire::test('pages::daily-agenda');
    User::whereKey(auth()->id())->update(['is_staff' => false]);

    $this->get('/agenda')->assertForbidden();
    $component->call('refreshAgenda')->assertForbidden();
});
