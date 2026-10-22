<?php

use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00', 'UTC'));
});

test('shows services and their prices without exposing existing customer details', function () {
    $service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 40, 'price' => '50.00']);
    Booking::factory()->for($service)->create(['customer_name' => 'Cliente confidencial', 'customer_phone' => '11987654321']);

    $this->get(route('home'))->assertOk()
        ->assertSee('Corte')->assertSee('40 min')->assertSee('50,00')
        ->assertDontSee('Cliente confidencial')->assertDontSee('11987654321')
        ->assertDontSee('/build/assets/');
});

test('shows an empty state when no services exist', function () {
    Livewire::test('pages::book-appointment')
        ->assertSee('Nenhum serviço disponível no momento.');
});

test('clears a selected time when service or date changes', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    $other = Service::factory()->create(['duration_minutes' => 40]);

    Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)
        ->set('date', '2026-10-23')
        ->set('time', '09:20')
        ->set('service_id', (string) $other->id)
        ->assertSet('time', '')
        ->assertSet('availableTimes', fn (array $slots): bool => in_array('09:40', $slots, true) && ! in_array('09:20', $slots, true))
        ->set('time', '09:40')
        ->set('date', '2026-10-24')
        ->assertSet('time', '');
});

test('confirms a valid reservation and resets only personal details and the time', function () {
    $service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 40]);

    Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)->set('date', '2026-10-23')->set('time', '09:40')
        ->set('customer_name', 'Ana Silva')->set('customer_phone', '(11) 91234-5678')
        ->call('save')
        ->assertHasNoErrors()->assertSee('Agendamento confirmado!')->assertSee('10:20')
        ->assertSet('customer_name', '')->assertSet('customer_phone', '')->assertSet('time', '')
        ->assertSet('service_id', (string) $service->id)->assertSet('date', '2026-10-23')
        ->assertSet('availableTimes', fn (array $slots): bool => ! in_array('09:40', $slots, true));

    $this->assertDatabaseHas('bookings', [
        'service_id' => $service->id, 'customer_name' => 'Ana Silva', 'customer_phone' => '(11) 91234-5678',
        'start_at' => '2026-10-23 12:40:00', 'end_at' => '2026-10-23 13:20:00',
    ]);
});

test('shows required field errors without saving', function () {
    Livewire::test('pages::book-appointment')->call('save')
        ->assertHasErrors(['service_id', 'time', 'customer_name', 'customer_phone']);

    $this->assertDatabaseCount('bookings', 0);
});

test('preserves customer details and refreshes availability if another client takes the time', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    $component = Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)->set('date', '2026-10-23')->set('time', '09:00')
        ->set('customer_name', 'Ana Silva')->set('customer_phone', '11912345678');
    Booking::factory()->create(['start_at' => '2026-10-23 12:00:00', 'end_at' => '2026-10-23 12:20:00']);

    $component->call('save')
        ->assertHasErrors(['time'])->assertSee('Este horário acabou de ser ocupado. Escolha outro horário.')
        ->assertSet('time', '')->assertSet('customer_name', 'Ana Silva')->assertSet('customer_phone', '11912345678')
        ->assertSet('confirmation', null)
        ->assertSet('availableTimes', fn (array $slots): bool => ! in_array('09:00', $slots, true));

    $this->assertDatabaseCount('bookings', 1);
});

test('rejects a service deleted after the form was loaded', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    $component = Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)->set('date', '2026-10-23')->set('time', '09:00')
        ->set('customer_name', 'Ana')->set('customer_phone', '11912345678');
    $service->delete();

    $component->call('save')->assertHasErrors(['service_id'])->assertSee('Selecione um serviço disponível.');

    $this->assertDatabaseCount('bookings', 0);
});

test('rejects a tampered time outside business hours', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);

    Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)->set('date', '2026-10-23')->set('time', '18:00')
        ->set('customer_name', 'Ana')->set('customer_phone', '11912345678')
        ->call('save')->assertHasErrors(['time'])->assertSee('O atendimento deve ocorrer entre 09:00 e 18:00.');

    $this->assertDatabaseCount('bookings', 0);
});

test('shows no availability when a full day is occupied by another service', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    Booking::factory()->create(['start_at' => '2026-10-23 12:00:00', 'end_at' => '2026-10-23 21:00:00']);

    Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)->set('date', '2026-10-23')
        ->assertSet('availableTimes', [])->assertSee('Nenhum horário disponível para este serviço e data.');
});
