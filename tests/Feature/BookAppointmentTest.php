<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
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

test('starts on the local current month and highlights today accessibly', function () {
    Service::factory()->create();

    $component = Livewire::test('pages::book-appointment')
        ->assertSet('date', '2026-10-22')->assertSet('calendarMonth', '2026-10')
        ->assertSee('outubro 2026')->assertSee('Hoje')
        ->assertSee('aria-current="date"', false)
        ->assertSee('Selecione um serviço para consultar os horários.');

    $days = collect($component->get('calendarDays'));
    expect($days->first()['date'])->toBe('2026-09-27')
        ->and($days->firstWhere('date', '2026-10-21')['is_past'])->toBeTrue()
        ->and($days->firstWhere('date', '2026-10-22')['is_today'])->toBeTrue();
});

test('navigates months across years and clears the date and time without losing customer details', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-22 12:00:00', 'UTC'));
    $service = Service::factory()->create(['duration_minutes' => 20]);

    Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)
        ->set('customer_name', 'Ana')->set('customer_phone', '11912345678')
        ->call('selectDate', '2026-12-23')->call('selectTime', '09:00')
        ->assertSet('time', '09:00')
        ->call('nextMonth')->assertSet('calendarMonth', '2027-01')
        ->assertSee('janeiro 2027')->assertSee('Selecione uma data.')
        ->assertSet('date', '')->assertSet('time', '')
        ->assertSet('customer_name', 'Ana')->assertSet('customer_phone', '11912345678')
        ->call('selectDate', '2027-01-01')->call('selectTime', '09:00')
        ->call('previousMonth')->assertSet('calendarMonth', '2026-12')
        ->assertSet('date', '')->assertSet('time', '')
        ->call('previousMonth')->assertSet('calendarMonth', '2026-12');
});

test('clears a time selected by button when the day or service changes', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    $other = Service::factory()->create(['duration_minutes' => 40]);

    Livewire::test('pages::book-appointment')
        ->set('service_id', (string) $service->id)
        ->call('selectDate', '2026-10-23')->call('selectTime', '09:20')
        ->assertSet('time', '09:20')
        ->call('selectDate', '2026-10-24')->assertSet('time', '')
        ->call('selectTime', '09:20')->set('service_id', (string) $other->id)
        ->assertSet('time', '')->assertSee('09:00–09:40');
});

test('rejects adulterated or past calendar dates', function (string $date) {
    Service::factory()->create();

    Livewire::test('pages::book-appointment')->call('selectDate', $date)
        ->assertHasErrors(['date'])->assertSet('date', '2026-10-22');

    $this->assertDatabaseCount('bookings', 0);
})->with(['2026-10-21', '2026-02-30', 'invalid', '']);

test('shows disabled past and reserved intervals without exposing customer information', function () {
    $service = Service::factory()->create(['duration_minutes' => 20]);
    Booking::factory()->create([
        'customer_name' => 'Nome confidencial', 'customer_phone' => '11988887777',
        'start_at' => '2026-10-22 12:20:00', 'end_at' => '2026-10-22 12:40:00',
    ]);

    $component = Livewire::test('pages::book-appointment')->set('service_id', (string) $service->id)
        ->assertSee('09:00–09:20')->assertSee('Já passou')->assertSee('Reservado')->assertSee('Livre')
        ->assertDontSee('Nome confidencial')->assertDontSee('11988887777');

    $html = $component->html();
    expect($html)->toMatch('/<button[^>]*disabled[^>]*aria-label="09:00 às 09:20 — Já passou"/s')
        ->toMatch('/<button[^>]*disabled[^>]*aria-label="09:20 às 09:40 — Reservado"/s');

    $component->call('selectTime', '09:20')->assertHasErrors(['time'])->assertSet('time', '')
        ->call('selectTime', '09:00')->assertHasErrors(['time'])->assertSet('time', '')
        ->call('selectTime', '09:40')->assertHasNoErrors()->assertSet('time', '09:40');
});

test('rejects tampered time button actions', function (string $time) {
    $service = Service::factory()->create(['duration_minutes' => 20]);

    Livewire::test('pages::book-appointment')->set('service_id', (string) $service->id)
        ->call('selectDate', '2026-10-23')->call('selectTime', $time)
        ->assertHasErrors(['time'])->assertSet('time', '');

    $this->assertDatabaseCount('bookings', 0);
})->with(['08:40', '18:00', '09:10', 'invalid']);

test('offers tomorrow when today has elapsed and shows fully reserved days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 22:00:00', 'UTC'));
    $service = Service::factory()->create(['duration_minutes' => 20]);
    Booking::factory()->create(['start_at' => '2026-10-23 12:00:00', 'end_at' => '2026-10-23 21:00:00']);

    Livewire::test('pages::book-appointment')->set('service_id', (string) $service->id)
        ->assertSee('Não há mais horários disponíveis hoje. Escolha outra data.')
        ->assertSee('Consultar amanhã')->call('selectTomorrow')->assertSet('date', '2026-10-23')
        ->assertSet('time', '')->assertSee('Todos os horários deste dia estão reservados.');
});

test('refreshes slot states after a conflict and retains customer details', function () {
    $service = Service::factory()->create(['duration_minutes' => 40]);
    $component = Livewire::test('pages::book-appointment')->set('service_id', (string) $service->id)
        ->call('selectDate', '2026-10-23')->call('selectTime', '09:00')
        ->set('customer_name', 'Ana')->set('customer_phone', '11912345678');
    Booking::factory()->create(['start_at' => '2026-10-23 12:20:00', 'end_at' => '2026-10-23 12:40:00']);

    $component->call('save')->assertHasErrors(['time'])->assertSet('time', '')
        ->assertSet('customer_name', 'Ana')->assertSet('customer_phone', '11912345678')
        ->assertSet('daySlots', fn (array $slots): bool => $slots[0]['status'] === 'booked')
        ->assertSee('Reservado');

    $this->assertDatabaseCount('bookings', 1);
});

test('books through calendar actions and displays the persisted appointment in the correct daily agenda', function () {
    $this->actingAs(User::factory()->staff()->create());
    $service = Service::factory()->create(['name' => 'Corte visual', 'duration_minutes' => 40]);
    $agenda = Livewire::test('pages::daily-agenda')->set('date', '2026-10-23');

    Livewire::test('pages::book-appointment')->set('service_id', (string) $service->id)
        ->call('selectDate', '2026-10-23')->call('selectTime', '09:40')
        ->set('customer_name', 'Cliente calendário')->set('customer_phone', '11912345678')
        ->call('save')->assertHasNoErrors()->assertSee('Agendamento confirmado!')
        ->assertSet('daySlots', fn (array $slots): bool => $slots[1]['status'] === 'booked');

    $agenda->call('refreshAgenda')->assertSee('Cliente calendário')->assertSee('Corte visual')
        ->assertSee('09:40')->assertSee('10:20')
        ->set('date', '2026-10-24')->assertDontSee('Cliente calendário');

    $this->assertDatabaseCount('bookings', 1);
});
